<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\BranchAccountingPeriods;
use App\Services\SchoolRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PostedFamilyReceiptApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pilot_receipt_routes_require_explicit_grants_on_each_request(): void
    {
        $branch = School::factory()->create(['status' => 'active']);
        $other = School::factory()->create(['status' => 'active']);
        $user = User::factory()->create(['is_active' => true]);
        $user->schools()->attach($branch, ['role' => 'admin', 'is_active' => true]);
        app(SchoolRoles::class)->ensureDefaults($branch);
        setPermissionsTeamId($branch->id);
        $user->assignRole(Role::query()->where('team_id', $branch->id)->where('name', 'admin')->firstOrFail());
        setPermissionsTeamId(0);

        $url = "/api/schools/{$branch->id}/posted-family-receipts";
        $this->postJson($url)->assertUnauthorized();
        $this->actingAs($user, 'web')->postJson($url)->assertForbidden();

        setPermissionsTeamId($branch->id);
        $user->givePermissionTo('fees.receipt.post');
        setPermissionsTeamId(0);
        $this->postJson($url)->assertForbidden();
        config(['billing.receipt_pilot' => ['branch_id' => $branch->id, 'currency' => 'PKR']]);
        $this->postJson("/api/schools/{$other->id}/posted-family-receipts")->assertNotFound();

        $this->travelTo(Carbon::parse('2026-11-30 12:00:00', 'UTC'));
        $calendar = app(BranchAccountingPeriods::class);
        $calendar->configureTimezone($branch->id, 'Asia/Karachi');
        $period = $calendar->open($user->id, $branch->id, '2026-11');
        $charge = $this->charge($branch->id);
        $data = [
            'received_on' => '2026-11-30', 'amount_minor' => 10000,
            'allocations' => [['charge_id' => $charge, 'amount_minor' => 10000]],
        ];
        $headers = ['Idempotency-Key' => 'synthetic-post'];
        $this->postJson($url, $data + ['accounting_date' => '2026-11-01'], $headers)->assertUnprocessable();
        $this->postJson($url, array_replace($data, ['allocations' => array_merge($data['allocations'], $data['allocations'])]), $headers)->assertUnprocessable();
        $id = $this->postJson($url, $data, $headers)->assertOk()->json('data.receipt_id');
        $reversal = "$url/$id/reversal";
        $reversalHeaders = ['Idempotency-Key' => 'synthetic-reverse'];
        $this->postJson($reversal, ['reason' => 'duplicate'], $reversalHeaders)->assertForbidden();
        setPermissionsTeamId($branch->id);
        $user->givePermissionTo('fees.receipt.reverse');
        setPermissionsTeamId(0);
        $this->postJson($reversal, ['reason' => 'duplicate', 'timezone' => 'UTC'], $reversalHeaders)->assertUnprocessable();
        $result = $this->postJson($reversal, ['reason' => 'duplicate'], $reversalHeaders)->assertOk()->json('data.reversal_id');
        $calendar->close($user->id, $branch->id, $period);
        $this->postJson($url, $data, $headers)->assertOk()->assertJsonPath('data.receipt_id', $id);
        $this->postJson($reversal, ['reason' => 'duplicate'], $reversalHeaders)->assertOk()->assertJsonPath('data.reversal_id', $result);
        $this->postJson($reversal, ['reason' => 'entry_error'], $reversalHeaders)->assertConflict();

        $module = DB::table('modules')->where('slug', 'fees')->value('id');
        DB::table('school_modules')->insert(['school_id' => $branch->id, 'module_id' => $module, 'is_enabled' => false]);
        $this->postJson($url, $data, $headers)->assertForbidden();
        DB::table('school_modules')->where('school_id', $branch->id)->where('module_id', $module)->delete();
        setPermissionsTeamId($branch->id);
        $user->revokePermissionTo('fees.receipt.post');
        setPermissionsTeamId(0);
        $this->postJson($url, $data, $headers)->assertForbidden();
        $this->assertDatabaseCount('posted_family_receipts', 1);
        $this->assertDatabaseCount('family_receipt_reversals', 1);
        $this->assertDatabaseCount('family_receipt_finance_events', 2);
    }

    private function charge(int $branch): int
    {
        $school = ['school_id' => $branch];
        $student = Student::factory()->create($school)->id;
        $session = DB::table('school_sessions')->insertGetId($school + [
            'title' => 'Synthetic session', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30',
        ]);
        $group = DB::table('fee_groups')->insertGetId($school + ['title' => 'Synthetic group']);
        $type = DB::table('fee_types')->insertGetId($school + ['title' => 'Synthetic type']);
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
