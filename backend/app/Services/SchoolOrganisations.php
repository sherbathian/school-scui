<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SchoolOrganisations
{
    // Call while holding the school row lock.
    public function ensureFor(School $school): int
    {
        if ($school->group_id !== null) {
            return $school->group_id;
        }

        $group = DB::table('school_groups')->insertGetId([
            'name' => Str::limit($school->name, 150, ''),
            'slug' => 'campus-'.$school->id.'-'.Str::limit($school->slug, 120, ''),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $school->forceFill(['group_id' => $group, 'is_main_branch' => true])->save();

        $plan = DB::table('plans')->where('is_default', true)->where('is_active', true)->orderBy('id')->value('id');
        if ($plan !== null && $school->verified_at) {
            DB::table('school_subscriptions')->insert([
                'school_group_id' => $group, 'plan_id' => $plan, 'starts_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $group;
    }
}
