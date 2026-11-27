<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

final class SchoolWebsiteAccess
{
    public const RESOURCES = ['school-pages', 'school-site-settings'];

    public function __construct(private readonly BranchModules $modules) {}

    /** @return Collection<int, int> */
    public function branchIds(?User $user, bool $writing = false): Collection
    {
        if (! $user?->is_active) {
            return collect();
        }

        return $user->ownedActiveSchools()->pluck('schools.id')->map(fn ($id) => (int) $id)->filter(function (int $school) use ($writing): bool {
            $website = $this->modules->forBranch($school)->firstWhere('slug', 'website');

            return ($website['enabled'] ?? false) && (! $writing || ! ($website['read_only'] ?? false));
        })->values();
    }
}
