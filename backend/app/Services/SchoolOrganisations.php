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

        return $group;
    }
}
