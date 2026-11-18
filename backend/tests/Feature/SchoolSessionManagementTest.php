<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SchoolSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolSessionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_admin_can_create_sessions_and_first_session_becomes_current(): void
    {
        $school = School::factory()->create();
        $admin = $this->sessionAdmin($school);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/schools/{$school->id}/sessions", [
                'title' => '2026–2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-06-30',
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_current', true)
            ->assertJsonPath('data.current_marker', $school->id);
    }

    public function test_session_dates_must_be_ordered_and_must_not_overlap(): void
    {
        $school = School::factory()->create();
        $admin = $this->sessionAdmin($school);
        $this->actingAs($admin, 'sanctum');

        $this->postJson("/api/schools/{$school->id}/sessions", [
            'title' => 'Invalid', 'starts_on' => '2026-08-01', 'ends_on' => '2026-08-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('ends_on');

        $this->postJson("/api/schools/{$school->id}/sessions", [
            'title' => '2026–2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-06-30',
        ])->assertCreated();
        $this->postJson("/api/schools/{$school->id}/sessions", [
            'title' => 'Overlap', 'starts_on' => '2027-06-30', 'ends_on' => '2028-06-30',
        ])->assertUnprocessable()->assertJsonValidationErrors('starts_on');
    }

    public function test_current_session_switch_is_atomic_and_exactly_one_remains_current(): void
    {
        $school = School::factory()->create();
        $admin = $this->sessionAdmin($school);
        $current = SchoolSession::factory()->for($school)->create([
            'title' => '2025–2026', 'starts_on' => '2025-08-01', 'ends_on' => '2026-06-30',
            'is_current' => true, 'current_marker' => $school->id,
        ]);
        $next = SchoolSession::factory()->for($school)->create([
            'title' => '2026–2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-06-30',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/schools/{$school->id}/sessions/{$next->id}", ['is_current' => true])
            ->assertOk()->assertJsonPath('data.is_current', true);

        $this->assertDatabaseHas('school_sessions', ['id' => $current->id, 'is_current' => false, 'current_marker' => null]);
        $this->assertDatabaseHas('school_sessions', ['id' => $next->id, 'is_current' => true, 'current_marker' => $school->id]);
    }

    public function test_current_session_cannot_be_unset_or_archived_until_another_is_current(): void
    {
        $school = School::factory()->create();
        $admin = $this->sessionAdmin($school);
        $session = SchoolSession::factory()->for($school)->create([
            'is_current' => true, 'current_marker' => $school->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/schools/{$school->id}/sessions/{$session->id}", ['is_current' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('is_current');
        $this->deleteJson("/api/schools/{$school->id}/sessions/{$session->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('school_session');
    }

    public function test_teachers_can_view_but_cannot_manage_sessions(): void
    {
        $school = School::factory()->create();
        $teacher = User::factory()->create();
        $teacher->schools()->attach($school, ['role' => 'teacher']);
        $session = SchoolSession::factory()->for($school)->create();

        $this->actingAs($teacher, 'sanctum')
            ->getJson("/api/schools/{$school->id}/sessions")
            ->assertOk();
        $this->postJson("/api/schools/{$school->id}/sessions", [
            'title' => 'Next', 'starts_on' => '2030-01-01', 'ends_on' => '2030-12-31',
        ])->assertForbidden();
        $this->patchJson("/api/schools/{$school->id}/sessions/{$session->id}", ['status' => 'inactive'])->assertForbidden();
    }

    public function test_school_members_cannot_read_another_schools_sessions(): void
    {
        $school = School::factory()->create();
        $foreign = School::factory()->create();
        $admin = $this->sessionAdmin($school);
        SchoolSession::factory()->for($foreign)->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/schools/{$foreign->id}/sessions")
            ->assertNotFound();
    }

    private function sessionAdmin(School $school): User
    {
        $user = User::factory()->create();
        $user->schools()->attach($school, ['role' => 'school_admin']);

        return $user;
    }
}
