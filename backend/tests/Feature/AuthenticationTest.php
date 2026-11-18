<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_read_their_profile_or_school_list(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->getJson('/api/schools')->assertUnauthorized();
    }

    public function test_user_can_sign_in_read_their_school_memberships_and_sign_out(): void
    {
        $user = User::factory()->create();
        $school = $this->createSchool('North School', 'north-school');
        $user->schools()->attach($school, ['role' => 'teacher']);

        $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.is_super_admin', false)
            ->assertJsonPath('data.schools.0.name', 'North School')
            ->assertJsonPath('data.schools.0.role', 'teacher')
            ->assertJsonMissingPath('data.password');

        $this->getJson('/api/me')->assertOk()->assertJsonPath('data.email', $user->email);
        $this->postJson('/api/logout')->assertNoContent();
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_invalid_credentials_receive_a_generic_error(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'The provided credentials are incorrect.');
    }

    public function test_school_members_can_only_read_their_own_schools(): void
    {
        $teacher = User::factory()->create();
        $assigned = $this->createSchool('North School', 'north-school');
        $other = $this->createSchool('South School', 'south-school');
        $teacher->schools()->attach($assigned, ['role' => 'teacher']);

        $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/schools')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'north-school');

        $this->getJson('/api/schools/'.$other->id)->assertNotFound();
    }

    public function test_school_admins_can_manage_a_school_but_teachers_cannot(): void
    {
        $school = $this->createSchool('North School', 'north-school');
        $admin = User::factory()->create();
        $teacher = User::factory()->create();
        $admin->schools()->attach($school, ['role' => 'school_admin']);
        $teacher->schools()->attach($school, ['role' => 'teacher']);

        $this->assertTrue(Gate::forUser($admin)->allows('manage', $school));
        $this->assertFalse(Gate::forUser($teacher)->allows('manage', $school));
    }

    public function test_super_admin_can_list_and_manage_schools_without_a_membership(): void
    {
        $school = $this->createSchool('North School', 'north-school');
        $superAdmin = User::factory()->create();
        $superAdmin->forceFill(['is_super_admin' => true])->save();

        $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/schools')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.role', 'super_admin');

        $this->assertTrue(Gate::forUser($superAdmin)->allows('manage', $school));
    }

    private function createSchool(string $name, string $slug): School
    {
        return School::create(['name' => $name, 'slug' => $slug]);
    }
}
