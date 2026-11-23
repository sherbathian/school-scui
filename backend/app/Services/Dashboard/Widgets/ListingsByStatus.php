<?php

namespace App\Services\Dashboard\Widgets;

use App\Services\Dashboard\DashboardContext;
use App\Services\Dashboard\Widget;
use App\Models\School;

final class ListingsByStatus implements Widget
{
    public function definition(): array
    {
        return [
            'key' => 'platform.listings_by_status', 'title' => 'Listings by status', 'type' => 'donut', 'size' => 'md',
            'scope' => 'platform', 'requiredModule' => null, 'requiredPermission' => 'moderateListings',
        ];
    }

    public function data(DashboardContext $context): array
    {
        $items = School::query()->selectRaw('listing_status, count(*) as total')
            ->groupBy('listing_status')->orderBy('listing_status')->get()
            ->map(fn (School $school) => ['label' => ucfirst($school->listing_status), 'value' => (int) $school->total]);

        return ['summary' => $items->map(fn (array $item) => $item['label'].': '.$item['value'])->join(', '),
            'data' => ['items' => $items]];
    }
}
