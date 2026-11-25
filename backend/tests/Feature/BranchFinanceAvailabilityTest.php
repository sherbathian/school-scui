<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\SchoolRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BranchFinanceAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_ifs_operational_gate_limits_access_and_can_pause_writes(): void
    {
        $branch = School::factory()->create(['status' => 'active']);
        $other = School::factory()->create(['status' => 'active']);
        $actor = User::factory()->create(['is_active' => true]);
        $ungranted = User::factory()->create(['is_active' => true]);
        foreach ([$branch, $other] as $school) {
            $actor->schools()->attach($school->id, ['role' => 'admin', 'is_active' => true]);
            app(SchoolRoles::class)->ensureDefaults($school);
            setPermissionsTeamId($school->id);
            $actor->givePermissionTo(['fees.receipt.post', 'fees.receipt.reverse', 'fees.period.manage',
                'fees.audit.view', 'fees.reconciliation.view']);
        }
        $ungranted->schools()->attach($branch->id, ['role' => 'admin', 'is_active' => true]);
        setPermissionsTeamId(0);
        $reports = DB::table('modules')->where('slug', 'reports')->value('id');
        DB::table('school_modules')->insert(['school_id' => $branch->id, 'module_id' => $reports, 'is_enabled' => true]);

        $base = "/api/schools/{$branch->id}";
        $periods = "$base/accounting-periods";
        $receipts = "$base/posted-family-receipts";
        $report = "$base/finance-reconciliation?month=2026-11&cutoff=2026-11-25";
        $this->travelTo(Carbon::parse('2026-11-25 12:00:00', 'Asia/Karachi'));
        $this->app->instance('env', 'production');
        $this->actingAs($actor)->getJson($report)->assertForbidden();
        config(['finance_reporting.operational' => [
            'enabled' => true, 'branch_id' => (string) $branch->id, 'currency' => 'USD', 'writes_enabled' => true,
        ]]);
        $this->getJson($report)->assertForbidden();
        config(['finance_reporting.operational.currency' => 'PKR']);
        $this->getJson("/api/schools/{$other->id}/finance-audit")->assertForbidden();
        $this->getJson($report)->assertOk()
            ->assertJsonPath('data.legacy.collectible_minor_at_cutoff', 0)
            ->assertJsonPath('data.v4.remaining_minor_at_cutoff', 0);
        $this->actingAs($ungranted)->getJson($report)->assertForbidden();
        $this->actingAs($actor);
        $controller = app(\App\Http\Controllers\Api\PostedFamilyReceiptController::class);
        $modules = app(\App\Services\BranchModules::class);
        $this->assertSame(['post', 'reverse', 'periods', 'audit', 'reconciliation'],
            $controller->workspaceGrants($actor, $branch, $modules));
        $this->getJson("$base/finance-reader?month=2026-11&cutoff=2026-11-25")->assertOk();
        $this->patchJson("$periods/timezone", ['timezone' => 'Asia/Karachi'])->assertNoContent();
        $period = $this->postJson($periods, ['month' => '2026-11'])->assertOk()->json('data.period_id');
        [$charge] = $this->charge($branch->id, 'IFS');
        $body = ['received_on' => '2026-11-25', 'amount_minor' => 75000,
            'allocations' => [['charge_id' => $charge, 'amount_minor' => 75000]]];
        $postKey = ['Idempotency-Key' => 'ifs-first-receipt'];
        $reverseKey = ['Idempotency-Key' => 'ifs-first-reversal'];
        $receipt = $this->postJson($receipts, $body, $postKey)->assertOk()->json('data.receipt_id');
        $this->postJson($receipts, $body, $postKey)->assertOk()->assertJsonPath('data.receipt_id', $receipt);
        $this->getJson($report)->assertJsonPath('data.v4.remaining_minor_at_cutoff', 25000);
        $reversal = $this->postJson("$receipts/$receipt/reversal", ['reason' => 'entry_error'], $reverseKey)
            ->assertOk()->json('data.reversal_id');
        $this->getJson($report)->assertJsonPath('data.v4.remaining_minor_at_cutoff', 100000);

        config(['finance_reporting.operational.writes_enabled' => false]);
        $this->postJson($receipts, $body, ['Idempotency-Key' => 'paused'])->assertForbidden();
        $this->postJson("$receipts/$receipt/reversal", ['reason' => 'entry_error'], $reverseKey)->assertForbidden();
        $this->postJson("$periods/$period/close")->assertForbidden();
        $this->getJson($periods)->assertOk();
        $this->getJson("$base/finance-audit")->assertOk();
        $this->getJson($report)->assertOk()->assertJsonPath('data.v4.net_minor', 0);
        $this->assertSame(['periods-read-only', 'audit', 'reconciliation'],
            $controller->workspaceGrants($actor, $branch, $modules));
        $this->assertDatabaseHas('family_receipt_reversals', [
            'id' => $reversal, 'receipt_id' => $receipt, 'period_id' => $period,
        ]);
        $this->assertDatabaseCount('posted_family_receipts', 1);
        $this->assertDatabaseCount('family_receipt_finance_events', 2);
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
