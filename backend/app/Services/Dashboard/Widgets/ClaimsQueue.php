<?php

namespace App\Services\Dashboard\Widgets;

use App\Services\Dashboard\DashboardContext;
use App\Services\Dashboard\Widget;
use Illuminate\Support\Facades\DB;

final class ClaimsQueue implements Widget
{
    public function definition(): array
    {
        return [
            'key' => 'platform.claims_queue', 'title' => 'Pending listing claims', 'type' => 'list', 'size' => 'md',
            'scope' => 'platform', 'requiredModule' => null, 'requiredPermission' => 'moderateListings',
        ];
    }

    public function data(DashboardContext $context): array
    {
        $rows = DB::table('listing_claims')->join('schools', 'schools.id', '=', 'listing_claims.school_id')
            ->where('listing_claims.status', 'pending')->whereNull('schools.deleted_at')
            ->orderBy('listing_claims.created_at')->orderBy('listing_claims.id')->limit(10)
            ->get(['schools.name', 'listing_claims.created_at'])->map(function (object $claim) use ($context): array {
                $submitted = \Carbon\CarbonImmutable::parse($claim->created_at, $context->asOf->timezone);
                $days = max(0, (int) $submitted->diffInDays($context->asOf));

                return ['title' => $claim->name, 'meta' => str($days)->append($days === 1 ? ' day' : ' days')->toString()];
            });

        return ['summary' => 'Oldest pending listing claims', 'data' => ['rows' => $rows, 'empty' => 'No pending claims.']];
    }
}
