<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BranchModules
{
    public function forBranch(int $school): Collection
    {
        $branch = DB::table('schools')->where('id', $school)->first(['group_id', 'institute_type', 'verified_at']);
        [$entitled, $readOnly] = $this->subscriptionAccess($branch);
        $modules = DB::table('modules')
            ->leftJoin('school_modules', fn ($join) => $join->on('school_modules.module_id', '=', 'modules.id')
                ->where('school_modules.school_id', $school))
            ->orderBy('modules.sort_order')
            ->get(['modules.id', 'modules.slug', 'modules.name', 'modules.is_core', 'modules.is_active', 'modules.applies_to', 'school_modules.is_enabled'])
            ->keyBy('id');
        $dependencies = DB::table('module_dependencies')->get()->groupBy('module_id');
        $eligible = $modules->map(function ($module) use ($branch, $entitled): bool {
            $types = json_decode($module->applies_to ?? 'null', true);
            $applies = empty($types) || in_array($branch?->institute_type, $types, true);

            return (bool) $module->is_core || ((bool) $module->is_active && $applies
                && ($entitled === null || in_array($module->id, $entitled, true)));
        });
        $enabled = $modules->map(fn ($module) => (bool) $module->is_core
            || ($eligible[$module->id] && (bool) ($module->is_enabled ?? true)));

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
            'entitled' => $eligible[$module->id], 'read_only' => $readOnly && ! $module->is_core,
            'has_override' => $module->is_enabled !== null, 'switched_on' => (bool) ($module->is_enabled ?? true), 'enabled' => $enabled[$module->id],
            'requires' => $dependencies->get($module->id, collect())
                ->map(fn ($requirement) => $modules[$requirement->requires_module_id]->slug)->values()->all(),
        ])->values();
    }

    public function assertEnabled(int $school, string $slug, bool $writing = false): void
    {
        $module = $this->forBranch($school)->firstWhere('slug', $slug);
        abort_if($module && ! $module['enabled'], 403, 'This module is disabled for the selected branch.');
        abort_if($module && $writing && $module['read_only'], 403, 'This module is read-only during the subscription grace period.');
    }

    private function subscriptionAccess(?object $branch): array
    {
        if ($branch?->group_id === null) {
            return [null, false];
        }

        $subscriptions = DB::table('school_subscriptions')->where('school_group_id', $branch->group_id);
        if (! (clone $subscriptions)->exists()) {
            return [null, false];
        }

        $now = now();
        $subscription = $subscriptions->where('starts_at', '<=', $now)->orderByDesc('starts_at')->orderByDesc('id')->first();
        if (! $branch->verified_at || ! $subscription || in_array($subscription->status, ['expired', 'cancelled'], true)) {
            return [[], false];
        }

        $inGrace = $subscription->status === 'grace' || ($subscription->ends_at && Carbon::parse($subscription->ends_at)->lte($now));
        if ($inGrace && (! $subscription->grace_ends_at || Carbon::parse($subscription->grace_ends_at)->lte($now))) {
            return [[], false];
        }

        $plan = DB::table('plan_modules')->where('plan_id', $subscription->plan_id)->pluck('module_id');
        $extras = DB::table('subscription_modules')->where('school_subscription_id', $subscription->id)
            ->where('starts_at', '<=', $now)->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->pluck('module_id');

        return [$plan->merge($extras)->unique()->all(), $inGrace];
    }
}
