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

class BranchFinanceReaderRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_reader_rollback_preserves_ledger_and_checks_access(): void
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
        $url = "/api/schools/$branch/finance-reader";
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
        $this->getJson("/api/schools/$outside/finance-reader".$nov)->assertNotFound();
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
        $type = DB::table('legacy_fee_types')->insertGetId(['school_id' => $branch, 'title' => 'Synthetic legacy', 'academic_year' => '2026-2027', 'amount_cents' => 120000]);
        $invoice = DB::table('fee_invoices')->insertGetId([
            'school_id' => $branch, 'student_id' => $student, 'fee_type_id' => $type, 'academic_year' => '2026-2027',
            'description' => 'Synthetic invoice', 'amount_cents' => 120000, 'created_at' => '2026-11-01 09:00:00',
        ]);
        DB::table('fee_payments')->insert(['school_id' => $branch, 'fee_invoice_id' => $invoice, 'amount_cents' => 50000, 'paid_on' => '2026-11-30']);
        DB::table('family_fee_receipts')->insert(['school_id' => $branch, 'receipt_number' => 'Synthetic evidence', 'receipt_date' => '2026-11-30', 'received' => '750.00']);
        DB::table('legacy_student_fee_snapshots')->insert(['school_id' => $branch, 'student_id' => $student, 'billing_month' => '2026-11', 'received_amount' => '750.00']);
        $this->getJson($url.$nov.'&source=v4')->assertOk()
            ->assertJsonPath('data.source', 'legacy')
            ->assertJsonPath('data.legacy.collected_minor', 50000)
            ->assertJsonMissingPath('data.v4');
        config(['finance_reporting.pilot_reader' => 'invalid']);
        $this->getJson($url.$nov)->assertJsonPath('data.source', 'legacy');
        config(['finance_reporting.pilot_reader' => 'v4']);
        $ledgerTables = ['posted_family_receipts', 'family_receipt_reversals', 'family_receipt_finance_events'];
        foreach ($ledgerTables as $table) {
            $this->assertNull(DB::table($table)->where('school_id', $branch)->max('id'));
        }
        $receipt = $post->post($actor->id, $branch, 'PKR', 'split', '2026-11-30', 75000, [$a => 45000, $b => 30000]);
        $post->post($actor->id, $outside, 'PKR', 'outside', '2026-11-30', 99900, [$foreign => 99900]);
        $calendar->close($actor->id, $branch, $period);
        $this->travelTo(Carbon::parse('2026-11-30 19:00:00', 'UTC'));
        $decemberPeriod = $calendar->open($actor->id, $branch, '2026-12');
        $reversal = app(ReverseFamilyReceipt::class)->reverse($actor->id, $branch, $receipt['receipt_id'], 'reverse', 'entry_error');
        $december = $post->post($actor->id, $branch, 'PKR', 'december', '2026-12-01', 10000, [$b => 10000]);

        $this->getJson($url.$nov)->assertOk()
            ->assertJsonPath('data.source', 'v4')
            ->assertJsonMissingPath('data.legacy')
            ->assertJsonPath('data.v4.gross_minor', 75000)
            ->assertJsonPath('data.v4.remaining_minor_at_cutoff', 125000);
        $this->getJson($url.$dec)->assertOk()
            ->assertJsonPath('data.v4.gross_minor', 10000)
            ->assertJsonPath('data.v4.reversal_minor', 75000)
            ->assertJsonPath('data.v4.net_minor', -65000)
            ->assertJsonPath('data.v4.remaining_minor_at_cutoff', 190000);

        $service = app(\App\Services\BranchFinanceReconciliationReport::class);
        $this->mock(\App\Services\BranchFinanceReconciliationReport::class, function ($mock) use ($service, $branch): void {
            $incorrect = $service->forSource($branch, '2026-12', '2026-12-01', 'v4');
            $incorrect['v4']['net_minor']++;
            $mock->shouldReceive('forSource')->with($branch, '2026-12', '2026-12-01', 'v4')->once()->andReturn($incorrect);
            $mock->shouldReceive('forSource')->with($branch, '2026-12', '2026-12-01', 'v4')->once()
                ->andThrow(new \RuntimeException('Synthetic v4 query failure'));
            $mock->shouldNotReceive('forBranch');
            $mock->shouldReceive('forSource')->with($branch, '2026-11', '2026-11-30', 'legacy')
                ->andReturnUsing($service->forSource(...));
        });
        $this->getJson($url.$dec)->assertOk()->assertJsonPath('data.v4.net_minor', -64999);
        $this->getJson($url.$dec)->assertInternalServerError();
        config(['finance_reporting.pilot_reader' => 'legacy']);
        $this->getJson($url.$nov)->assertJsonPath('data.legacy.collected_minor', 50000);
        $this->app->instance(\App\Services\BranchFinanceReconciliationReport::class, $service);
        $this->getJson("/api/schools/$branch/finance-reconciliation".$dec)
            ->assertJsonPath('data.v4.net_minor', -65000);
        $this->assertDatabaseHas('posted_family_receipts', [
            'id' => $receipt['receipt_id'], 'period_id' => $period, 'accounting_date' => '2026-11-30',
        ]);
        $this->assertDatabaseHas('posted_family_receipts', [
            'id' => $december['receipt_id'], 'period_id' => $decemberPeriod, 'accounting_date' => '2026-12-01',
        ]);
        $this->assertDatabaseHas('family_receipt_reversals', [
            'id' => $reversal['reversal_id'], 'period_id' => $decemberPeriod,
            'receipt_id' => $receipt['receipt_id'], 'accounting_date' => '2026-12-01',
        ]);
        $this->assertSame($receipt, $post->post($actor->id, $branch, 'PKR', 'split', '2026-11-30', 75000, [$a => 45000, $b => 30000]));
        $this->assertSame($reversal, app(ReverseFamilyReceipt::class)->reverse(
            $actor->id, $branch, $receipt['receipt_id'], 'reverse', 'entry_error',
        ));
        $this->assertSame($december, $post->post($actor->id, $branch, 'PKR', 'december', '2026-12-01', 10000, [$b => 10000]));
        config(['finance_reporting.pilot_reader' => 'v4']);
        $this->getJson($url.$nov)->assertJsonPath('data.v4.net_minor', 75000);
        $this->getJson($url.$dec)->assertJsonPath('data.v4.net_minor', -65000);
        $this->app->instance('env', 'production');
        try {
            $this->getJson($url.$dec)->assertForbidden();
        } finally {
            $this->app->instance('env', 'testing');
        }
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
