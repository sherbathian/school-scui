<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AdminDirectoryController extends Controller
{
    private const MANAGED_MEDIA_PATTERN = '~\Alisting-media/[a-zA-Z0-9]+\.(?:jpg|jpeg|png|webp|pdf)\z~';

    public const RESOURCES = [
        'boards' => 'boards',
        'education-levels' => 'education_levels',
        'facilities' => 'facilities',
        'school-groups' => 'school_groups',
        'listing-fees' => 'school_listing_fees',
        'school-media' => 'school_media',
        'course-categories' => 'course_categories',
        'courses' => 'courses',
        'programs' => 'programs',
    ];

    public const ASSIGNMENTS = [
        'boards' => ['school_board', 'board_id', 'boards'],
        'education-levels' => ['school_education_level', 'education_level_id', 'education_levels'],
        'facilities' => ['school_facility', 'facility_id', 'facilities'],
    ];

    public function index(Request $request, string $resource): JsonResponse
    {
        $table = self::RESOURCES[$resource];
        $query = DB::table($table)->orderByDesc('id');
        if (in_array($resource, ['listing-fees', 'school-media', 'programs', 'courses'], true) && $request->filled('school_id')) {
            $query->where('school_id', $request->integer('school_id'));
        }

        return response()->json($query->paginate(25));
    }

    public function options(string $resource): JsonResponse
    {
        $tables = match ($resource) {
            'listing-fees' => ['schools', 'education_levels'],
            'programs', 'school-media', 'school-groups' => ['schools'],
            'courses' => ['schools', 'cities', 'areas', 'course_categories'],
            default => [],
        };
        $options = [];
        foreach ($tables as $table) {
            $query = DB::table($table)->orderBy('name');
            $columns = ['id', 'name'];
            if ($table === 'schools') {
                $query->whereNull('deleted_at');
                if ($resource === 'school-groups') {
                    $columns[] = 'group_id';
                }
            }
            if ($table === 'areas') {
                $columns[] = 'city_id';
            }
            $options[$table] = $query->get($columns);
        }

        return response()->json(['data' => $options]);
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        return $this->save($request, $resource);
    }

    public function update(Request $request, string $resource, int $record): JsonResponse
    {
        return $this->save($request, $resource, $record);
    }

    public function destroy(string $resource, int $record): Response
    {
        DB::transaction(function () use ($resource, $record): void {
            $query = DB::table(self::RESOURCES[$resource])->where('id', $record);
            $row = $query->lockForUpdate()->firstOrFail();
            if ($resource === 'school-groups') {
                abort_if(
                    DB::table('schools')->where('group_id', $record)->exists()
                    || DB::table('school_group_user')->where('school_group_id', $record)->exists()
                    || DB::table('school_subscriptions')->where('school_group_id', $record)->exists()
                    || DB::table('subscription_invoices')->where('school_group_id', $record)->exists(),
                    409,
                    'This organisation still has branches, access assignments, or subscriptions.'
                );
            }
            $query->delete();
            if ($resource === 'school-media') {
                $this->removeUnusedMedia($row->path);
            }
        });

        return response()->noContent();
    }

    public function downloadMedia(int $record): Response
    {
        $path = DB::table('school_media')->where('id', $record)->firstOrFail()->path;
        $disk = Storage::disk('public');
        abort_unless(is_string($path) && preg_match(self::MANAGED_MEDIA_PATTERN, $path) && $disk->exists($path), 404);

        return $disk->download($path);
    }

    public function attachBranch(int $group, int $branch): JsonResponse
    {
        DB::transaction(function () use ($group, $branch): void {
            $school = DB::table('schools')->whereNull('deleted_at')->where('id', $branch);
            $record = $school->lockForUpdate()->firstOrFail();
            $organisation = DB::table('school_groups')->where('id', $group)->lockForUpdate()->firstOrFail();
            abort_unless($organisation->status === 'active', 409, 'Select an active organisation.');
            abort_if($record->group_id !== null && (int) $record->group_id !== $group, 409, 'This branch already belongs to another organisation.');
            if ($record->group_id === null) {
                $school->update([
                    'group_id' => $group,
                    'is_main_branch' => ! DB::table('schools')->where('group_id', $group)->whereNull('deleted_at')->exists(),
                    'updated_at' => now(),
                ]);
            }
        }, 3);

        return response()->json(['data' => ['school_id' => $branch, 'group_id' => $group]]);
    }

    public function assignments(int $branch, string $assignment): JsonResponse
    {
        [$table, $key, $catalogue] = self::ASSIGNMENTS[$assignment];
        DB::table('schools')->whereNull('deleted_at')->where('id', $branch)->firstOrFail();

        return response()->json([
            'data' => DB::table($table)->where('school_id', $branch)->orderBy($key)->pluck($key),
            'options' => DB::table($catalogue)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function syncAssignments(Request $request, int $branch, string $assignment): JsonResponse
    {
        [$table, $key, $catalogue] = self::ASSIGNMENTS[$assignment];
        $data = $request->validate([
            'ids' => ['present', 'list', 'max:500'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        validator($data, ['ids' => [Rule::exists($catalogue, 'id')]])->validate();

        DB::transaction(function () use ($table, $key, $branch, $data): void {
            DB::table('schools')->whereNull('deleted_at')->where('id', $branch)->lockForUpdate()->firstOrFail();
            DB::table($table)->where('school_id', $branch)->whereNotIn($key, $data['ids'])->delete();
            if ($data['ids'] !== []) {
                $rows = array_map(fn ($id) => ['school_id' => $branch, $key => $id], $data['ids']);
                DB::table($table)->upsert($rows, ['school_id', $key], [$key]);
            }
        });

        return $this->assignments($branch, $assignment);
    }

    private function save(Request $request, string $resource, ?int $id = null): JsonResponse
    {
        $table = self::RESOURCES[$resource];
        $existing = $id === null ? null : DB::table($table)->where('id', $id)->firstOrFail();
        $rules = $this->rules($resource);
        if ($existing) {
            foreach ($rules as &$rule) {
                array_unshift($rule, 'sometimes');
            }
            unset($rule);
        }
        $data = $request->validate($rules);
        if ($resource === 'courses') {
            $this->validateCourseLocation($data, $existing);
        }
        if (isset($data['last_confirmed_at'])) {
            $data['last_confirmed_at'] = Carbon::parse($data['last_confirmed_at'])->format('Y-m-d H:i:s');
        }
        $upload = $data['file'] ?? null;
        unset($data['file']);
        if ($resource === 'school-media') {
            $path = array_key_exists('path', $data) ? $data['path'] : ($existing->path ?? null);
            $url = array_key_exists('url', $data) ? $data['url'] : ($existing->url ?? null);
            if ($path && preg_match(self::MANAGED_MEDIA_PATTERN, $path) && $path !== ($existing->path ?? null)) {
                throw ValidationException::withMessages(['path' => 'Upload a file to create a managed media path.']);
            }
            if (! $upload && ! $path && ! $url) {
                throw ValidationException::withMessages(['url' => 'Enter a media URL or storage path.']);
            }
            if (! $existing) {
                $data['uploaded_by_user_id'] = $request->user()->id;
            }
        }

        if (array_key_exists('slug', $rules)) {
            $slug = $data['slug'] ?? $existing?->slug ?? Str::slug($data['title'] ?? $data['name']);
            $unique = Rule::unique($table, 'slug')->ignore($id);
            if ($resource === 'programs') {
                $unique->where('school_id', $data['school_id'] ?? $existing->school_id);
            }
            $max = match ($resource) {
                'courses' => 170,
                'programs', 'school-groups' => 150,
                default => 100,
            };
            validator(['slug' => $slug], ['slug' => ['required', 'alpha_dash:ascii', "max:{$max}", $unique]])->validate();
            $data['slug'] = $slug;
        }

        $storedPath = null;
        if ($upload) {
            $storedPath = $upload->store('listing-media', 'public');
            abort_unless($storedPath, 500, 'The media file could not be saved.');
            $data['path'] = $storedPath;
        }

        try {
            if ($existing) {
                DB::table($table)->where('id', $id)->update([...$data, 'updated_at' => now()]);
            } else {
                $id = DB::table($table)->insertGetId([...$data, 'created_at' => now(), 'updated_at' => now()]);
            }
        } catch (\Throwable $exception) {
            if ($storedPath) {
                Storage::disk('public')->delete($storedPath);
            }
            if ($exception instanceof UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['slug' => 'This slug is already in use.']);
            }

            throw $exception;
        }

        if ($resource === 'school-media' && $existing && array_key_exists('path', $data) && $existing->path !== $data['path']) {
            $this->removeUnusedMedia($existing->path);
        }

        return response()->json(['data' => DB::table($table)->where('id', $id)->firstOrFail()], $existing ? 200 : 201);
    }

    private function removeUnusedMedia(?string $path): void
    {
        if ($path === null || ! preg_match(self::MANAGED_MEDIA_PATTERN, $path)) {
            return;
        }

        DB::afterCommit(function () use ($path): void {
            if (! DB::table('school_media')->where('path', $path)->exists()) {
                Storage::disk('public')->delete($path);
            }
        });
    }

    private function validateCourseLocation(array $data, ?object $existing): void
    {
        $city = $data['city_id'] ?? $existing?->city_id;
        $area = array_key_exists('area_id', $data) ? $data['area_id'] : $existing?->area_id;
        if ($area !== null && ! DB::table('areas')->where('id', $area)->where('city_id', $city)->exists()) {
            throw ValidationException::withMessages(['area_id' => 'Choose an area in the selected city.']);
        }
    }

    private function rules(string $resource): array
    {
        $lookup = [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string'],
            'sort_order' => ['integer', 'between:-32768,32767'],
        ];
        $school = Rule::exists('schools', 'id')->whereNull('deleted_at');
        $money = ['nullable', 'numeric', 'decimal:0,2', 'between:0,99999999.99'];
        $filePath = ['nullable', 'string', 'max:255', 'regex:~\A(?!/)(?!.*(?:^|/)\.\.(?:/|$))[a-zA-Z0-9_./-]+\z~'];

        return match ($resource) {
            'boards' => [...$lookup, 'kind' => [Rule::in(['board', 'curriculum', 'university_charter'])], 'is_active' => ['boolean']],
            'education-levels', 'course-categories' => $lookup,
            'facilities' => [...$lookup, 'kind' => [Rule::in(['facility', 'focus'])], 'icon' => ['nullable', 'string', 'max:60'], 'is_active' => ['boolean']],
            'school-groups' => [
                'name' => ['required', 'string', 'max:150'], 'slug' => ['nullable', 'string'],
                'description' => ['nullable', 'string', 'max:16000'], 'website_url' => ['nullable', 'url:http,https', 'max:255'],
                'logo_path' => $filePath, 'status' => [Rule::in(['active', 'suspended'])],
            ],
            'listing-fees' => [
                'school_id' => ['required', 'integer', $school], 'education_level_id' => ['nullable', 'integer', Rule::exists('education_levels', 'id')],
                'label' => ['nullable', 'string', 'max:100'], 'monthly_fee' => $money, 'admission_fee' => $money, 'annual_charges' => $money,
                'currency' => ['string', 'regex:/^[A-Z]{3}$/'], 'effective_from' => ['nullable', 'date_format:Y-m-d'],
                'last_confirmed_at' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:255'],
            ],
            'school-media' => [
                'school_id' => ['required', 'integer', $school], 'kind' => [Rule::in(['photo', 'brochure', 'video'])], 'path' => $filePath,
                'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
                'url' => ['nullable', 'url:http,https', 'max:500'], 'caption' => ['nullable', 'string', 'max:255'],
                'alt_text' => ['nullable', 'string', 'max:255'], 'sort_order' => $lookup['sort_order'], 'is_approved' => ['boolean'],
            ],
            'courses' => [
                'school_id' => ['nullable', 'integer', $school], 'provider_name' => ['nullable', 'string', 'max:150'],
                'city_id' => ['required', 'integer', Rule::exists('cities', 'id')], 'area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')],
                'course_category_id' => ['nullable', 'integer', Rule::exists('course_categories', 'id')],
                'title' => ['required', 'string', 'max:150'], 'slug' => ['nullable', 'string'],
                'summary' => ['nullable', 'string', 'max:300'], 'description' => ['nullable', 'string', 'max:16000'],
                'duration_value' => ['nullable', 'integer', 'between:1,32767'], 'duration_unit' => ['nullable', Rule::in(['days', 'weeks', 'months'])],
                'mode' => [Rule::in(['onsite', 'online', 'hybrid'])], 'fee' => $money, 'next_start_date' => ['nullable', 'date_format:Y-m-d'], 'status' => [Rule::in(['draft', 'published', 'archived'])],
            ],
            'programs' => [
                'school_id' => ['required', 'integer', $school], 'title' => ['required', 'string', 'max:150'], 'slug' => ['nullable', 'string'],
                'level' => [Rule::in(['intermediate', 'bachelor', 'master', 'phd', 'diploma', 'certificate'])], 'field' => ['nullable', 'string', 'max:100'],
                'duration_months' => ['nullable', 'integer', 'between:1,32767'], 'fee_amount' => $money,
                'fee_period' => ['nullable', Rule::in(['semester', 'year', 'month', 'total'])], 'eligibility' => ['nullable', 'string', 'max:16000'],
                'admission_open' => ['boolean'], 'status' => [Rule::in(['active', 'archived'])],
            ],
        };
    }
}
