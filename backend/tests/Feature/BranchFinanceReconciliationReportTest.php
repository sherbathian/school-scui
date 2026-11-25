<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\BranchAccountingPeriods;
use App\Services\PostFamilyReceipt;
use App\Services\ReverseFamilyReceipt;
use App\Services\SchoolRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BranchFinanceReconciliationReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_preserves_prior_cutoff_and_checks_permissions_and_modules(): void
    {
        $branch = School::factory()->create(['status' => 'active'])->id;
        $outside = School::factory()->create(['status' => 'active'])->id;
        $actor = User::factory()->create(['is_active' => true]);
        $actor->schools()->attach($branch, ['role' => 'admin', 'is_active' => true]);
        app(SchoolRoles::class)->ensureDefaults(School::findOrFail($branch));
        setPermissionsTeamId($branch);
        $actor->assignRole(\Spatie\Permission\Models\Role::query()->where('team_id', $branch)->where('name', 'admin')->firstOrFail());
        setPermissionsTeamId(0);
        config(['billing.receipt_pilot' => ['branch_id' => $branch, 'currency' => 'PKR']]);
        $url = "/api/schools/$branch/finance-reconciliation";
        $nov = '?month=2026-11&cutoff=2026-11-30';
        $dec = '?month=2026-12&cutoff=2026-12-01';
        $this->getJson($url.$nov)->assertUnauthorized();
        $this->actingAs($actor, 'web')->getJson($url.$nov)->assertForbidden();
        setPermissionsTeamId($branch);
        $actor->givePermissionTo('fees.reconciliation.view');
        setPermissionsTeamId(0);
        $this->getJson($url.$nov)->assertForbidden();
        $reports = DB::table('modules')->where('slug', 'reports')->value('id');
        DB::table('school_modules')->insert(['school_id' => $branch, 'module_id' => $reports, 'is_enabled' => true]);
        $this->getJson("/api/schools/$outside/finance-reconciliation".$nov)->assertNotFound();
        $this->getJson($url.'?month=2026-11&cutoff=2026-12-01')->assertUnprocessable();

        [$a, $student] = $this->charge($branch, 'A');
        [$b] = $this->charge($branch, 'B');
        [$foreign] = $this->charge($outside, 'C');
        $this->travelTo(Carbon::parse('2026-11-30 12:00:00', 'UTC'));
        $calendar = app(BranchAccountingPeriods::class);
        $calendar->configureTimezone($branch, 'Asia/Karachi');
        $calendar->configureTimezone($outside, 'Asia/Karachi');
        $period = $calendar->open($actor->id, $branch, '2026-11');
        $calendar->open($actor->id, $outside, '2026-11');
        $post = app(PostFamilyReceipt::class);
        $receipt = $post->post($actor->id, $branch, 'PKR', 'split', '2026-11-30', 75000, [$a => 45000, $b => 30000]);
        $post->post($actor->id, $outside, 'PKR', 'outside', '2026-11-30', 99900, [$foreign => 99900]);
        $type = DB::table('legacy_fee_types')->insertGetId(['school_id' => $branch, 'title' => 'Synthetic legacy', 'academic_year' => '2026-2027', 'amount_cents' => 120000]);
        $invoice = DB::table('fee_invoices')->insertGetId([
            'school_id' => $branch, 'student_id' => $student, 'fee_type_id' => $type, 'academic_year' => '2026-2027',
            'description' => 'Synthetic invoice', 'amount_cents' => 120000, 'created_at' => '2026-11-01 09:00:00',
        ]);
        DB::table('fee_payments')->insert(['school_id' => $branch, 'fee_invoice_id' => $invoice, 'amount_cents' => 50000, 'paid_on' => '2026-11-30']);
        DB::table('family_fee_receipts')->insert(['school_id' => $branch, 'receipt_number' => 'Synthetic evidence', 'receipt_date' => '2026-11-30', 'received' => '750.00']);
        DB::table('legacy_student_fee_snapshots')->insert(['school_id' => $branch, 'student_id' => $student, 'billing_month' => '2026-11', 'received_amount' => '750.00']);
        $calendar->close($actor->id, $branch, $period);
        $this->travelTo(Carbon::parse('2026-11-30 19:00:00', 'UTC'));
        $calendar->open($actor->id, $branch, '2026-12');
        app(ReverseFamilyReceipt::class)->reverse($actor->id, $branch, $receipt['receipt_id'], 'reverse', 'entry_error');
        $post->post($actor->id, $branch, 'PKR', 'december', '2026-12-01', 10000, [$b => 10000]);

        $this->getJson($url.$nov)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.legacy.collected_minor', 50000)
            ->assertJsonPath('data.legacy.open_invoice_count_current', 1)
            ->assertJsonPath('data.legacy.collectible_minor_at_cutoff', 70000)
            ->assertJsonPath('data.v4.gross_minor', 75000)
            ->assertJsonPath('data.v4.reversal_minor', 0)
            ->assertJsonPath('data.v4.net_minor', 75000)
            ->assertJsonPath('data.v4.remaining_minor_at_cutoff', 125000);
        $this->getJson($url.$dec)->assertOk()->assertJsonPath('data.legacy.collected_minor', 0)
            ->assertJsonPath('data.v4.gross_minor', 10000)
            ->assertJsonPath('data.v4.reversal_minor', 75000)
            ->assertJsonPath('data.v4.net_minor', -65000)
            ->assertJsonPath('data.v4.remaining_minor_at_cutoff', 190000)
            ->assertJsonPath('data.v4.accounting_context_complete', true);
        DB::table('posted_family_receipts')->where('id', $receipt['receipt_id'])->update(['period_id' => null, 'accounting_date' => null]);
        $this->getJson($url.$dec)->assertOk()->assertJsonPath('data.v4.unassigned_receipt_count', 1)
            ->assertJsonPath('data.v4.accounting_context_complete', false);
        $fees = DB::table('modules')->where('slug', 'fees')->value('id');
        DB::table('school_modules')->insert(['school_id' => $branch, 'module_id' => $fees, 'is_enabled' => false]);
        $this->getJson($url.$dec)->assertForbidden();
        $this->assertDatabaseCount('posted_family_receipts', 3);
        $this->assertDatabaseCount('family_receipt_reversals', 1);
        $this->assertDatabaseCount('family_receipt_finance_events', 4);
    }

    private function charge(int $branch, string $suffix): array
    {
        $scope = ['school_id' => $branch];
        $student = Student::factory()->create($scope)->id;
        $session = DB::table('school_sessions')->insertGetId($scope + [
            'title' => 'Session '.$suffix, 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30',
        ]);
        $group = DB::table('fee_groups')->insertGetId($scope + ['title' => 'Group '.$suffix]);
        $type = DB::table('fee_types')->insertGetId($scope + ['title' => 'Type '.$suffix]);
        $master = DB::table('fee_masters')->insertGetId($scope + [
            'fee_group_id' => $group, 'school_session_id' => $session, 'fee_type_id' => $type,
            'billing_month' => '2026-11', 'due_date' => '2026-11-30', 'amount' => '1000.00',
        ]);

        return [DB::table('student_fee_charges')->insertGetId($scope + [
            'student_id' => $student, 'school_session_id' => $session, 'fee_master_id' => $master,
            'billing_month' => '2026-11', 'due_date' => '2026-11-30', 'amount' => '1000.00',
        ]), $student];
    }
}
