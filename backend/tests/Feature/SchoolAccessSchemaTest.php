<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SchoolAccessSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_code_is_unique_within_each_organisation_and_profile_fields_can_be_saved(): void
    {
        $group = DB::table('school_groups')->insertGetId(['name' => 'Synthetic group', 'slug' => 'synthetic-group']);
        $otherGroup = DB::table('school_groups')->insertGetId(['name' => 'Other group', 'slug' => 'other-group']);
        $first = School::factory()->create(['group_id' => $group]);
        $second = School::factory()->create(['group_id' => $group]);
        $third = School::factory()->create(['group_id' => $otherGroup]);
        $admin = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);

        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/listings/{$first->id}", [
            'branch_code' => 'NORTH', 'gender_type' => 'co_ed', 'established_year' => 2005,
            'seo_title' => 'Synthetic campus',
        ])->assertOk()->assertJsonPath('data.branch_code', 'NORTH')->assertJsonPath('data.gender_type', 'co_ed');
        $this->patchJson("/api/admin/listings/{$second->id}", ['branch_code' => 'north'])
            ->assertUnprocessable()->assertJsonValidationErrors('branch_code');
        $this->patchJson("/api/admin/listings/{$third->id}", ['branch_code' => 'NORTH'])->assertOk();
    }

    public function test_primary_contact_stays_on_one_branch_membership_without_changing_its_role(): void
    {
        $branch = School::factory()->create();
        $other = School::factory()->create();
        $member = User::factory()->create();
        $branch->users()->attach($member, ['role' => 'teacher']);
        $other->users()->attach($member, ['role' => 'teacher']);
        $admin = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);

        $this->actingAs($admin, 'sanctum')->patchJson("/api/schools/{$branch->id}/users/{$member->id}", [
            'is_primary_contact' => true,
        ])->assertOk()->assertJsonPath('data.is_primary_contact', true)->assertJsonPath('data.role', 'teacher');
        $this->assertDatabaseHas('school_user', ['school_id' => $other->id, 'user_id' => $member->id, 'is_primary_contact' => false]);
    }

    public function test_owner_signup_saves_private_unverified_phone_and_successful_sign_in_updates_timestamp(): void
    {
        $this->post('/institute/register', [
            'name' => 'Synthetic contact', 'email' => 'contact@example.test',
            'password' => 'Synthetic-long-password', 'password_confirmation' => 'Synthetic-long-password',
            'phone' => '+1 202 555 0100', 'locale' => 'ur', 'phone_verified_at' => now(),
        ])->assertRedirect('/institute/new');
        $user = User::where('email', 'contact@example.test')->firstOrFail();
        $this->assertSame('+1 202 555 0100', $user->phone);
        $this->assertNull($user->phone_verified_at);
        $this->assertSame('ur', $user->locale);
        $this->assertNotNull($user->last_login_at);

        $this->post('/institute/sign-out')->assertRedirect();
        $this->travel(2)->minutes();
        $this->post('/institute/sign-in', ['email' => 'contact@example.test', 'password' => 'invalid'])
            ->assertSessionHasErrors('email');
        $this->assertEquals($user->last_login_at, $user->fresh()->last_login_at);
        $this->post('/institute/sign-in', ['email' => 'contact@example.test', 'password' => 'Synthetic-long-password'])
            ->assertRedirect('/institute/new');
        $this->assertGreaterThan($user->last_login_at, $user->fresh()->last_login_at);
    }
}
