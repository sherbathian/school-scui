<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\PostFamilyReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PostFamilyReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_posting_replays_and_enforces_remaining_balance(): void
    {
        $school = School::factory()->create()->id;
        $actor = User::factory()->create()->id;
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
        $this->assertSame([['charge_id' => $charge, 'before_minor' => 40000, 'after_minor' => 0]], json_decode($final, true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_source_receipt_can_only_be_posted_once_and_must_belong_to_the_branch(): void
    {
        $school = School::factory()->create()->id;
        $other = School::factory()->create()->id;
        $actor = User::factory()->create()->id;
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
