<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BillingReconciliationPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_separates_billing_sources_and_preserves_exact_branch_totals(): void
    {
        $branch = School::factory()->create();
        $other = School::factory()->create();
        foreach ([$branch, $other] as $school) {
            DB::table('family_fee_receipts')->insert([
                ['school_id' => $school->id, 'receipt_number' => 'A', 'receipt_date' => '2026-11-25', 'received' => '3.01'],
                ['school_id' => $school->id, 'receipt_number' => 'B', 'receipt_date' => '2026-11-25', 'received' => null],
                ['school_id' => $school->id, 'receipt_number' => 'C', 'receipt_date' => '2026-11-26', 'received' => '99.00'],
            ]);
        }
        $student = Student::factory()->create(['school_id' => $branch->id]);
        $type = DB::table('legacy_fee_types')->insertGetId([
            'school_id' => $branch->id, 'title' => 'Synthetic fee', 'amount_cents' => 1001, 'academic_year' => '2026-2027',
        ]);
        $invoice = DB::table('fee_invoices')->insertGetId([
            'school_id' => $branch->id, 'student_id' => $student->id, 'fee_type_id' => $type,
            'academic_year' => '2026-2027', 'description' => 'Excluded reference',
            'amount_cents' => 1001, 'created_at' => '2026-11-25 23:59:59',
        ]);
        foreach (['2026-11-25', '2026-11-26'] as $date) {
            DB::table('fee_payments')->insert([
                'school_id' => $branch->id, 'fee_invoice_id' => $invoice, 'paid_on' => $date, 'amount_cents' => 301,
            ]);
        }
        DB::table('legacy_student_fee_snapshots')->insert([
            ['school_id' => $branch->id, 'student_id' => $student->id, 'billing_month' => '2026-10', 'balance_amount' => '10.11'],
            ['school_id' => $branch->id, 'student_id' => $student->id, 'billing_month' => '2026-11', 'balance_amount' => '9.01'],
        ]);

        DB::enableQueryLog();
        $arguments = ['branch' => $branch->id, '--as-of' => '2026-11-25'];
        $this->assertSame(0, Artisan::call('billing:reconcile-preview', $arguments));
        $output = Artisan::output();
        $this->assertSame(0, Artisan::call('billing:reconcile-preview', $arguments));
        $this->assertSame($output, Artisan::output());
        foreach (DB::getQueryLog() as $query) {
            $this->assertStringStartsWith('select ', strtolower($query['query']));
        }
        DB::disableQueryLog();
        $this->assertStringNotContainsString('Excluded reference', $output);

        $result = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(301, $result['legacy_payments']['amount_minor']);
        $this->assertSame(700, $result['legacy_invoices']['collectible_minor']);
        $this->assertSame(301, $result['family_receipts']['received_minor']);
        $this->assertSame(2, $result['family_receipts']['count']);
        $this->assertSame(1, $result['family_receipts']['missing_received_count']);
        $this->assertSame([1011, 901], array_column($result['legacy_snapshots']['by_month'], 'balance_minor'));
        $this->assertArrayNotHasKey('collections_total', $result);
    }

    public function test_preview_flags_void_and_overpaid_invoices_and_uses_each_sources_date_basis(): void
    {
        $branch = School::factory()->create();
        $student = Student::factory()->create(['school_id' => $branch->id]);
        $school = ['school_id' => $branch->id];
        $type = DB::table('legacy_fee_types')->insertGetId($school + [
            'title' => 'Synthetic legacy fee', 'amount_cents' => 200, 'academic_year' => '2026-2027',
        ]);
        $invoice = DB::table('fee_invoices')->insertGetId($school + [
            'student_id' => $student->id, 'fee_type_id' => $type,
            'academic_year' => '2026-2027', 'description' => 'Synthetic legacy fee',
            'amount_cents' => 200, 'status' => 'void', 'created_at' => '2026-11-24 12:00:00',
        ]);
        DB::table('fee_payments')->insert($school + [
            'fee_invoice_id' => $invoice, 'paid_on' => '2026-11-23', 'amount_cents' => 201,
        ]);

        $head = DB::table('expense_heads')->insertGetId($school + ['title' => 'Synthetic head']);
        foreach (['2026-11-25', '2026-11-26'] as $date) {
            DB::table('expenses')->insert($school + [
                'expense_head_id' => $head, 'expense_date' => $date, 'name' => 'Synthetic expense', 'amount' => '0.29',
            ]);
        }
        $session = DB::table('school_sessions')->insertGetId($school + [
            'title' => 'Synthetic session', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30',
        ]);
        $group = DB::table('fee_groups')->insertGetId($school + ['title' => 'Synthetic group']);
        $catalogueType = DB::table('fee_types')->insertGetId($school + ['title' => 'Synthetic monthly fee']);
        foreach (['2026-11', '2026-12'] as $month) {
            $master = DB::table('fee_masters')->insertGetId($school + [
                'fee_group_id' => $group, 'school_session_id' => $session, 'fee_type_id' => $catalogueType,
                'billing_month' => $month, 'due_date' => "$month-30", 'amount' => '0.31',
            ]);
            DB::table('student_fee_charges')->insert($school + [
                'fee_master_id' => $master, 'student_id' => $student->id, 'school_session_id' => $session,
                'billing_month' => $month, 'due_date' => "$month-30", 'amount' => '0.31',
            ]);
        }

        $this->assertSame(0, Artisan::call('billing:reconcile-preview', ['branch' => $branch->id, '--as-of' => '2026-11-25']));
        $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('current status', $result['legacy_invoices']['status_basis']);
        $this->assertSame(0, $result['legacy_invoices']['collectible_minor']);
        $this->assertSame(200, $result['legacy_invoices']['void_gross_minor']);
        $this->assertSame(1, $result['legacy_invoices']['overpaid_invoice_count']);
        $this->assertSame(1, $result['legacy_payments']['predates_invoice_count']);
        $this->assertSame(201, $result['legacy_payments']['on_void_minor']);
        $this->assertSame(29, $result['expenses']['amount_minor']);
        $this->assertSame([['month' => '2026-11', 'count' => 1, 'amount_minor' => 31]], $result['monthly_charges']['by_month']);
        $this->assertNull($result['family_receipts']['received_minor']);
    }

    public function test_evidence_maps_original_obligations_and_previews_family_allocations(): void
    {
        $branch = School::factory()->create();
        $foreign = School::factory()->create();
        $school = ['school_id' => $branch->id];
        $student = Student::factory()->create($school)->id;
        $sibling = Student::factory()->create($school)->id;
        $legacy = DB::table('legacy_fee_types')->insertGetId($school + [
            'title' => 'Legacy', 'academic_year' => '2026-2027', 'amount_cents' => 100000,
        ]);
        $invoice = DB::table('fee_invoices')->insertGetId($school + [
            'student_id' => $student, 'fee_type_id' => $legacy, 'academic_year' => '2026-2027',
            'description' => 'Synthetic', 'amount_cents' => 100000, 'created_at' => '2026-11-01 12:00:00',
        ]);
        DB::table('fee_payments')->insert($school + [
            'fee_invoice_id' => $invoice, 'amount_cents' => 30000, 'paid_on' => '2026-11-20',
        ]);
        $session = DB::table('school_sessions')->insertGetId($school + [
            'title' => 'Synthetic', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30',
        ]);
        $group = DB::table('fee_groups')->insertGetId($school + ['title' => 'Fees']);
        $type = DB::table('fee_types')->insertGetId($school + ['title' => 'Monthly']);
        $master = DB::table('fee_masters')->insertGetId($school + [
            'fee_group_id' => $group, 'school_session_id' => $session, 'fee_type_id' => $type,
            'billing_month' => '2026-11', 'due_date' => '2026-11-30', 'amount' => '1000.00',
        ]);
        $charges = [];
        foreach ([$student, $sibling] as $person) {
            $charges[] = DB::table('student_fee_charges')->insertGetId($school + [
                'student_id' => $person, 'fee_master_id' => $master, 'school_session_id' => $session,
                'billing_month' => '2026-11', 'due_date' => '2026-11-30', 'amount' => '1000.00',
            ]);
        }
        $receipt = DB::table('family_fee_receipts')->insertGetId($school + [
            'receipt_number' => 'Synthetic', 'receipt_date' => '2026-11-20', 'received' => '750.00',
        ]);
        $unresolvedReceipt = DB::table('family_fee_receipts')->insertGetId($school + [
            'receipt_number' => 'Other', 'receipt_date' => '2026-11-20', 'received' => '300.00',
        ]);
        $foreignReceipt = DB::table('family_fee_receipts')->insertGetId([
            'school_id' => $foreign->id, 'receipt_number' => 'Foreign', 'receipt_date' => '2026-11-20',
        ]);
        $link = [
            'invoice_id' => $invoice, 'charge_id' => $charges[0], 'billing_month' => '2026-11',
            'legacy_fee_type_id' => $legacy, 'v4_fee_type_id' => $type, 'invoice_currency' => 'PKR', 'charge_currency' => 'PKR',
        ];
        $evidence = ['invoice_links' => [$link], 'receipt_allocations' => [[
            'receipt_id' => $receipt, 'allocations' => [
                ['charge_id' => $charges[0], 'amount_minor' => 45000],
                ['charge_id' => $charges[1], 'amount_minor' => 30000],
            ],
        ], ['receipt_id' => $foreignReceipt, 'allocations' => []]]];

        $path = tempnam(sys_get_temp_dir(), 'billing-evidence-');
        try {
            file_put_contents($path, json_encode($evidence, JSON_THROW_ON_ERROR));
            $args = ['branch' => $branch->id, '--as-of' => '2026-11-25'];
            $this->assertSame(0, Artisan::call('billing:reconcile-preview', $args));
            $original = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
            $args['--mapping-evidence'] = $path;
            DB::enableQueryLog();
            $this->assertSame(0, Artisan::call('billing:reconcile-preview', $args));
            $output = Artisan::output();
            $this->assertSame(0, Artisan::call('billing:reconcile-preview', $args));
            $this->assertSame($output, Artisan::output());
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            foreach ($queries as $query) {
                $this->assertStringStartsWith('select ', strtolower($query['query']));
            }
            $result = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
            $mapping = $result['mapping'];
            unset($result['mapping']);
            $this->assertSame($original, $result);
            $this->assertSame(70000, $result['legacy_invoices']['collectible_minor']);
            $this->assertSame(100000, $mapping['invoices']['confirmed']['amount_minor']);
            $this->assertSame([$charges[1]], array_column($mapping['charges']['unresolved']['records'], 'charge_id'));
            $this->assertSame(75000, $mapping['receipts']['allocatable']['amount_minor']);
            $this->assertSame([$unresolvedReceipt], array_column($mapping['receipts']['unresolved']['records'], 'receipt_id'));

            foreach ([
                ['ambiguous_evidence', [$link, $link]],
                ['different_student', [array_replace($link, ['charge_id' => $charges[1]])]],
                ['period_mismatch', [array_replace($link, ['billing_month' => '2026-10'])]],
                ['charge_unavailable', [array_replace($link, ['charge_id' => 999999])]],
                ['fee_type_mismatch', [array_replace($link, ['v4_fee_type_id' => 999999])]],
                ['currency_mismatch', [array_replace($link, ['charge_currency' => 'USD'])]],
                ['missing_evidence', []],
            ] as [$reason, $links]) {
                file_put_contents($path, json_encode(['invoice_links' => $links], JSON_THROW_ON_ERROR));
                $this->assertSame(0, Artisan::call('billing:reconcile-preview', $args));
                $mapped = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['mapping'];
                $this->assertSame($reason, $mapped['invoices']['unresolved']['records'][0]['reason']);
                $this->assertSame(0, $mapped['invoices']['confirmed']['count']);
            }

            DB::table('student_fee_charges')->where('id', $charges[0])->update(['amount' => '999.99']);
            file_put_contents($path, json_encode($evidence, JSON_THROW_ON_ERROR));
            $this->assertSame(0, Artisan::call('billing:reconcile-preview', $args));
            $mapped = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['mapping'];
            $this->assertSame('amount_mismatch', $mapped['invoices']['unresolved']['records'][0]['reason']);

            DB::table('fee_invoices')->where('id', $invoice)->update(['status' => 'void']);
            file_put_contents($path, json_encode($evidence, JSON_THROW_ON_ERROR));
            $this->assertSame(0, Artisan::call('billing:reconcile-preview', $args));
            $mapped = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['mapping'];
            $this->assertSame('void_invoice', $mapped['invoices']['excluded']['records'][0]['reason']);
        } finally {
            unlink($path);
        }
    }

    public function test_preview_rejects_invalid_dates_and_unknown_branches(): void
    {
        $this->artisan('billing:reconcile-preview', ['branch' => '1', '--as-of' => '2026-02-30'])
            ->expectsOutputToContain('YYYY-MM-DD')->assertFailed();
        $this->artisan('billing:reconcile-preview', ['branch' => '999999', '--as-of' => '2026-11-25'])
            ->expectsOutputToContain('Branch not found')->assertFailed();
    }
}
