<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use App\Notifications\ListingClaimVerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InstituteClaimTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_claim_cannot_be_approved_before_email_verification(): void
    {
        $school = School::factory()->create(['listing_status' => 'published', 'verified_at' => now()]);
        $claimant = User::factory()->create(['is_active' => true]);
        $admin = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);
        $this->actingAs($claimant)->post("/institutions/{$school->slug}/claim", [
            'claimant_name' => 'Synthetic owner', 'phone' => '+92 300 0000000',
        ])->assertRedirect();
        $claim = DB::table('listing_claims')->where('user_id', $claimant->id)->value('id');

        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/claims/$claim", [
            'decision' => 'verified', 'review_note' => 'Synthetic review',
        ])->assertConflict();
        $this->assertDatabaseCount('school_group_user', 0);
    }

    public function test_registration_and_claim_intake_do_not_grant_school_access(): void
    {
        $school = School::factory()->create(['listing_status' => 'published', 'verified_at' => now()]);
        $path = "/institutions/{$school->slug}/claim";

        $this->get($path)->assertRedirect(route('institute.register', ['listing' => $school->slug]));
        $registration = [
            'name' => 'Synthetic Owner', 'email' => 'owner@example.test',
            'listing' => $school->slug, 'is_super_admin' => true,
        ];
        $this->post('/institute/register', [...$registration, 'password' => 'Demo123', 'password_confirmation' => 'Demo123'])
            ->assertSessionHasErrors('password');
        $this->post('/institute/register', [...$registration, 'password' => 'Demo1234', 'password_confirmation' => 'Demo1234'])
            ->assertRedirect($path);
        $user = User::where('email', 'owner@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->isSuperAdmin());

        $details = ['claimant_name' => 'Synthetic Owner', 'claimant_role' => 'Principal', 'phone' => '+92 300 0000000'];
        $this->post($path, [...$details, 'status' => 'verified'])->assertRedirect($path);
        $this->assertDatabaseHas('listing_claims', [
            'school_id' => $school->id, 'user_id' => $user->id, 'status' => 'pending', 'method' => 'manual',
        ]);
        $this->assertDatabaseMissing('school_user', ['school_id' => $school->id, 'user_id' => $user->id]);
        $this->post($path, $details)->assertRedirect($path);
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
        ])->assertRedirect('/institutions/synthetic-new-college/claim');

        $school = School::where('slug', 'synthetic-new-college')->firstOrFail();
        $this->assertSame('pending_review', $school->listing_status);
        $this->assertSame('self_submitted', $school->source);
        $this->assertDatabaseHas('listing_claims', ['school_id' => $school->id, 'user_id' => $claimant->id, 'status' => 'pending']);
        $this->get('/institutions/'.$school->slug.'/claim')->assertSee('Send verification code');
        $this->actingAs(User::factory()->create(['is_active' => true]))
            ->get('/institutions/'.$school->slug.'/claim')->assertNotFound();
        $this->actingAs($claimant)->get('/institute/new')->assertSee('Synthetic New College');
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
        Notification::fake();
        $path = "/institutions/{$school->slug}/claim";
        $code = $this->sendClaimCode($path, $claimant);
        $this->post("$path/verify", ['code' => $code])->assertRedirect($path);
        $this->assertDatabaseMissing('school_group_user', ['school_group_id' => $group, 'user_id' => $claimant->id]);
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
    public function test_claim_codes_expire_are_rate_limited_and_can_only_be_used_once(): void
    {
        Notification::fake();
        $school = School::factory()->create(['listing_status' => 'published', 'verified_at' => now()]);
        $claimant = User::factory()->create(['is_active' => true]);
        $stranger = User::factory()->create(['is_active' => true]);
        $path = "/institutions/{$school->slug}/claim";
        $this->actingAs($claimant)->post($path, ['claimant_name' => 'Synthetic claimant', 'phone' => '000']);
        $first = $this->sendClaimCode($path, $claimant);
        $this->post("$path/code")->assertSessionHasErrors('code');
        $this->actingAs($stranger)->post("$path/verify", ['code' => $first])->assertNotFound();
        $this->actingAs($claimant);
        $this->travel(11)->minutes();
        $this->post("$path/verify", ['code' => $first])->assertSessionHasErrors('code');

        $second = $this->sendClaimCode($path, $claimant);
        $this->travel(1)->minute();
        $third = $this->sendClaimCode($path, $claimant);
        $this->assertNotSame($second, $third);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post("$path/verify", ['code' => $second])->assertSessionHasErrors('code');
        }
        $this->assertDatabaseHas('listing_claim_verification_codes', ['attempts' => 5]);
        $this->post("$path/verify", ['code' => $third])->assertSessionHasErrors('code')
            ->assertSessionMissing('_old_input.code');
        $this->travel(1)->minute();
        $valid = $this->sendClaimCode($path, $claimant);
        $this->post("$path/verify", ['code' => $valid])->assertRedirect($path);
        $this->assertDatabaseCount('listing_claim_verification_codes', 0);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->post("$path/verify", ['code' => $valid])->assertSessionHasErrors('code');
        }
        $this->post("$path/verify", ['code' => $valid])->assertTooManyRequests();
    }

    private function sendClaimCode(string $path, User $claimant): string
    {
        $this->post("$path/code")->assertRedirect($path)->assertSessionHasNoErrors();
        $code = null;
        Notification::assertSentOnDemand(ListingClaimVerificationCode::class,
            function ($notification, $channels, $notifiable) use ($claimant, &$code): bool {
                if ($channels === ['mail'] && $notifiable->routes['mail'] === $claimant->email) {
                    $code = $notification->code;

                    return true;
                }

                return false;
            });

        return $code;
    }

}
