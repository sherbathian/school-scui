<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuardianController extends Controller
{
    public function index(Request $request, int $school): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'view');
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sort' => ['sometimes', 'string', 'in:id,source_parent_id,father_name,father_phone,father_occupation,father_cnic,mother_name,students_count'],
            'direction' => ['sometimes', 'string', 'in:asc,desc'],
        ]);
        $query = $schoolRecord->guardians()
            ->select(['id', 'school_id', 'source_parent_id', 'father_name', 'father_phone', 'father_occupation', 'father_cnic', 'father_doc_path', 'mother_name', 'mother_phone', 'mother_occupation', 'mother_cnic', 'mother_doc_path', 'is_guardian_other', 'guardian_name', 'guardian_relation', 'guardian_email', 'guardian_phone', 'guardian_occupation', 'guardian_cnic', 'guardian_address', 'guardian_doc_path'])
            ->withCount('students');
        $term = trim((string) ($validated['search'] ?? ''));
        if ($term !== '') {
            $query->where(function ($builder) use ($term): void {
                foreach (['source_parent_id', 'father_name', 'father_phone', 'father_occupation', 'father_cnic', 'mother_name', 'mother_phone', 'mother_cnic', 'guardian_name', 'guardian_phone', 'guardian_cnic'] as $column) {
                    $builder->orWhere($column, 'like', "%{$term}%");
                }
            });
        }
        $sort = $validated['sort'] ?? 'father_name';
        $direction = $validated['direction'] ?? 'asc';
        $query->orderBy($sort === 'students_count' ? 'students_count' : $sort, $direction)->orderBy('id');
        $usePagination = $request->hasAny(['page', 'per_page', 'search', 'sort', 'direction']);
        if (! $usePagination) {
            $guardians = $query->get();

            return response()->json(['data' => $guardians->map(fn (Guardian $guardian): array => $this->guardianData($guardian))->values()]);
        }
        $guardians = $query->paginate($validated['per_page'] ?? 25)->appends($request->query());

        return response()->json([
            'data' => $guardians->getCollection()->map(fn (Guardian $guardian): array => $this->guardianData($guardian))->values(),
            'meta' => [
                'current_page' => $guardians->currentPage(), 'last_page' => $guardians->lastPage(),
                'per_page' => $guardians->perPage(), 'total' => $guardians->total(),
                'from' => $guardians->firstItem(), 'to' => $guardians->lastItem(),
            ],
        ]);
    }

    public function store(Request $request, int $school): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $validated = $request->validate($this->rules());
        $files = $this->extractDocuments($validated);
        $validated['is_guardian_other'] = (bool) ($validated['is_guardian_other'] ?? false);
        $this->validateOtherGuardian($validated);

        $guardian = $schoolRecord->guardians()->create($validated);
        $this->storeDocuments($guardian, $files, $schoolRecord->id);

        return response()->json(['data' => $this->guardianData($guardian->fresh())], 201);
    }

    public function show(Request $request, int $school, int $guardian): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'view');
        $guardianRecord = $schoolRecord->guardians()->findOrFail($guardian);

        return response()->json(['data' => $this->guardianData($guardianRecord, true)]);
    }

    public function update(Request $request, int $school, int $guardian): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $guardianRecord = $schoolRecord->guardians()->findOrFail($guardian);
        $validated = $request->validate($this->rules(true));
        $files = $this->extractDocuments($validated);
        $removeFiles = [];
        foreach (['father', 'mother', 'guardian'] as $key) {
            $removeKey = "remove_{$key}_doc";
            if (($validated[$removeKey] ?? false) && ! isset($files["{$key}_doc"])) {
                $removeFiles[] = "{$key}_doc_path";
            }
            unset($validated[$removeKey]);
        }
        if (array_key_exists('is_guardian_other', $validated)) {
            $validated['is_guardian_other'] = (bool) $validated['is_guardian_other'];
        }
        $this->validateOtherGuardian($validated);
        $guardianRecord->update($validated);
        $this->storeDocuments($guardianRecord, $files, $schoolRecord->id, $removeFiles);

        return response()->json(['data' => $this->guardianData($guardianRecord->fresh())]);
    }

    public function destroy(Request $request, int $school, int $guardian): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $guardianRecord = $schoolRecord->guardians()->findOrFail($guardian);
        if ($guardianRecord->students()->exists()) {
            throw ValidationException::withMessages(['guardian' => ['This guardian is linked to students. Remove those student links before deleting the guardian.']]);
        }
        foreach (['father_doc_path', 'mother_doc_path', 'guardian_doc_path'] as $key) {
            if ($guardianRecord->{$key}) {
                Storage::disk('local')->delete($guardianRecord->{$key});
            }
        }
        $guardianRecord->delete();

        return response()->json([], 204);
    }

    public function media(int $school, int $guardian, string $kind): StreamedResponse
    {
        $guardianRecord = School::query()->findOrFail($school)->guardians()->findOrFail($guardian);
        $path = match ($kind) {
            'father' => $guardianRecord->father_doc_path,
            'mother' => $guardianRecord->mother_doc_path,
            'guardian' => $guardianRecord->guardian_doc_path,
            default => null,
        };
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, basename($path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    private function rules(bool $updating = false): array
    {
        $sourceParentId = Rule::unique('guardians', 'source_parent_id')
            ->where(fn ($query) => $query->where('school_id', request()->route('school')));
        if ($updating) {
            $sourceParentId->ignore((int) request()->route('guardian'));
        }

        return [
            'source_parent_id' => ['sometimes', 'nullable', 'string', 'max:50', $sourceParentId],
            'source_data' => ['sometimes', 'nullable', 'array'],
            'father_name' => [$updating ? 'sometimes' : 'nullable', 'nullable', 'string', 'max:255'], 'father_phone' => ['nullable', 'string', 'max:50'],
            'father_occupation' => ['nullable', 'string', 'max:255'], 'father_cnic' => ['nullable', 'string', 'max:30'],
            'mother_name' => ['nullable', 'string', 'max:255'], 'mother_phone' => ['nullable', 'string', 'max:50'],
            'mother_occupation' => ['nullable', 'string', 'max:255'], 'mother_cnic' => ['nullable', 'string', 'max:30'],
            'is_guardian_other' => ['sometimes', 'boolean'], 'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_relation' => ['nullable', 'string', 'max:100'], 'guardian_email' => ['nullable', 'email', 'max:255'],
            'guardian_phone' => ['nullable', 'string', 'max:50'], 'guardian_occupation' => ['nullable', 'string', 'max:255'],
            'guardian_cnic' => ['nullable', 'string', 'max:30'], 'guardian_address' => ['nullable', 'string'],
            'father_doc' => ['sometimes', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'mother_doc' => ['sometimes', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'guardian_doc' => ['sometimes', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'remove_father_doc' => ['sometimes', 'boolean'], 'remove_mother_doc' => ['sometimes', 'boolean'],
            'remove_guardian_doc' => ['sometimes', 'boolean'],
        ];
    }

    private function validateOtherGuardian(array $validated): void
    {
        if (($validated['is_guardian_other'] ?? false)
            && (empty($validated['guardian_name']) || empty($validated['guardian_relation']) || empty($validated['guardian_phone']))) {
            throw ValidationException::withMessages(['guardian_name' => ['Name, relation, and phone are required when another guardian is enabled.']]);
        }
    }

    /** @return array<string, mixed> */
    private function extractDocuments(array &$validated): array
    {
        $files = [];
        foreach (['father_doc', 'mother_doc', 'guardian_doc'] as $key) {
            $files[$key] = $validated[$key] ?? null;
            unset($validated[$key]);
        }

        return array_filter($files);
    }

    /** @param array<string, UploadedFile> $files
     * @param  array<int, string>  $removeFiles
     */
    private function storeDocuments(Guardian $guardian, array $files, int $schoolId, array $removeFiles = []): void
    {
        foreach (['father_doc', 'mother_doc', 'guardian_doc'] as $key) {
            $pathKey = "{$key}_path";
            if (! isset($files[$key]) && ! in_array($pathKey, $removeFiles, true)) {
                continue;
            }
            if ($guardian->{$pathKey}) {
                Storage::disk('local')->delete($guardian->{$pathKey});
            }
            $guardian->{$pathKey} = isset($files[$key])
                ? $files[$key]->store("guardians/{$schoolId}/{$key}", 'local')
                : null;
        }
        $guardian->save();
    }

    /** @return array<string, mixed> */
    private function guardianData(Guardian $guardian, bool $includeMediaUrls = false): array
    {
        $data = $guardian->only([
            'id', 'source_parent_id', 'father_name', 'father_phone', 'father_occupation', 'father_cnic', 'father_doc_path',
            'mother_name', 'mother_phone', 'mother_occupation', 'mother_cnic', 'mother_doc_path',
            'is_guardian_other', 'guardian_name', 'guardian_relation', 'guardian_email', 'guardian_phone',
            'guardian_occupation', 'guardian_cnic', 'guardian_address', 'guardian_doc_path',
        ]);
        $data['students_count'] = $guardian->students_count ?? $guardian->students()->count();
        if ($includeMediaUrls) {
            foreach (['father', 'mother', 'guardian'] as $kind) {
                if ($guardian->{"{$kind}_doc_path"}) {
                    $data["{$kind}_doc_url"] = URL::temporarySignedRoute('guardians.media', now()->addMinutes(30), [
                        'school' => $guardian->school_id,
                        'guardian' => $guardian->id,
                        'kind' => $kind,
                    ], absolute: false);
                }
            }
            $data['children'] = $guardian->students()
                ->with('enrollments.grade:id,title', 'enrollments.section:id,title')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
                ->map(function ($student): array {
                    $activeEnrollment = $student->enrollments->sortByDesc('id')->firstWhere('status', 'active');

                    return [
                        'id' => $student->id,
                        'admission_number' => $student->admission_number,
                        'first_name' => $student->first_name,
                        'last_name' => $student->last_name,
                        'status' => $student->status,
                        'current_enrollment' => $activeEnrollment ? [
                            'grade' => $activeEnrollment->grade?->title,
                            'section' => $activeEnrollment->section?->title,
                            'academic_year' => $activeEnrollment->academic_year,
                        ] : null,
                    ];
                })
                ->values()
                ->all();
        }

        return $data;
    }

    private function accessibleSchool(Request $request, int $school, string $ability): School
    {
        $schools = $request->user()->isSuperAdmin() ? School::query() : $request->user()->activeSchools();
        $schoolRecord = $schools->whereKey($school)->firstOrFail();
        Gate::authorize($ability, $schoolRecord);

        return $schoolRecord;
    }
}
