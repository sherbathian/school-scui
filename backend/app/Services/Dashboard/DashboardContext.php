<?php

namespace App\Services\Dashboard;

use App\Models\School;
use App\Models\User;
use Carbon\CarbonImmutable;

final readonly class DashboardContext
{
    public CarbonImmutable $asOf;

    public function __construct(public ?School $branch, public User $user)
    {
        $this->asOf = CarbonImmutable::now(config('app.timezone'));
    }
}
