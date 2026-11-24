<?php

namespace App\Services\Dashboard\Widgets;

use App\Models\School;
use App\Services\Dashboard\DashboardContext;
use App\Services\Dashboard\Widget;

final class ListingsByCity implements Widget
{
    public function definition(): array
    {
        return [
            'key' => 'platform.listings_by_city', 'title' => 'Listings by city', 'type' => 'bar',
            'size' => 'md', 'scope' => 'platform', 'requiredModule' => null, 'requiredPermission' => 'moderateListings',
        ];
    }

    public function data(DashboardContext $context): array
    {
        $items = School::query()->leftJoin('cities', 'cities.id', '=', 'schools.city_id')
            ->selectRaw('cities.name as city_name, count(*) as total')
            ->groupBy('cities.id', 'cities.name')->orderByDesc('total')->orderBy('cities.name')->orderBy('cities.id')
            ->limit(10)->get()->map(fn (School $city) => [
                'label' => $city->city_name ?? 'City not set',
                'value' => (int) $city->total,
            ]);

        return [
            'summary' => $items->map(fn (array $item) => $item['label'].': '.$item['value'])->join(', '),
            'data' => ['items' => $items],
        ];
    }
}
