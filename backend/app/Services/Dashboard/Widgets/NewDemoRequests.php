<?php

namespace App\Services\Dashboard\Widgets;

use App\Services\Dashboard\DashboardContext;
use App\Services\Dashboard\Widget;
use Illuminate\Support\Facades\DB;

final class NewDemoRequests implements Widget
{
    public function definition(): array
    {
        return [
            'key' => 'platform.new_demo_requests', 'title' => 'New demo requests', 'type' => 'kpi',
            'size' => 'sm', 'scope' => 'platform', 'requiredModule' => null, 'requiredPermission' => 'moderateListings',
        ];
    }

    public function data(DashboardContext $context): array
    {
        $count = DB::table('demo_requests')->where('status', 'new')->count();

        return ['summary' => 'Demo requests with New status.', 'data' => ['value' => $count]];
    }
}
