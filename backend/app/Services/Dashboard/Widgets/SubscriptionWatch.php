<?php

namespace App\Services\Dashboard\Widgets;

use App\Services\Dashboard\DashboardContext;
use App\Services\Dashboard\Widget;
use Illuminate\Support\Facades\DB;

final class SubscriptionWatch implements Widget
{
    public function definition(): array
    {
        return [
            'key' => 'platform.subscription_watch', 'title' => 'Subscriptions ending or in grace',
            'type' => 'list', 'size' => 'md', 'scope' => 'platform',
            'requiredModule' => null, 'requiredPermission' => 'moderateListings',
        ];
    }

    public function data(DashboardContext $context): array
    {
        $now = $context->asOf->toDateTimeString();
        $until = $context->asOf->addDays(14)->toDateTimeString();
        $started = DB::table('school_subscriptions')->where('starts_at', '<=', $now)
            ->select(['school_group_id', 'status', 'ends_at', 'grace_ends_at'])
            ->selectRaw('row_number() over (partition by school_group_id order by starts_at desc, id desc) as position_in_group');
        $current = DB::query()->fromSub($started, 'subscriptions')->where('position_in_group', 1)
            ->whereNotIn('status', ['cancelled', 'expired'])
            ->select(['school_group_id', 'grace_ends_at', 'ends_at'])
            ->selectRaw('case when status = ? or ends_at <= ? then 1 else 0 end as in_grace', ['grace', $now]);
        $rows = DB::query()->fromSub($current, 'subscriptions')
            ->join('school_groups', 'school_groups.id', '=', 'subscriptions.school_group_id')
            ->where(function ($query) use ($now, $until): void {
                $query->where(fn ($grace) => $grace->where('in_grace', 1)->where('grace_ends_at', '>', $now))
                    ->orWhere(fn ($ending) => $ending->where('in_grace', 0)->whereBetween('ends_at', [$now, $until]));
            })
            ->select('school_groups.name', 'in_grace')
            ->selectRaw('case when in_grace = 1 then grace_ends_at else ends_at end as deadline')
            ->orderBy('deadline')->orderBy('school_groups.id')->limit(10)->get()
            ->map(fn (object $row) => [
                'title' => $row->name,
                'meta' => ($row->in_grace ? 'Grace ends ' : 'Ends ').substr($row->deadline, 0, 10),
            ]);

        return [
            'summary' => 'Current subscriptions in grace or ending within 14 days.',
            'data' => ['rows' => $rows, 'empty' => 'No subscriptions in grace or ending within 14 days.'],
        ];
    }
}
