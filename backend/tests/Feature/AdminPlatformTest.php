<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_and_subscription_records_are_admin_only_and_enforce_valid_relationships(): void
    {
        $this->getJson('/api/admin/platform/plans')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/api/admin/platform/plans')->assertForbidden();
        $this->signInAsPlatformAdmin();

        $this->withoutVite()->get('/admin/platform/plans')->assertOk();
        $plan = $this->postJson('/api/admin/platform/plans', ['name' => 'Starter', 'slug' => 'starter', 'is_default' => true])
            ->assertCreated()->json('data.id');
        $replacement = $this->postJson('/api/admin/platform/plans', ['name' => 'Standard', 'slug' => 'standard', 'is_default' => true])
            ->assertCreated()->json('data.id');
        $this->assertDatabaseHas('plans', ['id' => $plan, 'is_default' => false]);
        $this->patchJson("/api/admin/platform/plans/{$replacement}", ['is_active' => false])->assertUnprocessable();

        $students = DB::table('modules')->where('slug', 'students')->value('id');
        $fees = DB::table('modules')->where('slug', 'fees')->value('id');
        $this->postJson('/api/admin/platform/module-dependencies', ['module_id' => $students, 'requires_module_id' => $fees])
            ->assertUnprocessable();
        $this->deleteJson("/api/admin/platform/modules/{$students}")->assertConflict();
        $this->postJson('/api/admin/platform/plan-modules', ['plan_id' => $plan, 'module_id' => $students])
            ->assertCreated()->assertJsonPath('data.id', "{$plan}-{$students}");
        $this->deleteJson("/api/admin/platform/plan-modules/{$plan}-{$students}")->assertNoContent();

        $limit = $this->postJson('/api/admin/platform/plan-limits', ['plan_id' => $plan, 'limit_key' => 'students', 'limit_value' => 80])
            ->assertCreated()->json('data.id');
        $this->patchJson("/api/admin/platform/plan-limits/{$limit}", ['limit_value' => null])
            ->assertOk()->assertJsonPath('data.limit_value', null);
        $this->postJson('/api/admin/platform/plan-limits', ['plan_id' => $plan, 'limit_key' => 'students'])->assertConflict();

        $demo = $this->postJson('/api/admin/platform/demo-requests', ['name' => 'Demo Contact', 'phone' => '03000000000', 'source_site' => 'marketing'])
            ->assertCreated()->json('data.id');
        $this->patchJson("/api/admin/platform/demo-requests/{$demo}", ['status' => 'contacted'])->assertOk();
        $this->deleteJson("/api/admin/platform/demo-requests/{$demo}")->assertNoContent();
    }

    public function test_website_admin_manages_themes_settings_and_branch_pages(): void
    {
        $base = '/api/admin/services';
        $themes = "$base/site-themes";
        $settings = "$base/school-site-settings";
        $pages = "$base/school-pages";
        foreach ([$themes, $settings, $pages] as $path) {
            $this->getJson($path)->assertUnauthorized();
        }
        $school = School::factory()->create(['listing_status' => 'published', 'verified_at' => now()]);
        $other = School::factory()->create();
        $member = User::factory()->create(['is_active' => true]);
        $member->schools()->attach($school, ['role' => 'admin', 'is_active' => true]);
        $this->actingAs($member, 'sanctum');
        foreach ([$themes, $settings, $pages] as $path) {
            $this->getJson($path)->assertForbidden();
            $this->postJson($path, [])->assertForbidden();
        }
        $this->signInAsPlatformAdmin();
        $this->withoutVite()->get('/admin/services/school-pages')->assertOk();
        $theme = $this->postJson($themes, ['name' => 'Synthetic theme', 'slug' => 'synthetic-theme', 'version' => '1.0.0'])
            ->assertCreated()->json('data.id');
        $this->patchJson("$themes/$theme", ['name' => 'Updated theme'])->assertOk()
            ->assertJsonPath('data.name', 'Updated theme');
        $this->getJson($themes)->assertJsonFragment(['name' => 'Updated theme']);
        $this->postJson($settings, ['school_id' => $school->id, 'site_theme_id' => $theme + 1000])
            ->assertUnprocessable()->assertJsonValidationErrors('site_theme_id');
        $site = $this->postJson($settings, ['school_id' => $school->id, 'site_theme_id' => $theme])
            ->assertCreated()->json('data.id');
        $this->getJson("$settings?school_id={$school->id}")->assertJsonCount(1, 'data');
        $this->getJson("$settings?school_id={$other->id}")->assertJsonCount(0, 'data');
        $this->patchJson("$settings/$site", ['primary_color' => '#124F42'])->assertOk()
            ->assertJsonPath('data.primary_color', '#124F42');
        $this->patchJson("$settings/$site", ['site_enabled' => true, 'subdomain' => 'synthetic-school'])
            ->assertUnprocessable();
        $this->deleteJson("$themes/$theme")->assertConflict();

        $details = ['school_id' => $school->id, 'title' => 'Synthetic page', 'slug' => 'welcome'];
        $this->postJson($pages, [...$details, 'school_id' => $other->id + 1000])
            ->assertUnprocessable()->assertJsonValidationErrors('school_id');
        $page = $this->postJson($pages, $details)->assertCreated()->json('data.id');
        $this->assertDatabaseHas('school_pages', ['id' => $page, 'is_published' => false]);
        $this->postJson($pages, $details)->assertConflict();
        $this->postJson($pages, [...$details, 'school_id' => $other->id])->assertCreated();
        $this->getJson("$pages?school_id={$school->id}")->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $page);
        $this->patchJson("$pages/$page", ['is_published' => true, 'title' => 'Updated page'])
            ->assertOk()->assertJsonPath('data.title', 'Updated page');
        $this->assertDatabaseHas('school_pages', ['id' => $page, 'is_published' => true]);
        $this->deleteJson("$pages/$page")->assertNoContent();
        $this->deleteJson("$settings/$site")->assertNoContent();
        $this->deleteJson("$themes/$theme")->assertNoContent();
        $this->assertDatabaseMissing('school_pages', ['id' => $page]);
        $this->assertDatabaseMissing('school_site_settings', ['id' => $site]);
        $this->assertDatabaseMissing('site_themes', ['id' => $theme]);
        $this->assertDatabaseHas('schools', ['id' => $school->id, 'listing_status' => 'published']);
    }

    public function test_school_page_preview_is_available_locally_and_unavailable_in_production(): void
    {
        $school = School::factory()->create(['listing_status' => 'published', 'verified_at' => now()]);
        DB::table('school_pages')->insert([
            'school_id' => $school->id, 'title' => 'Synthetic preview page', 'slug' => 'welcome',
            'is_published' => true,
        ]);
        DB::table('school_pages')->insert([
            'school_id' => $school->id, 'title' => 'Synthetic draft page', 'slug' => 'draft',
            'is_published' => false,
        ]);
        $preview = "/local/school-preview/{$school->slug}/welcome";
        $draft = "/local/school-preview/{$school->slug}/draft";
        $this->app->instance('env', 'local');
        try {
            $this->withoutVite()->get($preview)->assertRedirect();
            $this->actingAs(User::factory()->create(['is_super_admin' => true, 'is_active' => true]), 'web');
            $this->get($preview)->assertOk()->assertSee('Synthetic preview page');
            $this->get($draft)->assertOk()->assertSee('Synthetic draft page')->assertSee('Local preview · Draft');
            $this->app->instance('env', 'production');
            $this->get($preview)->assertNotFound();
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_subscription_entitlements_grants_and_grace_preserve_branch_switches(): void
    {
        $this->signInAsPlatformAdmin();
        $group = $this->group();
        $school = School::factory()->create(['group_id' => $group, 'verified_at' => now()]);
        $plan = $this->postJson('/api/admin/platform/plans', ['name' => 'Standard', 'slug' => 'standard'])->assertCreated()->json('data.id');
        $subscription = $this->postJson('/api/admin/platform/subscriptions', [
            'school_group_id' => $group, 'plan_id' => $plan,
            'starts_at' => now()->subDays(3)->toDateTimeString(), 'ends_at' => now()->addDays(3)->toDateTimeString(),
        ])->assertCreated()->json('data.id');

        $this->getJson("/api/schools/{$school->id}/students")->assertForbidden();
        $ids = DB::table('modules')->pluck('id', 'slug');
        $this->postJson('/api/admin/platform/plan-modules', ['plan_id' => $plan, 'module_id' => $ids['students']])->assertCreated();
        $grant = $this->postJson('/api/admin/platform/subscription-modules', [
            'school_subscription_id' => $subscription, 'module_id' => $ids['fees'],
            'starts_at' => now()->subDay()->toDateTimeString(), 'source' => 'trial',
        ])->assertCreated();
        $this->postJson('/api/admin/platform/subscription-limits', [
            'school_subscription_id' => $subscription, 'limit_key' => 'students', 'limit_value' => 120,
        ])->assertCreated();

        $fees = "/api/schools/{$school->id}/fee-types";
        $switch = "/api/admin/branches/{$school->id}/modules/students";
        $this->getJson($fees)->assertOk();
        $this->patchJson($switch, ['enabled' => false])->assertOk();
        $this->getJson($fees)->assertForbidden();
        $this->deleteJson($switch)->assertOk();
        $this->getJson($fees)->assertOk();
        $this->patchJson("/api/admin/platform/subscription-modules/{$grant->json('data.id')}", [
            'ends_at' => now()->subMinute()->toDateTimeString(),
        ])->assertOk();
        $this->getJson($fees)->assertForbidden();

        $this->patchJson("/api/admin/platform/subscriptions/{$subscription}", [
            'status' => 'grace', 'ends_at' => now()->subDay()->toDateTimeString(), 'grace_ends_at' => now()->addDay()->toDateTimeString(),
        ])->assertOk();
        $this->getJson("/api/schools/{$school->id}/students")->assertOk();
        $this->postJson("/api/schools/{$school->id}/students", [])->assertForbidden();
        $this->deleteJson("/api/admin/platform/subscriptions/{$subscription}")->assertConflict();
    }

    public function test_organisation_members_have_scoped_access_and_verified_owners_are_protected(): void
    {
        $admin = $this->signInAsPlatformAdmin();
        $group = $this->group();
        $school = School::factory()->create(['group_id' => $group, 'claim_status' => 'claimed']);
        $other = School::factory()->create();
        $owner = User::factory()->create(['is_active' => true]);
        $viewer = User::factory()->create(['is_active' => true]);
        $path = '/api/admin/platform/organisation-members';
        $this->postJson($path, ['school_group_id' => $group, 'user_id' => $owner->id, 'role' => 'owner'])->assertCreated();
        $this->deleteJson("{$path}/{$group}-{$owner->id}")->assertConflict();
        $this->postJson($path, ['school_group_id' => $group, 'user_id' => $viewer->id, 'role' => 'group_viewer'])->assertCreated();

        $this->actingAs($viewer)->getJson("/api/schools/{$school->id}")->assertOk();
        $this->getJson("/api/schools/{$school->id}/students")->assertForbidden();
        $this->getJson("/api/schools/{$other->id}")->assertNotFound();
        $this->actingAs($admin)->patchJson("{$path}/{$group}-{$viewer->id}", ['is_active' => false])->assertOk();
        $this->actingAs($viewer)->getJson("/api/schools/{$school->id}")->assertNotFound();
    }

    public function test_only_safe_otp_metadata_is_exposed_and_notifications_belong_to_the_recipient(): void
    {
        $admin = $this->signInAsPlatformAdmin();
        $recipient = User::factory()->create(['is_active' => true]);
        $otp = DB::table('otp_codes')->insertGetId([
            'user_id' => $recipient->id, 'channel' => 'email', 'purpose' => 'claim',
            'destination' => 'private@example.test', 'code_hash' => 'internal-hash', 'expires_at' => now()->addMinute(),
        ]);
        $this->getJson('/api/admin/platform/otp-codes')->assertOk()
            ->assertJsonMissingPath('data.0.code_hash')->assertJsonMissingPath('data.0.destination');
        $this->postJson('/api/admin/platform/otp-codes', [])->assertStatus(405);
        $this->patchJson("/api/admin/platform/otp-codes/{$otp}", ['invalidate' => true])->assertOk();
        $this->assertDatabaseMissing('otp_codes', ['id' => $otp, 'consumed_at' => null]);

        $notice = $this->postJson('/api/admin/platform/notifications', [
            'notifiable_id' => $recipient->id, 'title' => 'Test notice', 'message' => 'Synthetic notification',
        ])->assertCreated()->json('data.id');
        $this->getJson('/api/notifications')->assertJsonCount(0, 'data');
        $this->patchJson("/api/notifications/{$notice}", ['read' => true])->assertNotFound();
        $this->actingAs($recipient)->getJson('/api/notifications')->assertJsonPath('data.0.id', $notice);
        $this->patchJson("/api/notifications/{$notice}", ['read' => true])->assertOk()->assertJsonPath('data.read', true);
        $this->deleteJson("/api/notifications/{$notice}")->assertNoContent();
        $this->assertDatabaseMissing('notifications', ['id' => $notice]);
    }

    private function group(): int
    {
        return DB::table('school_groups')->insertGetId(['name' => 'Example Network', 'slug' => 'example-network']);
    }

    private function signInAsPlatformAdmin(): User
    {
        $admin = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);
        $this->actingAs($admin);

        return $admin;
    }
}
