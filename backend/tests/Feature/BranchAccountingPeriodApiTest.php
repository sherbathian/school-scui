<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use App\Services\SchoolRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BranchAccountingPeriodApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_calendar_requires_an_explicit_grant_and_audits_transitions_once(): void
    {
        $branch = School::factory()->create(['status' => 'active']);
        $other = School::factory()->create(['status' => 'active']);
        $user = User::factory()->create(['is_active' => true]);
        $user->schools()->attach($branch, ['role' => 'admin', 'is_active' => true]);
        app(SchoolRoles::class)->ensureDefaults($branch);
        setPermissionsTeamId($branch->id);
        $user->assignRole(Role::query()->where('team_id', $branch->id)->where('name', 'admin')->firstOrFail());
        setPermissionsTeamId(0);

        $url = "/api/schools/{$branch->id}/accounting-periods";
        $grant = 'fees.period.manage';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($user, 'web')->getJson($url)->assertForbidden();
        $this->patchJson("$url/timezone", ['timezone' => 'Asia/Karachi'])->assertForbidden();
        setPermissionsTeamId($branch->id);
        $user->givePermissionTo($grant);
        setPermissionsTeamId(0);
        $this->getJson($url)->assertForbidden();
        config(['billing.receipt_pilot' => ['branch_id' => $branch->id, 'currency' => 'PKR']]);
        $this->getJson("/api/schools/{$other->id}/accounting-periods")->assertNotFound();
        $this->assertDatabaseCount('branch_accounting_settings', 0);

        $this->travelTo(Carbon::parse('2026-11-29 12:00:00', 'UTC'));
        $this->getJson($url)->assertOk()->assertJsonPath('data.timezone', null)->assertJsonCount(0, 'data.periods');
        $this->postJson($url, ['month' => '2026-11'])->assertUnprocessable();
        $this->patchJson("$url/timezone", ['timezone' => 'Bad/Zone'])->assertUnprocessable();
        $this->patchJson("$url/timezone", ['timezone' => 'Asia/Karachi'])->assertNoContent();
        $id = $this->postJson($url, ['month' => '2026-11'])->assertOk()->json('data.period_id');
        $this->postJson($url, ['month' => '2026-11'])->assertOk()->assertJsonPath('data.period_id', $id);
        $this->patchJson("$url/timezone", ['timezone' => 'UTC'])->assertUnprocessable();
        $this->postJson("$url/$id/close")->assertUnprocessable();
        $this->assertDatabaseCount('branch_accounting_period_events', 1);

        $fees = DB::table('modules')->where('slug', 'fees')->value('id');
        DB::table('school_modules')->insert(['school_id' => $branch->id, 'module_id' => $fees, 'is_enabled' => false]);
        $this->getJson($url)->assertForbidden();
        $this->postJson("$url/$id/close")->assertForbidden();
        DB::table('school_modules')->where('school_id', $branch->id)->where('module_id', $fees)->delete();

        $this->travelTo(Carbon::parse('2026-11-30 12:00:00', 'UTC'));
        $this->postJson("$url/$id/close")->assertNoContent();
        $this->postJson("$url/$id/close")->assertNoContent();
        $this->postJson($url, ['month' => '2026-11'])->assertUnprocessable();
        $this->postJson($url, ['month' => '2026-12'])->assertOk();
        $this->getJson($url)->assertOk()
            ->assertJsonPath('data.timezone', 'Asia/Karachi')
            ->assertJsonPath('data.periods.0.status', 'open')
            ->assertJsonPath('data.periods.0.month', '2026-12')
            ->assertJsonPath('data.periods.1.status', 'closed');
        $this->assertDatabaseCount('branch_accounting_period_events', 3);
    }
}
