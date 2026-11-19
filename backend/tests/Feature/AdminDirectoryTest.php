<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_catalogue_and_branch_assignments_are_platform_managed(): void
    {
        $first = School::factory()->create();
        $second = School::factory()->create();
        $this->getJson('/api/admin/directory/boards')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson('/api/admin/directory/boards', ['name' => 'Federal'])->assertForbidden();
        $this->signInAsPlatformAdmin();
        $this->withoutVite()->get('/admin/directory/boards')->assertOk();
        $this->getJson('/api/admin/directory/users')->assertNotFound();

        foreach (['boards' => 'school_board', 'education-levels' => 'school_education_level', 'facilities' => 'school_facility'] as $resource => $pivot) {
            $path = "/api/admin/directory/{$resource}";
            $assignment = "/api/admin/listings/{$first->id}/assignments/{$resource}";
            $id = $this->postJson($path, ['name' => 'Alpha'])->assertCreated()->json('data.id');
            $other = $this->postJson($path, ['name' => 'Beta'])->assertCreated()->json('data.id');
            $this->patchJson("{$path}/{$id}", ['name' => 'Alpha Updated'])->assertOk()->assertJsonPath('data.name', 'Alpha Updated');
            $this->getJson($path)->assertJsonCount(2, 'data');

            $this->putJson($assignment, ['ids' => [$id, $id]])->assertUnprocessable();
            $this->putJson($assignment, ['ids' => [99999]])->assertUnprocessable();
            $this->putJson($assignment, ['ids' => [[]]])->assertUnprocessable();
            $this->putJson($assignment, ['ids' => [$id, $other]])->assertOk()->assertJsonCount(2, 'data');
            $this->putJson($assignment, ['ids' => [$id, $other]])->assertOk()->assertJsonCount(2, 'data');
            $this->putJson($assignment, ['ids' => [$other]])->assertOk()->assertJsonCount(1, 'data');
            $this->getJson("/api/admin/listings/{$second->id}/assignments/{$resource}")->assertJsonCount(0, 'data');
            $this->deleteJson("{$path}/{$other}")->assertNoContent();
            $this->assertDatabaseCount($pivot, 0);
        }
    }

    public function test_courses_and_programs_validate_location_and_slug_scope(): void
    {
        $this->signInAsPlatformAdmin();
        $first = School::factory()->create();
        $second = School::factory()->create();
        $city = $this->postJson('/api/admin/cities', ['name' => 'Islamabad'])->assertCreated()->json('data.id');
        $otherCity = $this->postJson('/api/admin/cities', ['name' => 'Rawalpindi'])->assertCreated()->json('data.id');
        $area = $this->postJson("/api/admin/cities/{$city}/areas", ['name' => 'F-8', 'latitude' => '33.7000000'])
            ->assertCreated()->json('data.id');
        $categoryPath = '/api/admin/directory/course-categories';
        $category = $this->postJson($categoryPath, ['name' => 'Technology'])->assertCreated()->json('data.id');
        $this->patchJson("{$categoryPath}/{$category}", ['sort_order' => 5])->assertOk()->assertJsonPath('data.sort_order', 5);
        $coursePath = '/api/admin/directory/courses';
        $course = ['title' => 'Python', 'city_id' => $city, 'area_id' => $area, 'course_category_id' => $category];
        $this->postJson($coursePath, [...$course, 'city_id' => $otherCity])->assertJsonValidationErrors('area_id');
        $id = $this->postJson($coursePath, $course)->assertCreated()->assertJsonPath('data.school_id', null)->json('data.id');
        $this->patchJson("{$coursePath}/{$id}", ['city_id' => $otherCity])->assertJsonValidationErrors('area_id');
        $this->patchJson("{$coursePath}/{$id}", ['status' => 'published'])->assertOk();
        $this->deleteJson("{$categoryPath}/{$category}")->assertNoContent();
        $this->assertDatabaseHas('courses', ['id' => $id, 'course_category_id' => null]);
        $this->deleteJson("{$coursePath}/{$id}")->assertNoContent();

        $programPath = '/api/admin/directory/programs';
        $program = ['title' => 'Computer Science', 'school_id' => $first->id, 'fee_period' => 'semester'];
        $programId = $this->postJson($programPath, $program)->assertCreated()->json('data.id');
        $this->postJson($programPath, $program)->assertJsonValidationErrors('slug');
        $this->postJson($programPath, [...$program, 'school_id' => $second->id])->assertCreated();
        $this->patchJson("{$programPath}/{$programId}", ['admission_open' => true])->assertOk();
        $this->getJson("{$programPath}?school_id={$first->id}")->assertJsonCount(1, 'data');
        $this->deleteJson("{$programPath}/{$programId}")->assertNoContent();
    }

    public function test_groups_fees_and_media_are_maintainable_with_their_relationships(): void
    {
        $admin = $this->signInAsPlatformAdmin();
        $branch = School::factory()->create();
        $groups = '/api/admin/directory/school-groups';
        $group = $this->postJson($groups, ['name' => 'Campus Network'])->assertCreated()->json('data.id');
        $other = $this->postJson($groups, ['name' => 'Other Network'])->assertCreated()->json('data.id');
        $this->putJson("{$groups}/{$group}/branches/{$branch->id}")->assertOk();
        $this->putJson("{$groups}/{$other}/branches/{$branch->id}")->assertConflict();
        $this->patchJson("{$groups}/{$group}", ['status' => 'suspended'])->assertOk();
        $this->deleteJson("{$groups}/{$group}")->assertConflict();
        $this->deleteJson("{$groups}/{$other}")->assertNoContent();

        $fees = '/api/admin/directory/listing-fees';
        $level = $this->postJson('/api/admin/directory/education-levels', ['name' => 'Primary'])->assertCreated()->json('data.id');
        $fee = $this->postJson($fees, ['school_id' => $branch->id, 'education_level_id' => $level, 'monthly_fee' => '1234.50'])
            ->assertCreated()->assertJsonPath('data.currency', 'PKR')->json('data.id');
        $this->patchJson("{$fees}/{$fee}", ['last_confirmed_at' => '2026-11-19T11:30'])->assertOk()
            ->assertJsonPath('data.last_confirmed_at', '2026-11-19 11:30:00');
        $this->deleteJson("/api/admin/directory/education-levels/{$level}")->assertNoContent();
        $this->assertDatabaseHas('school_listing_fees', ['id' => $fee, 'education_level_id' => null]);
        $this->deleteJson("{$fees}/{$fee}")->assertNoContent();

        Storage::fake('public');
        $media = '/api/admin/directory/school-media';
        $this->postJson($media, ['school_id' => $branch->id, 'url' => 'javascript:alert(1)'])->assertJsonValidationErrors('url');
        $response = $this->post($media, [
            'school_id' => $branch->id, 'file' => UploadedFile::fake()->image('campus.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.uploaded_by_user_id', $admin->id);
        $mediaId = $response->json('data.id');
        Storage::disk('public')->assertExists($response->json('data.path'));
        $this->get("{$media}/{$mediaId}/download")->assertDownload(basename($response->json('data.path')));
        $replacement = $this->post("{$media}/{$mediaId}", [
            '_method' => 'PATCH', 'file' => UploadedFile::fake()->image('replacement.png'), 'is_approved' => '0',
        ], ['Accept' => 'application/json'])->assertOk();
        $this->assertNotSame($response->json('data.path'), $replacement->json('data.path'));
        Storage::disk('public')->assertMissing($response->json('data.path'));
        $this->assertDatabaseHas('school_media', ['id' => $mediaId, 'is_approved' => false]);
        $this->patchJson("{$media}/{$mediaId}", ['caption' => 'Main building'])->assertOk();
        $this->deleteJson("{$media}/{$mediaId}")->assertNoContent();
        Storage::disk('public')->assertMissing($replacement->json('data.path'));
        $this->assertDatabaseMissing('school_media', ['id' => $mediaId]);
    }

    public function test_location_and_group_foreign_key_delete_rules_match_the_workbook(): void
    {
        $city = DB::table('cities')->insertGetId(['name' => 'Test City', 'slug' => 'test-city']);
        $area = DB::table('areas')->insertGetId(['name' => 'Test Area', 'slug' => 'test-area', 'city_id' => $city]);
        $group = DB::table('school_groups')->insertGetId(['name' => 'Test Group', 'slug' => 'test-group']);
        $school = School::factory()->create(['group_id' => $group, 'area_id' => $area]);
        DB::table('cities')->where('id', $city)->delete();
        DB::table('school_groups')->where('id', $group)->delete();
        $this->assertDatabaseMissing('areas', ['id' => $area]);
        $this->assertDatabaseHas('schools', ['id' => $school->id, 'area_id' => null, 'group_id' => null]);
    }

    private function signInAsPlatformAdmin(): User
    {
        $admin = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);
        $this->actingAs($admin);

        return $admin;
    }
}
