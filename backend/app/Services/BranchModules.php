<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Database\Query\JoinClause;
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
        [$eligible, $enabled] = $this->evaluate($branch, $modules, $dependencies, $entitled, $modules->pluck('is_enabled', 'id'));

        return $modules->map(fn ($module) => [
            'id' => $module->id, 'slug' => $module->slug, 'name' => $module->name,
            'core' => (bool) $module->is_core, 'available' => (bool) $module->is_active,
            'entitled' => $eligible[$module->id], 'read_only' => $readOnly && ! $module->is_core,
            'has_override' => $module->is_enabled !== null, 'switched_on' => (bool) ($module->is_enabled ?? true), 'enabled' => $enabled[$module->id],
            'requires' => $dependencies->get($module->id, collect())
                ->map(fn ($requirement) => $modules[$requirement->requires_module_id]->slug)->values()->all(),
        ])->values();
    }

    public function adoption(CarbonInterface $now): Collection
    {
        // Load the catalogue and all explicit branch switches together.
        $catalogue = DB::table('modules')->leftJoin('school_modules', 'school_modules.module_id', '=', 'modules.id')
            ->orderBy('modules.sort_order')->orderBy('modules.id')
            ->get(['modules.id', 'modules.name', 'modules.is_core', 'modules.is_active',
                'modules.applies_to', 'school_modules.school_id', 'school_modules.is_enabled']);
        $modules = $catalogue->keyBy('id');
        $switches = $catalogue->whereNotNull('school_id')->groupBy('school_id');
        $dependencies = DB::table('module_dependencies')->get()->groupBy('module_id');
        $counts = $modules->map(fn () => 0);

        // Rank started subscriptions first. A future-only row still records that the group has subscription history.
        $subscriptions = DB::table('school_subscriptions')->select('school_subscriptions.*')
            ->selectRaw('row_number() over (partition by school_group_id order by case when starts_at <= ? then 0 else 1 end, starts_at desc, id desc) as position_in_group', [$now]);
        $entitlements = DB::table('school_subscriptions')
            ->join('plan_modules', 'plan_modules.plan_id', '=', 'school_subscriptions.plan_id')
            ->selectRaw('school_subscriptions.id as subscription_id, plan_modules.module_id')
            ->union(
                DB::table('subscription_modules')->where('starts_at', '<=', $now)
                    ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $now))
                    ->selectRaw('school_subscription_id as subscription_id, module_id')
            );
        $branches = DB::table('schools')->whereNull('schools.deleted_at')
            ->leftJoinSub($subscriptions, 'subscription', function (JoinClause $join): void {
                $join->on('subscription.school_group_id', '=', 'schools.group_id')->where('subscription.position_in_group', 1);
            })
            ->leftJoinSub($entitlements, 'entitlements', 'entitlements.subscription_id', '=', 'subscription.id')
            ->select(['schools.id as branch_id', 'schools.institute_type', 'schools.verified_at', 'subscription.id as subscription_id',
                'subscription.status', 'subscription.starts_at', 'subscription.ends_at', 'subscription.grace_ends_at', 'entitlements.module_id'])
            ->orderBy('schools.id')->cursor()->chunkWhile(fn ($row, $key, $branchRows) => $row->branch_id === $branchRows->last()->branch_id);

        foreach ($branches as $rows) {
            $branch = $rows->first();
            $entitled = null;
            if ($branch->subscription_id !== null) {
                $started = Carbon::parse($branch->starts_at)->lte($now);
                $entitled = $started && $this->subscriptionGrace((bool) $branch->verified_at, $branch, $now) !== null
                    ? $rows->pluck('module_id')->filter()->all()
                    : [];
            }
            $overrides = $switches->get($branch->branch_id, collect())->pluck('is_enabled', 'id');
            [, $enabled] = $this->evaluate($branch, $modules, $dependencies, $entitled, $overrides);
            foreach ($enabled as $id => $on) {
                $counts[$id] += (int) $on;
            }
        }

        return $modules->map(fn ($module) => ['label' => $module->name, 'value' => $counts[$module->id]])->values();
    }

    private function evaluate(?object $branch, Collection $modules, Collection $dependencies, ?array $entitled, Collection $overrides): array
    {
        $eligible = $modules->map(function ($module) use ($branch, $entitled): bool {
            $types = json_decode($module->applies_to ?? 'null', true);
            $applies = empty($types) || in_array($branch?->institute_type, $types, true);

            return (bool) $module->is_core || ((bool) $module->is_active && $applies
                && ($entitled === null || in_array($module->id, $entitled, true)));
        });
        $enabled = $modules->map(fn ($module) => (bool) $module->is_core
            || ($eligible[$module->id] && (bool) ($overrides[$module->id] ?? true)));

        do {
            $changed = false;
            foreach ($dependencies as $id => $requirements) {
                if ($enabled[$id] && $requirements->contains(fn ($requirement) => ! $enabled[$requirement->requires_module_id])) {
                    $enabled[$id] = false;
                    $changed = true;
                }
            }
        } while ($changed);

        return [$eligible, $enabled];
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
        $inGrace = $this->subscriptionGrace((bool) $branch->verified_at, $subscription, $now);
        if ($inGrace === null) {
            return [[], false];
        }

        $plan = DB::table('plan_modules')->where('plan_id', $subscription->plan_id)->pluck('module_id');
        $extras = DB::table('subscription_modules')->where('school_subscription_id', $subscription->id)
            ->where('starts_at', '<=', $now)->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->pluck('module_id');

        return [$plan->merge($extras)->unique()->all(), $inGrace];
    }

    private function subscriptionGrace(bool $verified, ?object $subscription, CarbonInterface $now): ?bool
    {
        if (! $verified || ! $subscription || in_array($subscription->status, ['expired', 'cancelled'], true)) {
            return null;
        }

        $inGrace = $subscription->status === 'grace' || ($subscription->ends_at && Carbon::parse($subscription->ends_at)->lte($now));
        if ($inGrace && (! $subscription->grace_ends_at || Carbon::parse($subscription->grace_ends_at)->lte($now))) {
            return null;
        }

        return $inGrace;
    }
}
