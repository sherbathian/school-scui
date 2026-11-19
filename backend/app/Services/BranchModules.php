<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BranchModules
{
    public function forBranch(int $school): Collection
    {
        $modules = DB::table('modules')
            ->leftJoin('school_modules', fn ($join) => $join->on('school_modules.module_id', '=', 'modules.id')
                ->where('school_modules.school_id', $school))
            ->orderBy('modules.sort_order')
            ->get(['modules.id', 'modules.slug', 'modules.name', 'modules.is_core', 'modules.is_active', 'school_modules.is_enabled'])
            ->keyBy('id');
        $dependencies = DB::table('module_dependencies')->get()->groupBy('module_id');
        $enabled = $modules->map(fn ($module) => (bool) $module->is_core
            || ((bool) $module->is_active && (bool) ($module->is_enabled ?? true)));

        do {
            $changed = false;
            foreach ($dependencies as $id => $requirements) {
                if ($enabled[$id] && $requirements->contains(fn ($requirement) => ! $enabled[$requirement->requires_module_id])) {
                    $enabled[$id] = false;
                    $changed = true;
                }
            }
        } while ($changed);

        return $modules->map(fn ($module) => [
            'id' => $module->id, 'slug' => $module->slug, 'name' => $module->name,
            'core' => (bool) $module->is_core, 'available' => (bool) $module->is_active,
            'switched_on' => (bool) ($module->is_enabled ?? true), 'enabled' => $enabled[$module->id],
            'requires' => $dependencies->get($module->id, collect())
                ->map(fn ($requirement) => $modules[$requirement->requires_module_id]->slug)->values()->all(),
        ])->values();
    }

    public function assertEnabled(int $school, string $slug): void
    {
        $module = $this->forBranch($school)->firstWhere('slug', $slug);
        abort_if($module && ! $module['enabled'], 403, 'This module is disabled for the selected branch.');
    }
}
