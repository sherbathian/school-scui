<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_and_edits_a_draft_on_the_school_record(): void
    {
        $this->signInAsPlatformAdmin();

        $listing = $this->postJson('/api/admin/listings', [
            'name' => 'North Campus',
            'institute_type' => 'college',
            'listing_status' => 'published',
            'source' => 'self_submitted',
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'north-campus')
            ->assertJsonPath('data.listing_status', 'draft')
            ->assertJsonPath('data.source', 'admin_created')
            ->assertJsonPath('data.verified_at', null)
            ->json('data.id');

        $this->patchJson("/api/admin/listings/{$listing}", [
            'tagline' => 'Admissions open',
            'website_url' => 'https://example.org',
        ])->assertOk()->assertJsonPath('data.tagline', 'Admissions open');

        $this->assertDatabaseHas('schools', [
            'id' => $listing,
            'institute_type' => 'college',
            'tagline' => 'Admissions open',
            'listing_status' => 'draft',
        ]);

        School::factory()->create();
        $this->getJson('/api/admin/listings?listing_status=draft&institute_type=college')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $listing);

        $this->postJson('/api/admin/listings', ['name' => 'North Campus', 'institute_type' => 'school'])
            ->assertUnprocessable()->assertJsonValidationErrors('slug');
    }

    public function test_verification_is_required_before_publication_and_suspension_preserves_history(): void
    {
        $this->signInAsPlatformAdmin();
        $school = School::factory()->create();
        $url = "/api/admin/listings/{$school->id}";

        $this->postJson("{$url}/publish")->assertConflict();
        $this->postJson("{$url}/verify")->assertOk()->assertJsonPath('data.listing_status', 'draft');
        $this->assertNotNull($school->fresh()->verified_at);

        $publishedAt = $this->postJson("{$url}/publish")->assertOk()
            ->assertJsonPath('data.listing_status', 'published')->json('data.published_at');
        $this->assertNotNull($publishedAt);

        $this->postJson("{$url}/suspend")->assertOk()
            ->assertJsonPath('data.listing_status', 'suspended');
        $this->travel(1)->hour();
        $this->postJson("{$url}/publish")->assertOk()
            ->assertJsonPath('data.published_at', $publishedAt);
    }

    public function test_listing_actions_require_platform_admin_access(): void
    {
        $school = School::factory()->create();
        $url = "/api/admin/listings/{$school->id}";

        $this->getJson('/api/admin/listings')->assertUnauthorized();

        $member = User::factory()->create();
        $member->schools()->attach($school, ['role' => 'school_admin', 'is_active' => true]);
        $this->actingAs($member, 'sanctum');

        $this->getJson('/api/admin/listings')->assertForbidden();
        $this->postJson('/api/admin/listings')->assertForbidden();
        $this->patchJson($url)->assertForbidden();
        $this->postJson("{$url}/verify")->assertForbidden();
        $this->postJson("{$url}/publish")->assertForbidden();
        $this->postJson("{$url}/suspend")->assertForbidden();
    }

    public function test_archived_and_management_suspended_schools_cannot_be_published(): void
    {
        $this->signInAsPlatformAdmin();
        $school = School::factory()->create(['status' => 'suspended']);

        $this->postJson("/api/admin/listings/{$school->id}/verify")->assertOk();
        $this->postJson("/api/admin/listings/{$school->id}/publish")->assertConflict();

        $school->delete();
        $this->postJson("/api/admin/listings/{$school->id}/publish")->assertNotFound();
    }

    public function test_admin_workspace_is_available_only_to_active_platform_admins(): void
    {
        $this->withoutVite();
        $this->get('/admin/listings')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Listings')->where('administrator', false));

        $this->actingAs(User::factory()->create())
            ->get('/admin/listings')->assertForbidden();

        $this->actingAs(User::factory()->create(['is_super_admin' => true, 'is_active' => false]))
            ->getJson('/api/admin/listings')->assertForbidden();

        $this->actingAs(User::factory()->create(['is_super_admin' => true, 'is_active' => true]))
            ->get('/admin/listings')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Listings')->where('administrator', true));
    }

    public function test_admin_sign_in_rejects_school_members_and_starts_a_platform_session(): void
    {
        $member = User::factory()->create();
        $admin = User::factory()->create(['is_super_admin' => true]);
        $this->withHeader('Origin', 'http://localhost:8000');

        $this->postJson('/api/admin/login', ['email' => $member->email, 'password' => 'password'])
            ->assertUnprocessable();
        $this->assertGuest();

        $this->postJson('/api/admin/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertOk()->assertJsonPath('data.is_super_admin', true);
        $this->getJson('/api/admin/listings')->assertOk();
        $this->postJson('/api/logout')->assertNoContent();
        $this->getJson('/api/admin/listings')->assertUnauthorized();
    }

    public function test_admin_manages_locations_and_filters_listings_by_city_and_area(): void
    {
        $this->signInAsPlatformAdmin();
        $firstCity = $this->postJson('/api/admin/cities', ['name' => 'North City'])->assertCreated()->json('data.id');
        $secondCity = $this->postJson('/api/admin/cities', ['name' => 'South City'])->assertCreated()->json('data.id');
        $area = $this->postJson("/api/admin/cities/{$firstCity}/areas", ['name' => 'Central'])
            ->assertCreated()->json('data.id');

        $profile = ['name' => 'North Academy', 'institute_type' => 'school', 'area_id' => $area];

        $this->postJson('/api/admin/listings', [...$profile, 'city_id' => $secondCity])
            ->assertUnprocessable()->assertJsonValidationErrors('area_id');

        $listing = $this->postJson('/api/admin/listings', [...$profile, 'city_id' => $firstCity])
            ->assertCreated()->assertJsonPath('data.area_id', $area)->json('data.id');

        School::factory()->create();
        $this->getJson("/api/admin/listings?city_id={$firstCity}&area_id={$area}")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $listing);
        $this->getJson("/api/admin/cities/{$secondCity}/areas")
            ->assertOk()->assertJsonCount(0, 'data');

        $this->patchJson("/api/admin/listings/{$listing}", ['city_id' => $secondCity])
            ->assertUnprocessable()->assertJsonValidationErrors('area_id');
        $this->patchJson("/api/admin/listings/{$listing}", ['city_id' => $secondCity, 'area_id' => null])
            ->assertOk()->assertJsonPath('data.city_id', $secondCity);
    }

    public function test_dashboard_counts_and_filters_the_verification_queue(): void
    {
        $this->signInAsPlatformAdmin();
        $draft = School::factory()->create();
        School::factory()->create(['listing_status' => 'published', 'verified_at' => now()]);
        School::factory()->create(['listing_status' => 'suspended']);
        School::factory()->create(['status' => 'suspended']);
        School::factory()->create()->delete();

        $this->getJson('/api/admin/listings?needs_verification=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $draft->id)
            ->assertJsonPath('summary.published', 1)
            ->assertJsonPath('summary.needs_verification', 1)
            ->assertJsonPath('summary.suspended', 1);

        $this->postJson("/api/admin/listings/{$draft->id}/verify")->assertOk();
        $this->getJson('/api/admin/listings?needs_verification=1')
            ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('summary.needs_verification', 0);
    }

    public function test_listing_search_respects_status_and_matches_slug(): void
    {
        $this->signInAsPlatformAdmin();
        $match = School::factory()->create(['name' => 'Example Academy', 'slug' => 'north-campus']);
        School::factory()->create(['name' => 'North Institute', 'listing_status' => 'suspended']);
        School::factory()->create(['name' => 'South Institute']);

        $this->getJson('/api/admin/listings?search=north&listing_status=draft')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
    }

    public function test_listing_editor_pages_require_platform_access_and_load_the_requested_record(): void
    {
        $this->withoutVite();
        $listing = School::factory()->create();

        $this->get('/admin/login')->assertOk();
        $this->get('/admin/listings/create')->assertRedirect('/admin/login');

        $this->actingAs(User::factory()->create())->get('/admin/listings/create')->assertForbidden();
        $this->signInAsPlatformAdmin();
        $this->get('/admin/listings/create')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('screen', 'create')->where('listing', null));
        $this->get("/admin/listings/{$listing->id}/edit")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('screen', 'edit')->where('listing.id', $listing->id));
        $listing->delete();
        $this->get("/admin/listings/{$listing->id}/edit")->assertNotFound();
    }

    public function test_platform_admin_can_list_edit_and_delete_locations(): void
    {
        $this->get('/admin/cities')->assertRedirect('/admin/login');
        $this->getJson('/api/admin/areas')->assertUnauthorized();
        $this->signInAsPlatformAdmin();
        $this->withoutVite();
        $city = $this->postJson('/api/admin/cities', ['name' => 'Islamabad'])->assertCreated()->json('data.id');
        $area = $this->postJson("/api/admin/cities/{$city}/areas", ['name' => 'F-8'])->assertCreated()->json('data.id');
        $this->get('/admin/areas')->assertInertia(fn (Assert $page) => $page->component('Admin/Locations')->where('kind', 'areas'));
        $this->getJson('/api/admin/areas')->assertJsonPath('data.0.city_name', 'Islamabad');
        $this->patchJson("/api/admin/cities/{$city}", ['name' => 'Islamabad Capital'])->assertOk()->assertJsonPath('data.slug', 'islamabad');
        $this->patchJson("/api/admin/areas/{$area}", ['name' => 'Sector F-8', 'area_type' => 'sector'])
            ->assertOk()->assertJsonPath('data.area_type', 'sector');
        $this->deleteJson("/api/admin/cities/{$city}")->assertConflict();
        $this->deleteJson("/api/admin/areas/{$area}")->assertNoContent();
        $this->deleteJson("/api/admin/cities/{$city}")->assertNoContent();
    }

    private function signInAsPlatformAdmin(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);
        $this->actingAs($admin, 'sanctum');
    }
}
