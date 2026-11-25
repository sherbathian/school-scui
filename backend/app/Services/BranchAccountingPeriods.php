<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchAccountingPeriods
{
    /** Internal entry points require a caller authorised for the branch. */
    public function configureTimezone(int $branch, string $timezone): void
    {
        $this->validateTimezone($timezone);
        DB::transaction(function () use ($branch, $timezone): void {
            $this->lockBranch($branch);
            $previous = $this->storedTimezone($branch);
            if ($previous === $timezone) {
                return;
            }
            if (DB::table('branch_accounting_periods')->where('school_id', $branch)->lockForUpdate()->first(['id'])) {
                throw ValidationException::withMessages(['timezone' => 'The timezone is fixed once the first period opens.']);
            }

            DB::table('branch_accounting_settings')->updateOrInsert(['school_id' => $branch], ['timezone' => $timezone]);
        }, 3);
    }

    public function open(int $actor, int $branch, string $month): int
    {
        if (! preg_match('/^[1-9][0-9]{3}-(0[1-9]|1[0-2])$/D', $month)) {
            throw ValidationException::withMessages(['month' => 'Provide a month in YYYY-MM format.']);
        }

        return DB::transaction(function () use ($actor, $branch, $month): int {
            $context = $this->context($branch);
            $latest = DB::table('branch_accounting_periods')->where('school_id', $branch)
                ->orderByDesc('month')->lockForUpdate()->first();
            if ($latest?->month === $month && $latest->open_marker === 'open') {
                return (int) $latest->id;
            }
            if ($latest && ($latest->open_marker === 'open' || $month <= $latest->month)) {
                throw ValidationException::withMessages(['period' => 'Close the open period before opening a newer month.']);
            }

            $firstDay = CarbonImmutable::parse($month.'-01', 'UTC');
            $period = [
                'school_id' => $branch, 'month' => $month,
                'starts_on' => $firstDay->toDateString(),
                'ends_on' => $firstDay->endOfMonth()->toDateString(),
            ];
            $id = DB::table('branch_accounting_periods')->insertGetId($period + ['open_marker' => 'open']);
            $this->record($actor, $id, $period, $context, 'period.opened', null, 'open');

            return $id;
        }, 3);
    }

    public function close(int $actor, int $branch, int $id): void
    {
        DB::transaction(function () use ($actor, $branch, $id): void {
            $context = $this->context($branch);
            $period = DB::table('branch_accounting_periods')->where('school_id', $branch)
                ->where('id', $id)->lockForUpdate()->first();
            if ($period === null) {
                throw ValidationException::withMessages(['period' => 'The accounting period is unavailable.']);
            }
            if ($period->open_marker === null) {
                return;
            }
            if ($context['accounting_date'] < $period->ends_on) {
                throw ValidationException::withMessages(['period' => 'Close the period on its last local day or later.']);
            }

            DB::table('branch_accounting_periods')->where('school_id', $branch)->where('id', $id)->update(['open_marker' => null]);
            $this->record($actor, $id, [
                'school_id' => $branch, 'month' => $period->month,
                'starts_on' => $period->starts_on, 'ends_on' => $period->ends_on,
            ], $context, 'period.closed', 'open', 'closed');
        }, 3);
    }

    /** A writer must call inside its own transaction to retain the branch lock through commit. */
    public function current(int $branch): array
    {
        return DB::transaction(function () use ($branch): array {
            $context = $this->context($branch);
            $day = $context['accounting_date'];
            $period = DB::table('branch_accounting_periods')->where('school_id', $branch)
                ->where('open_marker', 'open')->where('starts_on', '<=', $day)->where('ends_on', '>=', $day)
                ->lockForUpdate()->first(['id', 'starts_on', 'ends_on']);
            if ($period === null) {
                throw ValidationException::withMessages(['period' => 'No open period covers the current business date.']);
            }

            return $context + [
                'period_id' => (int) $period->id, 'starts_on' => $period->starts_on, 'ends_on' => $period->ends_on,
            ];
        }, 3);
    }

    private function context(int $branch): array
    {
        $this->lockBranch($branch);
        $timezone = $this->storedTimezone($branch);
        $this->validateTimezone($timezone);
        $instant = now('UTC');

        return [
            'timezone' => $timezone,
            'accounting_date' => $instant->copy()->setTimezone($timezone)->toDateString(),
            'recorded_at_utc' => $instant->toDateTimeString(),
        ];
    }

    /** Acquire inside the caller's transaction, before operation keys or charge locks. */
    public function lockBranch(int $branch): void
    {
        if (! DB::table('schools')->where('id', $branch)->whereNull('deleted_at')->lockForUpdate()->first(['id'])) {
            throw ValidationException::withMessages(['branch' => 'The branch is unavailable.']);
        }
    }

    private function storedTimezone(int $branch): ?string
    {
        return DB::table('branch_accounting_settings')->where('school_id', $branch)->lockForUpdate()->value('timezone');
    }

    private function validateTimezone(?string $timezone): void
    {
        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw ValidationException::withMessages(['timezone' => 'Configure a valid IANA branch timezone.']);
        }
    }

    private function record(int $actor, int $id, array $period, array $context, string $action, ?string $from, string $to): void
    {
        DB::table('branch_accounting_period_events')->insert($period + [
            'actor_id' => $actor, 'period_id' => $id, 'action' => $action,
            'from_state' => $from, 'to_state' => $to, 'timezone' => $context['timezone'],
            'business_date' => $context['accounting_date'], 'recorded_at_utc' => $context['recorded_at_utc'],
        ]);
    }
}
