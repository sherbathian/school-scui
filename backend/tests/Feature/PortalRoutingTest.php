<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_entry_points_use_the_canonical_login(): void
    {
        $this->withoutVite();

        foreach (['/', '/admin/login', '/dashboard'] as $path) {
            $this->get('https://admin.taleemfinder.pk'.$path)
                ->assertRedirect('https://admin.taleemfinder.pk/login');
        }

        $this->get('http://admin.taleemfinder.pk/dashboard')
            ->assertRedirect('https://admin.taleemfinder.pk/login');

        $this->get('https://admin.taleemfinder.pk/login')->assertOk()->assertViewIs('admin.app');
        $this->get('https://taleemfinder.pk/login')->assertOk()->assertViewIs('directory.institute-account');
    }

    public function test_management_dashboard_requires_management_access(): void
    {
        $this->withoutVite();
        $member = User::factory()->create()->refresh();
        $this->postJson('https://admin.taleemfinder.pk/login', [
            'email' => $member->email,
            'password' => 'password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertGuest();
        $this->actingAs($member)->get('https://admin.taleemfinder.pk/dashboard')->assertForbidden();
        $this->get('https://taleemfinder.pk/dashboard')->assertOk()->assertViewIs('directory.dashboard');

        $admin = User::factory()->create(['is_super_admin' => true])->refresh();
        $this->actingAs($admin);
        foreach (['/', '/admin/login', '/login', '/admin/dashboard'] as $path) {
            $this->get('https://admin.taleemfinder.pk'.$path)
                ->assertRedirect('https://admin.taleemfinder.pk/dashboard');
        }
        $this->get('https://admin.taleemfinder.pk/dashboard')->assertOk()->assertViewIs('admin.app');
    }

    public function test_management_sign_in_api_uses_a_session_on_the_admin_host(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['is_super_admin' => true])->refresh();

        $this->withHeader('Origin', 'https://admin.taleemfinder.pk')
            ->postJson('https://admin.taleemfinder.pk/api/admin/login', [
                'email' => $admin->email,
                'password' => 'password',
            ])->assertOk();
        $this->assertAuthenticatedAs($admin);
        $this->get('https://admin.taleemfinder.pk/admin/dashboard')
            ->assertRedirect('https://admin.taleemfinder.pk/dashboard');
    }

    public function test_management_paths_on_the_public_host_do_not_serve_the_management_portal(): void
    {
        $this->withoutVite();
        $this->get('https://taleemfinder.pk/admin/login')
            ->assertRedirect('https://admin.taleemfinder.pk/admin/login');
        $this->get('https://taleemfinder.pk/admin/dashboard/25')
            ->assertRedirect('https://admin.taleemfinder.pk/admin/dashboard/25');
        $this->postJson('https://taleemfinder.pk/api/admin/login')->assertNotFound();
    }

    public function test_management_pages_use_prefix_free_paths_without_weakening_access(): void
    {
        $this->withoutVite();
        $host = 'https://admin.taleemfinder.pk';
        $school = School::factory()->create();
        $otherSchool = School::factory()->create();
        $teacher = User::factory()->create()->refresh();
        $teacher->schools()->attach($school, ['role' => 'teacher', 'is_active' => true]);

        $this->get($host.'/operations')->assertRedirect($host.'/login');
        $this->get($host.'/admin/operations?tab=grades')->assertRedirect($host.'/operations?tab=grades');
        $this->get($host.'/admin')->assertRedirect($host);
        $this->get('https://taleemfinder.pk/operations')->assertNotFound();
        $this->get('https://taleemfinder.pk/directory')->assertOk();

        $this->actingAs($teacher)->get($host.'/operations')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/BranchWorkspace')->where('branch.id', $school->id));
        $this->get($host.'/branches/'.$otherSchool->id.'/operations')->assertNotFound();
        $this->get($host.'/listings/create')->assertForbidden();

        $admin = User::factory()->create(['is_super_admin' => true])->refresh();
        $this->actingAs($admin)->get($host.'/listings/'.$school->id.'/edit')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Listings')->where('screen', 'edit'));
        $this->get($host.'/directory/boards')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Directory')->where('resource', 'boards'));
        $this->get($host.'/admin/reports/'.$school->id.'/csv?month=2026-11')
            ->assertRedirect($host.'/reports/'.$school->id.'/csv?month=2026-11');
    }

    public function test_public_dashboard_lists_only_the_signed_in_users_listings(): void
    {
        $this->withoutVite();
        $user = User::factory()->create()->refresh();
        $other = User::factory()->create()->refresh();

        foreach ([[$user, 'My test listing'], [$other, 'Another test listing']] as [$owner, $name]) {
            $this->actingAs($owner)->post('https://taleemfinder.pk/institute/new', [
                'name' => $name,
                'institute_type' => 'school',
                'claimant_role' => 'Owner',
                'phone' => '03000000000',
            ])->assertSessionHasNoErrors();
        }

        $this->actingAs($user)->get('https://taleemfinder.pk/dashboard')
            ->assertOk()->assertSee('My test listing')->assertDontSee('Another test listing')
            ->assertSee('https://taleemfinder.pk/institute/new');
    }
}
