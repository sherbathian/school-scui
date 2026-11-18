<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolSession;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SchoolSessionController extends Controller
{
    public function index(Request $request, int $school): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'view');

        return response()->json(['data' => $schoolRecord->schoolSessions()->orderByDesc('starts_on')->get()]);
    }

    public function store(Request $request, int $school): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $validated = $this->validated($request, $schoolRecord);

        $session = DB::transaction(function () use ($schoolRecord, $validated): SchoolSession {
            $sessions = $schoolRecord->schoolSessions()->lockForUpdate();
            if ($validated['ends_on'] <= $validated['starts_on']) {
                throw ValidationException::withMessages(['ends_on' => ['The end date must be after the start date.']]);
            }
            $this->ensureNoOverlap($sessions->get(), $validated['starts_on'], $validated['ends_on']);
            $hasCurrent = $sessions->where('is_current', true)->exists();
            $isCurrent = $hasCurrent ? ($validated['is_current'] ?? false) : true;

            if ($isCurrent) {
                $sessions->where('is_current', true)->update(['is_current' => false, 'current_marker' => null]);
            }

            return $schoolRecord->schoolSessions()->create([
                'title' => $validated['title'],
                'starts_on' => $validated['starts_on'],
                'ends_on' => $validated['ends_on'],
                'is_current' => $isCurrent,
                'current_marker' => $isCurrent ? $schoolRecord->id : null,
                'status' => $validated['status'] ?? 'active',
            ]);
        });

        return response()->json(['data' => $session], 201);
    }

    public function show(Request $request, int $school, int $schoolSession): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'view');

        return response()->json(['data' => $schoolRecord->schoolSessions()->findOrFail($schoolSession)]);
    }

    public function update(Request $request, int $school, int $schoolSession): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $session = $schoolRecord->schoolSessions()->findOrFail($schoolSession);
        $validated = $this->validated($request, $schoolRecord, $session, true);

        $session = DB::transaction(function () use ($schoolRecord, $session, $validated): SchoolSession {
            $sessions = $schoolRecord->schoolSessions()->lockForUpdate();
            $allSessions = $sessions->get();
            $startsOn = $validated['starts_on'] ?? $session->starts_on->toDateString();
            $endsOn = $validated['ends_on'] ?? $session->ends_on->toDateString();
            if ($endsOn <= $startsOn) {
                throw ValidationException::withMessages(['ends_on' => ['The end date must be after the start date.']]);
            }
            $this->ensureNoOverlap($allSessions->where('id', '!=', $session->id), $startsOn, $endsOn);
            $newIsCurrent = $validated['is_current'] ?? $session->is_current;
            if (($validated['status'] ?? $session->status) === 'inactive' && $newIsCurrent) {
                throw ValidationException::withMessages(['status' => ['Choose another current session before making this session inactive.']]);
            }

            if (! $newIsCurrent && $session->is_current && $allSessions->where('is_current', true)->count() === 1) {
                throw ValidationException::withMessages(['is_current' => ['A school must always have a current session. Choose another current session first.']]);
            }

            if ($newIsCurrent) {
                $sessions->where('is_current', true)->where('id', '!=', $session->id)
                    ->update(['is_current' => false, 'current_marker' => null]);
            }

            $session->fill($validated);
            $session->is_current = $newIsCurrent;
            $session->current_marker = $newIsCurrent ? $schoolRecord->id : null;
            $session->save();

            return $session->refresh();
        });

        return response()->json(['data' => $session]);
    }

    public function destroy(Request $request, int $school, int $schoolSession): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $session = $schoolRecord->schoolSessions()->findOrFail($schoolSession);
        if ($session->is_current) {
            throw ValidationException::withMessages(['school_session' => ['Choose another current session before archiving this one.']]);
        }
        $session->update(['status' => 'inactive']);

        return response()->json([], 204);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, School $school, ?SchoolSession $session = null, bool $sometimes = false): array
    {
        $required = $sometimes ? ['sometimes', 'required'] : ['required'];
        $date = [...$required, 'date'];
        return $request->validate([
            'title' => [...$required, 'string', 'max:100', Rule::unique('school_sessions', 'title')->where(fn (Builder $query) => $query->where('school_id', $school->id))->ignore($session?->id)],
            'starts_on' => $date,
            'ends_on' => $date,
            'is_current' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);
    }

    /** @param iterable<SchoolSession> $sessions */
    private function ensureNoOverlap(iterable $sessions, string $startsOn, string $endsOn): void
    {
        foreach ($sessions as $existing) {
            if ($startsOn <= $existing->ends_on->toDateString() && $endsOn >= $existing->starts_on->toDateString()) {
                throw ValidationException::withMessages(['starts_on' => ["These dates overlap the {$existing->title} session."]]);
            }
        }
    }

    private function accessibleSchool(Request $request, int $school, string $ability): School
    {
        $schools = $request->user()->isSuperAdmin() ? School::query() : $request->user()->activeSchools();
        $record = $schools->whereKey($school)->firstOrFail();
        Gate::authorize($ability, $record);

        return $record;
    }
}
