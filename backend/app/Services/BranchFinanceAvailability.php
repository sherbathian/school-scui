<?php

namespace App\Services;

class BranchFinanceAvailability
{
    public function allows(int $school, bool $writing = false): bool
    {
        $pilot = config('billing.receipt_pilot');
        if (app()->environment('testing') && ($pilot['branch_id'] ?? null) === $school
            && ($pilot['currency'] ?? null) === 'PKR') {
            return true;
        }

        return $this->operational($school)
            && (! $writing || config('finance_reporting.operational.writes_enabled') === true);
    }

    private function operational(int $school): bool
    {
        $settings = config('finance_reporting.operational');

        return app()->environment('production') && $school > 0
            && ($settings['enabled'] ?? false) === true
            && in_array($settings['branch_id'] ?? null, [$school, (string) $school], true)
            && ($settings['currency'] ?? null) === 'PKR';
    }
}
