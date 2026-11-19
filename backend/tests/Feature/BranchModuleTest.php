<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_platform_admins_change_switches_and_dependencies_are_enforced_per_branch(): void
    {
        $first = School::factory()->create(['listing_status' => 'published', 'verified_at' => now()]);
        $second = School::factory()->create();
        $manager = User::factory()->create();
        $manager->schools()->attach([$first->id => ['role' => 'admin'], $second->id => ['role' => 'admin']]);
        $admin = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);
        $path = "/api/admin/branches/{$first->id}/modules";

        $this->actingAs($manager, 'sanctum')->patchJson("{$path}/students", ['enabled' => false])->assertForbidden();
        $this->getJson("/api/schools/{$first->id}/students")->assertOk();
        $this->actingAs($admin, 'sanctum')->getJson($path)->assertOk();
        $this->patchJson("{$path}/listing", ['enabled' => false])->assertConflict();
        $this->patchJson("{$path}/students", ['enabled' => false])->assertOk();
        $this->patchJson("{$path}/fees", ['enabled' => true])->assertConflict();

        $this->actingAs($manager, 'sanctum')->getJson("/api/schools/{$first->id}/students")->assertForbidden();
        $this->getJson("/api/schools/{$first->id}/fee-types")->assertForbidden();
        $this->getJson("/api/schools/{$second->id}/students")->assertOk();
        $this->get("/institutions/{$first->slug}")->assertOk();

        $this->actingAs($admin, 'sanctum')->patchJson("{$path}/students", ['enabled' => true])->assertOk();
        $this->actingAs($manager, 'sanctum')->getJson("/api/schools/{$first->id}/fee-types")->assertOk();
    }
}
