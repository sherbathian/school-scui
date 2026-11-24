<?php

namespace App\Services\Dashboard;

use App\Models\School;
use Carbon\CarbonImmutable;

final readonly class DashboardContext
{
    public CarbonImmutable $asOf;

    public function __construct(public ?School $branch)
    {
        $this->asOf = CarbonImmutable::now(config('app.timezone'));
    }
}
