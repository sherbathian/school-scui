<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use App\Services\BranchAccountingPeriods;
use App\Services\SchoolRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BranchFinanceAuditApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_audit_requires_explicit_branch_access_and_paginates_filtered_events(): void
    {
        $branch = School::factory()->create(['status' => 'active']);
        $foreign = School::factory()->create(['status' => 'active']);
        $actor = User::factory()->create(['is_active' => true]);
        $actor->schools()->attach($branch, ['role' => 'admin', 'is_active' => true]);
        app(SchoolRoles::class)->ensureDefaults($branch);
        setPermissionsTeamId($branch->id);
        $actor->assignRole(Role::query()->where('team_id', $branch->id)->where('name', 'admin')->firstOrFail());
        $actor->givePermissionTo(['audit.view', 'fees.receipt.post']);
        setPermissionsTeamId(0);
        $url = "/api/schools/{$branch->id}/finance-audit";
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($actor, 'web')->getJson($url)->assertForbidden();
        setPermissionsTeamId($branch->id);
        $actor->givePermissionTo('fees.audit.view');
        setPermissionsTeamId(0);
        $this->getJson($url)->assertForbidden();
        config(['billing.receipt_pilot' => ['branch_id' => $branch->id, 'currency' => 'PKR']]);
        $this->getJson("/api/schools/{$foreign->id}/finance-audit")->assertNotFound();

        $calendar = app(BranchAccountingPeriods::class);
        $this->travelTo(Carbon::parse('2026-11-30 19:00:00', 'UTC'));
        $branchPeriod = null;
        foreach ([$branch, $foreign] as $school) {
            $calendar->configureTimezone($school->id, 'Asia/Karachi');
            $period = $calendar->open($actor->id, $school->id, '2026-12');
            $receipt = DB::table('posted_family_receipts')->insertGetId([
                'school_id' => $school->id, 'actor_id' => $actor->id,
                'currency' => 'PKR', 'amount_minor' => 10000, 'received_on' => '2026-12-01', 'posted_at' => '2026-11-30 19:00:00',
            ]);
            DB::table('family_receipt_finance_events')->insert([
                'school_id' => $school->id, 'receipt_id' => $receipt, 'actor_id' => $actor->id,
                'action' => 'receipt.posted', 'currency' => 'PKR', 'amount_minor' => 10000,
                'recorded_at' => '2026-11-30 19:00:00', 'period_id' => $period, 'accounting_date' => '2026-12-01',
                'accounting_timezone' => 'Asia/Karachi', 'accounting_recorded_at_utc' => '2026-11-30 19:00:00',
                'balances' => json_encode([['charge_id' => 5, 'before_minor' => 15000, 'after_minor' => 5000, 'private' => 'excluded']]),
                'allocation_ids' => '[3]',
            ]);
            if ($school->is($branch)) {
                $branchPeriod = $period;
                $reversal = DB::table('family_receipt_reversals')->insertGetId([
                    'school_id' => $branch->id, 'actor_id' => $actor->id, 'receipt_id' => $receipt,
                    'currency' => 'PKR', 'amount_minor' => 10000, 'reason_code' => 'duplicate',
                    'recorded_at' => '2026-12-02 00:00:00', 'reversed_on' => '2026-12-02',
                ]);
                DB::table('family_receipt_finance_events')->insert([
                    'school_id' => $branch->id, 'actor_id' => $actor->id, 'receipt_id' => $receipt,
                    'action' => 'receipt.reversed', 'currency' => 'PKR', 'amount_minor' => 10000,
                    'recorded_at' => '2026-12-02 00:00:00', 'reversal_id' => $reversal,
                    'reason_code' => 'duplicate', 'balances' => '[]', 'allocation_ids' => '[]',
                ]);
            }
        }
        $this->travelTo(Carbon::parse('2026-12-31 12:00:00', 'UTC'));
        $calendar->close($actor->id, $branch->id, $branchPeriod);

        $query = '?from=2026-11-30&to=2026-11-30&per_page=1';
        $first = $this->getJson($url.$query)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.action', 'receipt.posted')
            ->assertJsonPath('data.0.accounting_date', '2026-12-01')
            ->assertJsonPath('data.0.balances.0.after_minor', 5000)->assertJsonMissingPath('data.0.balances.0.private');
        $this->getJson($first->json('links.next'))->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'period.opened')->assertJsonPath('data.0.school_id', $branch->id)
            ->assertJsonPath('links.next', null);
        $this->getJson($url.'?action=period.closed')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.to_state', 'closed');
        $this->getJson($url.'?action=receipt.reversed')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.timestamp_basis', 'legacy_recorded_at')->assertJsonPath('data.0.reason_code', 'duplicate');
        $this->getJson($url.'?action=receipt.reversed&from=2026-12-02')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($url.'?from=2026-12-02&to=2026-12-01')->assertUnprocessable();

        $fees = DB::table('modules')->where('slug', 'fees')->value('id');
        DB::table('school_modules')->insert(['school_id' => $branch->id, 'module_id' => $fees, 'is_enabled' => false]);
        $this->getJson($url)->assertForbidden();
        $this->assertDatabaseCount('family_receipt_finance_events', 3);
        $this->assertDatabaseCount('branch_accounting_period_events', 3);
    }
}
