<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use App\Services\BranchAccountingPeriods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BranchAccountingPeriodsTest extends TestCase
{
    use RefreshDatabase;

    public function test_period_uses_branch_midnight_and_audits_each_transition_once(): void
    {
        $branch = School::factory()->create()->id;
        $actor = User::factory()->create()->id;
        $calendar = app(BranchAccountingPeriods::class);
        $this->travelTo(Carbon::parse('2026-11-30 18:59:59', 'UTC'));
        $calendar->configureTimezone($branch, 'Asia/Karachi');
        $id = $calendar->open($actor, $branch, '2026-11');
        $this->assertSame($id, $calendar->open($actor, $branch, '2026-11'));
        $this->assertSame('2026-11-30', $calendar->current($branch)['accounting_date']);

        $this->travel(1)->second();
        $this->rejected('period', fn () => $calendar->current($branch));
        $calendar->close($actor, $branch, $id);
        $calendar->close($actor, $branch, $id);
        $december = $calendar->open($actor, $branch, '2026-12');
        $this->assertSame($december, $calendar->current($branch)['period_id']);
        $this->assertSame('2026-12-01', $calendar->current($branch)['accounting_date']);
        $this->assertSame(['period.opened', 'period.closed', 'period.opened'],
            DB::table('branch_accounting_period_events')->orderBy('id')->pluck('action')->all());
    }

    public function test_period_cannot_be_reopened_and_branch_calendar_gaps_fail_closed(): void
    {
        $branch = School::factory()->create()->id;
        $other = School::factory()->create()->id;
        $actor = User::factory()->create()->id;
        $calendar = app(BranchAccountingPeriods::class);
        $this->travelTo(Carbon::parse('2026-11-27 12:00:00', 'UTC'));

        $this->rejected('timezone', fn () => $calendar->open($actor, $branch, '2026-11'));
        $this->rejected('timezone', fn () => $calendar->configureTimezone($branch, 'Invalid/Zone'));
        $calendar->configureTimezone($branch, 'Asia/Karachi');
        $calendar->configureTimezone($other, 'UTC');
        $id = $calendar->open($actor, $branch, '2026-11');
        $this->rejected('period', fn () => $calendar->current($other));
        $this->rejected('period', fn () => $calendar->close($actor, $other, $id));
        $this->rejected('timezone', fn () => $calendar->configureTimezone($branch, 'UTC'));
        $this->rejected('period', fn () => $calendar->close($actor, $branch, $id));
        $this->rejected('period', fn () => $calendar->open($actor, $branch, '2026-12'));

        $this->travelTo(Carbon::parse('2026-11-30 12:00:00', 'UTC'));
        $calendar->close($actor, $branch, $id);
        $this->rejected('period', fn () => $calendar->open($actor, $branch, '2026-11'));
        $this->rejected('period', fn () => $calendar->current($branch));
        $january = $calendar->open($actor, $branch, '2027-01');
        $this->rejected('period', fn () => $calendar->open($actor, $branch, '2026-12'));
        $this->rejected('period', fn () => $calendar->current($branch));

        $this->travelTo(Carbon::parse('2026-12-31 19:00:00', 'UTC'));
        $this->assertSame($january, $calendar->current($branch)['period_id']);
        $this->assertSame('2027-01-01', $calendar->current($branch)['accounting_date']);
        $this->assertDatabaseCount('branch_accounting_period_events', 3);
    }

    public function test_close_is_rolled_back_when_the_audit_write_fails(): void
    {
        $branch = School::factory()->create()->id;
        $actor = User::factory()->create()->id;
        $calendar = app(BranchAccountingPeriods::class);
        $this->travelTo(Carbon::parse('2026-11-30 12:00:00', 'UTC'));
        $calendar->configureTimezone($branch, 'Asia/Karachi');
        $id = $calendar->open($actor, $branch, '2026-11');
        $rejectAudit = true;
        DB::connection()->beforeExecuting(function (string $query) use (&$rejectAudit): void {
            if ($rejectAudit && str_contains($query, 'insert into') && str_contains($query, 'branch_accounting_period_events')) {
                throw new \RuntimeException('Synthetic audit failure');
            }
        });
        try {
            $calendar->close($actor, $branch, $id);
            $this->fail('An unaudited closure succeeded.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic audit failure', $exception->getMessage());
        }
        $this->assertSame($id, $calendar->current($branch)['period_id']);
        $this->assertDatabaseCount('branch_accounting_period_events', 1);
        $rejectAudit = false;
        $calendar->close($actor, $branch, $id);
        $this->assertDatabaseCount('branch_accounting_period_events', 2);
    }

    private function rejected(string $field, callable $operation): void
    {
        try {
            $operation();
            $this->fail("Expected a validation error for {$field}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
