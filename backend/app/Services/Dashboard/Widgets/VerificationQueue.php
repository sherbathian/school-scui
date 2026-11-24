<?php

namespace App\Services\Dashboard\Widgets;

use App\Models\School;
use App\Services\Dashboard\DashboardContext;
use App\Services\Dashboard\Widget;
use Carbon\CarbonImmutable;

final class VerificationQueue implements Widget
{
    public function definition(): array
    {
        return [
            'key' => 'platform.verification_queue', 'title' => 'Listings awaiting verification',
            'type' => 'list', 'size' => 'md', 'scope' => 'platform',
            'requiredModule' => null, 'requiredPermission' => 'moderateListings',
        ];
    }

    public function data(DashboardContext $context): array
    {
        $rows = School::query()->whereNull('verified_at')->where('status', 'active')
            ->whereIn('listing_status', ['draft', 'pending_review'])
            ->orderBy('created_at')->orderBy('id')->limit(10)->get(['name', 'created_at'])
            ->map(function (School $school) use ($context): array {
                $created = $school->getRawOriginal('created_at');
                $days = $created ? max(0, (int) CarbonImmutable::parse($created, $context->asOf->timezone)->diffInDays($context->asOf)) : null;

                return ['title' => $school->name, 'meta' => $days === null ? 'Age unavailable' : $days.($days === 1 ? ' day' : ' days')];
            });

        return [
            'summary' => 'Oldest active listings awaiting verification.',
            'data' => ['rows' => $rows, 'empty' => 'No listings awaiting verification.'],
        ];
    }
}
