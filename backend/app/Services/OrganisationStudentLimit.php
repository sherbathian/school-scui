<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OrganisationStudentLimit
{
    public const KEY = 'max_students';

    // Take this lock inside the writing transaction, before locking student rows.
    public function lock(School $school): ?int
    {
        if ($school->group_id === null) {
            return null;
        }

        return DB::table('school_groups')->where('id', $school->group_id)->lockForUpdate()->first(['id'])?->id;
    }

    public function assertSeatAvailable(?int $group): void
    {
        if ($group === null) {
            return;
        }

        $subscription = DB::table('school_subscriptions')->where('school_group_id', $group)
            ->where('starts_at', '<=', now())->orderByDesc('starts_at')->orderByDesc('id')->first(['id', 'plan_id']);
        if (! $subscription) {
            return;
        }

        $override = DB::table('subscription_limits')->where('school_subscription_id', $subscription->id)
            ->where('limit_key', self::KEY)->first(['limit_value']);
        $limit = $override ? $override->limit_value : DB::table('plan_limits')
            ->where('plan_id', $subscription->plan_id)->where('limit_key', self::KEY)->value('limit_value');
        if ($limit === null) {
            return;
        }

        $usage = DB::table('students')->join('schools', 'schools.id', '=', 'students.school_id')
            ->where('schools.group_id', $group)->whereNull('schools.deleted_at')
            ->where('students.status', 'active')->count();
        if ($usage >= (int) $limit) {
            throw ValidationException::withMessages([
                'status' => "This organisation has reached its plan limit of {$limit} active students.",
            ]);
        }
    }
}
