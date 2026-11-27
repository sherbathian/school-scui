<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_totals_and_payments_are_consistent_and_branch_lines_are_organisation_scoped(): void
    {
        $this->getJson('/api/admin/services/subscription-invoices')->assertUnauthorized();
        $admin = $this->signIn();
        $this->withoutVite()->get('/admin/services/subscription-invoices')->assertOk();
        $group = DB::table('school_groups')->insertGetId(['name' => 'Example Group', 'slug' => 'example']);
        $branch = School::factory()->create(['group_id' => $group]);
        $foreign = School::factory()->create();
        $root = '/api/admin/services';
        $invoice = $this->postJson("{$root}/subscription-invoices", [
            'school_group_id' => $group, 'invoice_no' => 'EXAMPLE-01', 'period_start' => '2026-11-01',
            'period_end' => '2026-11-30', 'due_date' => '2026-12-01',
        ])->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');
        $line = ['subscription_invoice_id' => $invoice, 'description' => 'Module charge', 'quantity' => 3, 'unit_price' => '33.33'];
        $this->postJson("{$root}/subscription-invoice-lines", [...$line, 'school_id' => $foreign->id])->assertUnprocessable();
        $lineId = $this->postJson("{$root}/subscription-invoice-lines", [...$line, 'school_id' => $branch->id])
            ->assertCreated()->json('data.id');
        $this->assertDatabaseHas('subscription_invoices', ['id' => $invoice, 'subtotal' => 99.99, 'total_amount' => 99.99]);
        $this->patchJson("{$root}/subscription-invoices/{$invoice}", ['status' => 'unpaid', 'discount_total' => '0.99'])->assertOk();
        $this->patchJson("{$root}/subscription-invoice-lines/{$lineId}", ['quantity' => 1])->assertConflict();

        $payment = ['subscription_invoice_id' => $invoice, 'paid_on' => now()->toDateString(), 'method' => 'cash'];
        $this->postJson("{$root}/subscription-payments", [...$payment, 'amount' => '99.01'])->assertUnprocessable();
        $paymentId = $this->postJson("{$root}/subscription-payments", [...$payment, 'amount' => '99.00'])
            ->assertCreated()->assertJsonPath('data.recorded_by_user_id', $admin->id)->json('data.id');
        $this->assertDatabaseHas('subscription_invoices', ['id' => $invoice, 'status' => 'paid', 'total_amount' => 99.00]);
        $this->deleteJson("{$root}/subscription-invoices/{$invoice}")->assertConflict();
        $this->patchJson("{$root}/subscription-payments/{$paymentId}", ['amount' => '98.00'])->assertOk();
        $this->assertDatabaseHas('subscription_invoices', ['id' => $invoice, 'status' => 'unpaid']);
        $this->deleteJson("{$root}/subscription-payments/{$paymentId}")->assertNoContent();
        $this->patchJson("{$root}/subscription-invoices/{$invoice}", ['status' => 'void'])->assertOk();
    }

    public function test_site_configuration_checks_theme_ownership_slugs_and_entitlement(): void
    {
        $this->signIn();
        $first = School::factory()->create(['verified_at' => now()]);
        $second = School::factory()->create();
        $root = '/api/admin/services';
        $theme = $this->postJson("{$root}/site-themes", ['name' => 'Example Theme', 'slug' => 'example-theme', 'version' => '1.0.0'])
            ->assertCreated()->json('data.id');
        $settings = ['school_id' => $first->id, 'site_theme_id' => $theme, 'subdomain' => 'example-school'];
        $this->postJson("{$root}/school-site-settings", [...$settings, 'subdomain' => 'admin'])->assertJsonValidationErrors('subdomain');
        $this->postJson("{$root}/school-site-settings", [...$settings, 'site_enabled' => true])->assertUnprocessable();
        $id = $this->postJson("{$root}/school-site-settings", $settings)->assertCreated()->json('data.id');
        $this->postJson("{$root}/school-site-settings", $settings)->assertConflict();
        DB::table('modules')->insert(['slug' => 'website', 'name' => 'Website']);
        $this->patchJson("{$root}/school-site-settings/{$id}", ['site_enabled' => true])->assertOk();
        $this->deleteJson("{$root}/site-themes/{$theme}")->assertConflict();

        $page = ['school_id' => $first->id, 'title' => 'About', 'slug' => 'about', 'content' => '[{"text":"Example"}]'];
        $id = $this->postJson("{$root}/school-pages", $page)->assertCreated()->json('data.id');
        $this->postJson("{$root}/school-pages", $page)->assertConflict();
        $this->postJson("{$root}/school-pages", [...$page, 'school_id' => $second->id])->assertCreated();
        $this->patchJson("{$root}/school-pages/{$id}", ['content' => 'false'])->assertJsonValidationErrors('content');
        $this->deleteJson("{$root}/school-pages/{$id}")->assertNoContent();
    }

    public function test_school_owners_can_manage_only_their_enabled_branch_websites(): void
    {
        $this->withoutVite();
        $group = DB::table('school_groups')->insertGetId(['name' => 'Owner Group', 'slug' => 'owner-group']);
        $branch = School::factory()->create(['group_id' => $group]);
        $disabled = School::factory()->create(['group_id' => $group]);
        $foreign = School::factory()->create();
        $owner = User::factory()->create(['is_active' => true]);
        $teacher = User::factory()->create(['is_active' => true]);
        $teacher->schools()->attach($branch, ['role' => 'teacher', 'is_active' => true]);
        DB::table('school_group_user')->insert(['school_group_id' => $group, 'user_id' => $owner->id, 'role' => 'owner', 'is_active' => true]);
        $module = DB::table('modules')->insertGetId(['slug' => 'website', 'name' => 'Website']);
        DB::table('school_modules')->insert(['school_id' => $disabled->id, 'module_id' => $module, 'is_enabled' => false]);
        $theme = DB::table('site_themes')->insertGetId(['name' => 'Active Theme', 'slug' => 'active-theme', 'version' => '1', 'is_active' => true]);
        $inactiveTheme = DB::table('site_themes')->insertGetId(['name' => 'Inactive Theme', 'slug' => 'inactive-theme', 'version' => '1', 'is_active' => false]);
        $foreignPage = DB::table('school_pages')->insertGetId(['school_id' => $foreign->id, 'title' => 'Other school', 'slug' => 'other', 'page_type' => 'custom']);
        $root = '/api/admin/services';
        $payload = ['school_id' => $branch->id, 'title' => 'About us', 'slug' => 'about', 'page_type' => 'custom'];

        $this->getJson("{$root}/school-pages")->assertUnauthorized();
        $this->actingAs($owner)->get('/admin/services/school-pages')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Directory')->where('platform', false)->where('canManageSchoolWebsite', true));
        $this->get('/admin/services/school-site-settings')->assertOk();
        $this->getJson("{$root}/school-pages/options")->assertOk()
            ->assertJsonPath('data.schools.0.id', $branch->id)->assertJsonCount(1, 'data.schools');
        $this->getJson("{$root}/school-site-settings/options")->assertOk()
            ->assertJsonPath('data.site_themes.0.id', $theme)->assertJsonCount(1, 'data.site_themes');
        $this->postJson("{$root}/school-pages/preview", $payload)->assertOk();
        $this->postJson("{$root}/school-pages/preview", [...$payload, 'school_id' => $foreign->id])->assertForbidden();
        $this->postJson("{$root}/school-pages/preview", [...$payload, 'school_id' => $disabled->id])->assertForbidden();
        $id = $this->postJson("{$root}/school-pages", $payload)->assertCreated()->json('data.id');
        $this->getJson("{$root}/school-pages")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        $this->patchJson("{$root}/school-pages/{$id}", ['title' => 'About our school', 'is_published' => true])->assertOk();
        $this->patchJson("{$root}/school-pages/{$id}", ['school_id' => $foreign->id])->assertForbidden();
        $this->postJson("{$root}/school-pages", [...$payload, 'school_id' => $disabled->id])->assertForbidden();
        $this->patchJson("{$root}/school-pages/{$foreignPage}", ['title' => 'Wrong school'])->assertNotFound();
        $this->deleteJson("{$root}/school-pages/{$foreignPage}")->assertNotFound();
        $this->deleteJson("{$root}/school-pages/{$id}")->assertNoContent();

        $settings = ['school_id' => $branch->id, 'site_theme_id' => $theme];
        $this->postJson("{$root}/school-site-settings", [...$settings, 'site_theme_id' => $inactiveTheme])->assertJsonValidationErrors('site_theme_id');
        $setting = $this->postJson("{$root}/school-site-settings", $settings)->assertCreated()->json('data.id');
        $this->patchJson("{$root}/school-site-settings/{$setting}", ['primary_color' => '#123456'])->assertOk();
        $this->deleteJson("{$root}/school-site-settings/{$setting}")->assertForbidden();
        $this->getJson("{$root}/site-themes")->assertForbidden();
        $this->actingAs($teacher)->getJson("{$root}/school-pages")->assertForbidden();
        $this->get('/admin/services/school-site-settings')->assertForbidden();

        DB::table('school_modules')->insert(['school_id' => $branch->id, 'module_id' => $module, 'is_enabled' => false]);
        $this->actingAs($owner)->patchJson("{$root}/school-site-settings/{$setting}", ['primary_color' => '#000000'])
            ->assertForbidden();
        $this->assertDatabaseHas('school_site_settings', ['id' => $setting, 'primary_color' => '#123456']);
    }

    public function test_token_metadata_hides_secrets_and_issued_tokens_cannot_access_admin_routes(): void
    {
        $admin = $this->signIn();
        $root = '/api/admin/services';
        $deviceId = $this->postJson("{$root}/device-tokens", ['user_id' => $admin->id, 'token' => 'private-registration', 'platform' => 'ios'])
            ->assertCreated()->assertJsonMissingPath('data.token')->json('data.id');
        $this->getJson("{$root}/device-tokens")->assertJsonMissingPath('data.0.token');
        $this->patchJson("{$root}/device-tokens/{$deviceId}", ['app_version' => '1.2.3'])->assertOk()->assertJsonMissingPath('data.token');
        $this->deleteJson("{$root}/device-tokens/{$deviceId}")->assertNoContent();

        $issued = $this->postJson("{$root}/personal-access-tokens", ['name' => 'Profile client', 'days' => 7])
            ->assertCreated()->assertJsonMissingPath('data.token');
        $secret = $issued->json('issued_token');
        $tokenId = $issued->json('data.id');
        $this->getJson("{$root}/personal-access-tokens")->assertJsonMissingPath('data.0.token');
        $this->patchJson("{$root}/personal-access-tokens/{$tokenId}", ['expires_at' => now()->addDays(8)->toDateTimeString()])
            ->assertJsonValidationErrors('expires_at');
        auth('web')->forgetUser();
        $this->app['auth']->forgetGuards();
        $this->withToken($secret)->getJson('/api/me')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($secret)->getJson("{$root}/personal-access-tokens")->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->actingAs($admin)->deleteJson("{$root}/personal-access-tokens/{$tokenId}")->assertNoContent();
        auth('web')->forgetUser();
        $this->app['auth']->forgetGuards();
        $this->withToken($secret)->getJson('/api/me')->assertUnauthorized();
    }

    private function signIn(): User
    {
        $admin = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);
        $this->actingAs($admin);

        return $admin;
    }
}
