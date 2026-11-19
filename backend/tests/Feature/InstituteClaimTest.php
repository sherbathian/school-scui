<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InstituteClaimTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_and_claim_intake_do_not_grant_school_access(): void
    {
        $school = School::factory()->create(['listing_status' => 'published', 'verified_at' => now()]);
        $path = "/institutions/{$school->slug}/claim";

        $this->get($path)->assertRedirect(route('institute.register', ['listing' => $school->slug]));
        $this->post('/institute/register', [
            'name' => 'Synthetic Owner', 'email' => 'owner@example.test',
            'password' => 'Synthetic-passphrase-2026', 'password_confirmation' => 'Synthetic-passphrase-2026',
            'listing' => $school->slug, 'is_super_admin' => true,
        ])->assertRedirect($path);
        $user = User::where('email', 'owner@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->isSuperAdmin());

        $details = ['claimant_name' => 'Synthetic Owner', 'claimant_role' => 'Principal', 'phone' => '+92 300 0000000'];
        $this->post($path, [...$details, 'status' => 'verified'])->assertRedirect($path);
        $this->assertDatabaseHas('listing_claims', [
            'school_id' => $school->id, 'user_id' => $user->id, 'status' => 'pending', 'method' => 'manual',
        ]);
        $this->assertDatabaseMissing('school_user', ['school_id' => $school->id, 'user_id' => $user->id]);
        $this->post($path, $details)->assertSessionHasErrors('claim');
        $this->assertDatabaseCount('listing_claims', 1);
        $this->get('/admin/claims')->assertForbidden();
    }

    public function test_claim_intake_excludes_hidden_listings_and_admin_can_view_the_queue(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user)->get("/institutions/{$school->slug}/claim")->assertNotFound();
        $this->actingAs(User::factory()->create(['is_super_admin' => true, 'is_active' => true]));
        $this->withoutVite()->get('/admin/claims')->assertOk();
    }

    public function test_new_institute_submissions_remain_private_during_review(): void
    {
        $claimant = User::factory()->create(['is_active' => true]);
        $this->actingAs($claimant)->post('/institute/new', [
            'name' => 'Synthetic New College', 'institute_type' => 'college', 'phone' => '+92 300 1111111',
            'listing_status' => 'published',
        ])->assertRedirect('/institute/new');

        $school = School::where('slug', 'synthetic-new-college')->firstOrFail();
        $this->assertSame('pending_review', $school->listing_status);
        $this->assertSame('self_submitted', $school->source);
        $this->assertDatabaseHas('listing_claims', ['school_id' => $school->id, 'user_id' => $claimant->id, 'status' => 'pending']);
        $this->get('/institute/new')->assertSee('Synthetic New College');
        $this->get('/institutions/'.$school->slug)->assertNotFound();
        $this->get('/sitemap.xml')->assertDontSee($school->slug);
    }

    public function test_super_admin_approval_grants_only_organisation_access_and_is_single_use(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);
        $claimant = User::factory()->create(['is_active' => true]);
        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/listings/{$school->id}/verify")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/listings/{$school->id}/publish")->assertOk();
        $group = $school->fresh()->group_id;
        $this->assertNotNull($group);
        $other = School::factory()->create();

        $details = ['claimant_name' => 'Owner Candidate', 'phone' => '+92 300 0000000'];
        $this->actingAs($claimant)->post("/institutions/{$school->slug}/claim", $details)->assertRedirect();
        $claim = DB::table('listing_claims')->where('user_id', $claimant->id)->value('id');
        $decision = ['decision' => 'verified', 'review_note' => 'Verified through the institution.'];
        $this->actingAs($claimant, 'sanctum')->patchJson("/api/admin/claims/{$claim}", $decision)->assertForbidden();
        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/claims/{$claim}", $decision)->assertOk();
        $this->patchJson("/api/admin/claims/{$claim}", $decision)->assertConflict();
        $branch = School::factory()->create(['group_id' => $group]);

        $this->assertDatabaseHas('school_group_user', ['school_group_id' => $group, 'user_id' => $claimant->id, 'role' => 'owner']);
        $this->assertDatabaseHas('listing_claims', ['id' => $claim, 'status' => 'verified', 'reviewed_by_user_id' => $admin->id]);
        $this->actingAs($claimant, 'sanctum')->getJson("/api/schools/{$branch->id}")->assertOk()->assertJsonPath('data.role', 'owner');
        $this->getJson("/api/schools/{$other->id}")->assertNotFound();
        $this->getJson('/api/admin/claims')->assertForbidden();
        DB::table('school_group_user')->where('user_id', $claimant->id)->update(['is_active' => false]);
        $this->getJson("/api/schools/{$branch->id}")->assertNotFound();
    }
}
