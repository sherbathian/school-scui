<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\BranchAccountingPeriods;
use App\Services\PostFamilyReceipt;
use App\Services\ReverseFamilyReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PostFamilyReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-25 12:00:00', 'UTC'));
    }

    public function test_receipt_posting_replays_and_enforces_remaining_balance(): void
    {
        $school = School::factory()->create()->id;
        $actor = User::factory()->create()->id;
        $this->openPeriod($actor, $school);
        $charge = $this->charge($school);
        $post = app(PostFamilyReceipt::class);
        $first = $post->post($actor, $school, 'PKR', 'payment-1', '2026-11-25', 60000, [$charge => 60000]);
        $this->assertSame($first, $post->post($actor, $school, 'PKR', 'payment-1', '2026-11-25', 60000, [$charge => 60000]));

        foreach ([['payment-1', 40000], ['payment-2', 50000]] as [$key, $amount]) {
            try {
                $post->post($actor, $school, 'PKR', $key, '2026-11-25', $amount, [$charge => $amount]);
                $this->fail('Invalid posting succeeded.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }

        $post->post($actor, $school, 'PKR', 'payment-2', '2026-11-25', 40000, [$charge => 40000]);
        $this->assertDatabaseCount('posted_family_receipts', 2);
        $this->assertDatabaseCount('family_receipt_posting_keys', 2);
        $this->assertDatabaseCount('family_receipt_finance_events', 2);
        $this->assertSame(100000, (int) DB::table('posted_family_receipt_allocations')->sum('amount_minor'));
        $final = DB::table('family_receipt_finance_events')->latest('id')->value('balances');
        $balances = json_decode($final, true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(1, $balances);
        ksort($balances[0]);
        $this->assertSame(['after_minor' => 0, 'before_minor' => 40000, 'charge_id' => $charge], $balances[0]);
    }

    public function test_source_receipt_can_only_be_posted_once_and_must_belong_to_the_branch(): void
    {
        $school = School::factory()->create()->id;
        $other = School::factory()->create()->id;
        $actor = User::factory()->create()->id;
        $this->openPeriod($actor, $school);
        $charge = $this->charge($school);
        $foreignCharge = $this->charge($other);
        $source = fn (int $branch): int => DB::table('family_fee_receipts')->insertGetId([
            'school_id' => $branch, 'receipt_number' => 'Synthetic', 'receipt_date' => '2026-11-25', 'received' => '200.00',
        ]);
        $local = $source($school);
        $foreign = $source($other);
        $post = app(PostFamilyReceipt::class);

        foreach ([
            ['PKR', $charge, 20000, $foreign, 'source', 422],
            ['PKR', $foreignCharge, 20000, $local, 'allocations', 422],
            ['PKR', $charge, 20001, $local, 'source', 422],
            ['USD', $charge, 20000, $local, 'receipt', 422],
        ] as [$currency, $id, $amount, $import, $field, $status]) {
            try {
                $post->post($actor, $school, $currency, 'attempt', '2026-11-25', $amount, [$id => $amount], $import);
                $this->fail('Invalid posting succeeded.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
                $this->assertSame($status, $exception->status);
            }
        }
        $post->post($actor, $school, 'PKR', 'attempt', '2026-11-25', 20000, [$charge => 20000], $local);
        try {
            $post->post($actor, $school, 'PKR', 'another-key', '2026-11-25', 20000, [$charge => 20000], $local);
            $this->fail('Duplicate import succeeded.');
        } catch (ValidationException $exception) {
            $this->assertSame(409, $exception->status);
        }
        $this->assertDatabaseCount('posted_family_receipts', 1);
        $this->assertDatabaseCount('family_receipt_posting_keys', 1);
        $this->assertDatabaseCount('family_receipt_finance_events', 1);
    }

    public function test_audit_write_failure_rolls_back_the_posting_and_leaves_key_reusable(): void
    {
        $school = School::factory()->create()->id;
        $actor = User::factory()->create()->id;
        $this->openPeriod($actor, $school);
        $charge = $this->charge($school);
        $secondCharge = $this->charge($school, ' 2');
        $rejectAudit = true;
        DB::connection()->beforeExecuting(function (string $query) use (&$rejectAudit): void {
            if ($rejectAudit && str_contains($query, 'insert into') && str_contains($query, 'family_receipt_finance_events')) {
                throw new \RuntimeException('Synthetic audit failure');
            }
        });

        $post = app(PostFamilyReceipt::class);
        try {
            $post->post($actor, $school, 'PKR', 'key', '2026-11-25', 10000, [$secondCharge => 4000, $charge => 6000]);
            $this->fail('Posting survived audit write failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic audit failure', $exception->getMessage());
        }
        foreach (['posted_family_receipts', 'posted_family_receipt_allocations', 'family_receipt_posting_keys', 'family_receipt_finance_events'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $rejectAudit = false;
        $result = $post->post($actor, $school, 'PKR', 'key', '2026-11-25', 10000, [$charge => 6000, $secondCharge => 4000]);
        $this->assertSame($result, $post->post($actor, $school, 'PKR', 'key', '2026-11-25', 10000, [$secondCharge => 4000, $charge => 6000]));
        $this->assertDatabaseCount('posted_family_receipt_allocations', 2);
        $this->assertDatabaseCount('family_receipt_finance_events', 1);
    }

    public function test_full_reversal_restores_split_balances_once_and_preserves_originals(): void
    {
        $school = School::factory()->create()->id;
        $foreign = School::factory()->create()->id;
        $actor = User::factory()->create()->id;
        $this->openPeriod($actor, $school);
        $first = $this->charge($school);
        $second = $this->charge($school, ' 2');
        $post = app(PostFamilyReceipt::class);
        $reverse = app(ReverseFamilyReceipt::class);
        $original = $post->post($actor, $school, 'PKR', 'original', '2026-11-25', 60000, [$second => 20000, $first => 40000]);
        $id = $original['receipt_id'];
        $this->openPeriod($actor, $foreign);

        foreach ([
            [$foreign, 'reverse', 'duplicate', 'receipt', 422],
        ] as [$branch, $key, $reason, $field, $status]) {
            try {
                $reverse->reverse($actor, $branch, $id, $key, $reason);
                $this->fail('Invalid reversal succeeded.');
            } catch (ValidationException $exception) {
                $this->assertSame($status, $exception->status);
                $this->assertArrayHasKey($field, $exception->errors());
            }
        }
        $rejectAudit = true;
        DB::connection()->beforeExecuting(function (string $query) use (&$rejectAudit): void {
            if ($rejectAudit && str_contains($query, 'insert into') && str_contains($query, 'family_receipt_finance_events')) {
                throw new \RuntimeException('Synthetic reversal audit failure');
            }
        });
        try {
            $reverse->reverse($actor, $school, $id, 'reverse', 'duplicate');
            $this->fail('Reversal survived audit failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic reversal audit failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('family_receipt_reversals', 0);
        $this->assertDatabaseCount('family_receipt_reversal_keys', 0);
        $this->assertDatabaseCount('family_receipt_finance_events', 1);
        $rejectAudit = false;

        $result = $reverse->reverse($actor, $school, $id, 'reverse', 'duplicate');
        $this->assertSame($result, $reverse->reverse($actor, $school, $id, 'reverse', 'duplicate'));
        foreach ([['reverse', 'entry_error', 'key'], ['other', 'duplicate', 'receipt']] as [$key, $reason, $field]) {
            try {
                $reverse->reverse($actor, $school, $id, $key, $reason);
                $this->fail('Repeated reversal succeeded.');
            } catch (ValidationException $exception) {
                $this->assertSame(409, $exception->status);
                $this->assertArrayHasKey($field, $exception->errors());
            }
        }

        $this->assertDatabaseHas('posted_family_receipts', ['id' => $id, 'amount_minor' => 60000, 'received_on' => '2026-11-25']);
        $this->assertDatabaseCount('posted_family_receipt_allocations', 2);
        $this->assertDatabaseCount('family_receipt_reversals', 1);
        $this->assertDatabaseCount('family_receipt_reversal_keys', 1);
        $event = DB::table('family_receipt_finance_events')->where('action', 'receipt.reversed')->first();
        $this->assertSame($result['reversal_id'], (int) $event->reversal_id);
        $lines = json_decode($event->balances, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([60000, 80000], array_column($lines, 'before_minor'));
        $this->assertSame([100000, 100000], array_column($lines, 'after_minor'));
        $post->post($actor, $school, 'PKR', 'after', '2026-11-25', 200000, [$first => 100000, $second => 100000]);
        $this->assertDatabaseCount('family_receipt_finance_events', 3);
    }

    public function test_new_posting_requires_configured_accounting_period(): void
    {
        $school = School::factory()->create()->id;
        $actor = User::factory()->create()->id;
        $charge = $this->charge($school);
        $this->expectException(ValidationException::class);
        app(PostFamilyReceipt::class)->post($actor, $school, 'PKR', 'blocked', '2026-11-25', 10000, [$charge => 10000]);
    }

    public function test_closure_allows_replays_and_later_period_reverses_the_original_receipt(): void
    {
        $school = School::factory()->create()->id;
        $actor = User::factory()->create()->id;
        $november = $this->openPeriod($actor, $school);
        $charge = $this->charge($school);
        $post = app(PostFamilyReceipt::class);
        $reverse = app(ReverseFamilyReceipt::class);
        $calendar = app(BranchAccountingPeriods::class);
        $this->travelTo(Carbon::parse('2026-11-30 18:59:59', 'UTC'));
        $receipt = $post->post($actor, $school, 'PKR', 'original', '2026-11-30', 10000, [$charge => 10000]);
        $calendar->close($actor, $school, $november);
        $this->assertSame($receipt, $post->post($actor, $school, 'PKR', 'original', '2026-11-30', 10000, [$charge => 10000]));

        foreach ([
            fn () => $post->post($actor, $school, 'PKR', 'new', '2026-11-30', 10000, [$charge => 10000]),
            fn () => $reverse->reverse($actor, $school, $receipt['receipt_id'], 'reverse', 'duplicate'),
        ] as $operation) {
            $this->rejected('period', $operation);
        }
        $this->assertDatabaseCount('family_receipt_posting_keys', 1);
        $this->assertDatabaseCount('family_receipt_reversal_keys', 0);
        $this->assertDatabaseCount('family_receipt_finance_events', 1);

        $december = $calendar->open($actor, $school, '2026-12');
        $this->travel(1)->second();
        $this->rejected('received_on', fn () => $post->post($actor, $school, 'PKR', 'new', '2026-12-02', 10000, [$charge => 10000]));
        $this->rejected('received_on', fn () => $post->post($actor, $school, 'PKR', 'new', '2026-11-30', 10000, [$charge => 10000]));
        $source = DB::table('family_fee_receipts')->insertGetId([
            'school_id' => $school, 'receipt_number' => 'Synthetic earlier receipt', 'receipt_date' => '2026-11-30', 'received' => '100.00',
        ]);
        $late = $post->post($actor, $school, 'PKR', 'new', '2026-11-30', 10000, [$charge => 10000], $source);
        $reversal = $reverse->reverse($actor, $school, $receipt['receipt_id'], 'reverse', 'duplicate');

        $this->assertDatabaseHas('posted_family_receipts', ['id' => $receipt['receipt_id'], 'period_id' => $november, 'accounting_date' => '2026-11-30']);
        $this->assertDatabaseHas('posted_family_receipts', [
            'id' => $late['receipt_id'], 'received_on' => '2026-11-30', 'period_id' => $december,
            'accounting_date' => '2026-12-01', 'accounting_timezone' => 'Asia/Karachi', 'accounting_recorded_at_utc' => '2026-11-30 19:00:00',
        ]);
        $this->assertDatabaseHas('family_receipt_reversals', ['id' => $reversal['reversal_id'], 'period_id' => $december, 'accounting_date' => '2026-12-01']);
        $this->assertDatabaseHas('family_receipt_finance_events', ['reversal_id' => $reversal['reversal_id'], 'period_id' => $december, 'accounting_date' => '2026-12-01']);
        $this->travelTo(Carbon::parse('2026-12-31 12:00:00', 'UTC'));
        $calendar->close($actor, $school, $december);
        $this->assertSame($reversal, $reverse->reverse($actor, $school, $receipt['receipt_id'], 'reverse', 'duplicate'));
        $this->assertDatabaseCount('family_receipt_finance_events', 3);
    }

    public function test_pre_integration_records_are_not_assigned_a_period_during_replay(): void
    {
        $school = School::factory()->create()->id;
        $actor = User::factory()->create()->id;
        $this->openPeriod($actor, $school);
        $charge = $this->charge($school);
        $post = app(PostFamilyReceipt::class);
        $reverse = app(ReverseFamilyReceipt::class);
        $receipt = $post->post($actor, $school, 'PKR', 'original', '2026-11-25', 10000, [$charge => 10000]);
        $id = $receipt['receipt_id'];
        DB::table('posted_family_receipts')->where('id', $id)->update(['period_id' => null, 'accounting_date' => null]);
        $this->assertSame($receipt, $post->post($actor, $school, 'PKR', 'original', '2026-11-25', 10000, [$charge => 10000]));
        $this->rejected('period', fn () => $reverse->reverse($actor, $school, $id, 'blocked', 'duplicate'));

        $legacy = DB::table('family_receipt_reversals')->insertGetId([
            'school_id' => $school, 'receipt_id' => $id, 'actor_id' => $actor, 'currency' => 'PKR',
            'amount_minor' => 10000, 'reason_code' => 'duplicate', 'reversed_on' => '2026-11-25', 'recorded_at' => '2026-11-25 12:00:00',
        ]);
        DB::table('family_receipt_reversal_keys')->insert([
            'school_id' => $school, 'reversal_id' => $legacy, 'operation_key' => hash('sha256', 'legacy'),
            'payload_hash' => hash('sha256', json_encode([$id, 'duplicate', '2026-11-25'], JSON_THROW_ON_ERROR)),
        ]);
        $this->assertSame(['reversal_id' => $legacy], $reverse->reverse($actor, $school, $id, 'legacy', 'duplicate'));
        $this->rejected('key', fn () => $reverse->reverse($actor, $school, $id, 'legacy', 'entry_error'));
        $this->assertDatabaseHas('family_receipt_reversals', ['id' => $legacy, 'period_id' => null]);
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

    private function openPeriod(int $actor, int $branch): int
    {
        $calendar = app(BranchAccountingPeriods::class);
        $calendar->configureTimezone($branch, 'Asia/Karachi');

        return $calendar->open($actor, $branch, '2026-11');
    }

    private function charge(int $branch, string $suffix = ''): int
    {
        $school = ['school_id' => $branch];
        $student = Student::factory()->create($school)->id;
        $session = DB::table('school_sessions')->insertGetId($school + [
            'title' => 'Synthetic session'.$suffix, 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30',
        ]);
        $group = DB::table('fee_groups')->insertGetId($school + ['title' => 'Synthetic group'.$suffix]);
        $type = DB::table('fee_types')->insertGetId($school + ['title' => 'Synthetic type'.$suffix]);
        $master = DB::table('fee_masters')->insertGetId($school + [
            'fee_group_id' => $group, 'school_session_id' => $session, 'fee_type_id' => $type,
            'billing_month' => '2026-11', 'due_date' => '2026-11-30', 'amount' => '1000.00',
        ]);

        return DB::table('student_fee_charges')->insertGetId($school + [
            'student_id' => $student, 'school_session_id' => $session, 'fee_master_id' => $master,
            'billing_month' => '2026-11', 'due_date' => '2026-11-30', 'amount' => '1000.00',
        ]);
    }
}
