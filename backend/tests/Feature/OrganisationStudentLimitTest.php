<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\School;
use App\Models\SchoolSession;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganisationStudentLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_branches_share_the_last_seat_and_rejected_creates_leave_no_related_records(): void
    {
        [$first, $second] = $this->organisation(2);
        $outside = School::factory()->create();
        Student::factory()->for($outside)->create(['status' => 'active']);
        Student::factory()->for($first)->create(['status' => 'active']);
        Storage::fake('local');

        $this->postJson("/api/schools/{$second->id}/students", $this->student('QA-LAST'))->assertCreated();
        $this->postJson("/api/schools/{$first->id}/students", $this->student('QA-FULL') + [
            'guardian' => ['father_name' => 'Test Guardian', 'father_phone' => '0000000000'],
            'b_form_document' => UploadedFile::fake()->create('demo.pdf', 1, 'application/pdf'),
        ])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertDatabaseMissing('students', ['admission_number' => 'QA-FULL']);
        $this->assertDatabaseMissing('guardians', ['father_name' => 'Test Guardian']);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_subscription_overrides_and_non_active_student_changes_follow_the_cap(): void
    {
        [$first, $second, $subscription, $plan] = $this->organisation(1);
        $existing = Student::factory()->for($first)->create(['status' => 'active']);
        $pending = Student::factory()->for($second)->create(['status' => 'pending']);
        $path = "/api/schools/{$second->id}/students";
        $this->patchJson("$path/{$pending->id}", ['status' => 'active'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->patchJson("$path/{$pending->id}", ['first_name' => 'Changed'])->assertOk();
        $this->postJson($path, array_replace($this->student('QA-INACTIVE'), ['status' => 'inactive']))->assertCreated();

        DB::table('subscription_limits')->insert(['school_subscription_id' => $subscription, 'limit_key' => 'max_students', 'limit_value' => 0]);
        $this->patchJson("/api/schools/{$first->id}/students/{$existing->id}", ['status' => 'inactive'])->assertOk();
        $this->patchJson("$path/{$pending->id}", ['status' => 'active'])->assertUnprocessable();
        DB::table('subscription_limits')->where('school_subscription_id', $subscription)->update(['limit_value' => null]);
        $this->patchJson("$path/{$pending->id}", ['status' => 'active'])->assertOk();
        DB::table('subscription_limits')->where('school_subscription_id', $subscription)->delete();
        $this->patchJson("$path/{$pending->id}", ['status' => 'active', 'first_name' => 'Still active'])->assertOk();
        $this->postJson($path, $this->student('QA-AT-CAP'))->assertUnprocessable();
        DB::table('plan_limits')->where('plan_id', $plan)->delete();
        $this->postJson($path, $this->student('QA-NO-CAP'))->assertCreated();
    }

    public function test_rejoining_requires_capacity_and_withdrawal_frees_a_seat(): void
    {
        [$first, $second] = $this->organisation(1);
        $active = Student::factory()->for($first)->create(['status' => 'active']);
        $returning = Student::factory()->for($second)->create(['status' => 'leave']);
        $grade = Grade::factory()->for($second)->create();
        $section = Section::factory()->for($second)->create();
        DB::table('grade_section')->insert(['school_id' => $second->id, 'grade_id' => $grade->id, 'section_id' => $section->id]);
        SchoolSession::factory()->for($second)->create(['is_current' => true]);
        $date = today()->toDateString();
        $url = "/api/schools/{$second->id}/students/{$returning->id}/rejoin";
        $payload = ['grade_id' => $grade->id, 'section_id' => $section->id, 'event_date' => $date];
        $this->postJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertDatabaseHas('students', ['id' => $returning->id, 'status' => 'leave']);
        $this->assertDatabaseMissing('student_lifecycle_events', ['student_id' => $returning->id]);
        $this->assertDatabaseMissing('enrollments', ['student_id' => $returning->id]);
        $this->postJson("/api/schools/{$first->id}/students/{$active->id}/withdraw", ['event_date' => $date])->assertSuccessful();
        $this->postJson($url, $payload)->assertCreated();
        $this->assertDatabaseHas('students', ['id' => $returning->id, 'status' => 'active']);
    }

    public function test_archived_branches_are_excluded_but_disabled_branches_still_consume_capacity(): void
    {
        [$first, $second, $subscription, $plan] = $this->organisation(2);
        $archived = School::factory()->create(['group_id' => $first->group_id]);
        Student::factory()->for($archived)->create(['status' => 'active']);
        $archived->delete();
        Student::factory()->for($second)->create(['status' => 'active']);
        DB::table('school_modules')->insert([
            'school_id' => $second->id, 'module_id' => DB::table('modules')->where('slug', 'students')->value('id'),
            'is_enabled' => false,
        ]);
        $future = DB::table('school_subscriptions')->insertGetId([
            'school_group_id' => $first->group_id, 'plan_id' => $plan, 'starts_at' => now()->addDay(),
        ]);
        DB::table('subscription_limits')->insert(['school_subscription_id' => $future, 'limit_key' => 'max_students', 'limit_value' => 0]);
        $this->postJson("/api/schools/{$second->id}/students", $this->student('QA-DISABLED'))->assertForbidden();
        $this->postJson("/api/schools/{$first->id}/students", $this->student('QA-LAST'))->assertCreated();
        $this->postJson("/api/schools/{$first->id}/students", $this->student('QA-FULL'))->assertUnprocessable();
        $other = School::factory()->create();
        $this->postJson("/api/schools/{$other->id}/students", $this->student('QA-FOREIGN'))->assertNotFound();
        DB::table('school_subscriptions')->where('id', $subscription)->update([
            'ends_at' => now()->subHour(), 'grace_ends_at' => now()->addHour(),
        ]);
        $this->postJson("/api/schools/{$first->id}/students", $this->student('QA-GRACE'))->assertForbidden();
    }

    private function organisation(int $limit): array
    {
        $group = DB::table('school_groups')->insertGetId(['name' => 'Quota test', 'slug' => 'quota-test']);
        $plan = DB::table('plans')->insertGetId(['name' => 'Quota test', 'slug' => 'quota-test']);
        DB::table('plan_modules')->insert([
            'plan_id' => $plan, 'module_id' => DB::table('modules')->where('slug', 'students')->value('id'),
        ]);
        DB::table('plan_limits')->insert(['plan_id' => $plan, 'limit_key' => 'max_students', 'limit_value' => $limit]);
        $subscription = DB::table('school_subscriptions')->insertGetId([
            'school_group_id' => $group, 'plan_id' => $plan, 'starts_at' => now()->subDay(),
        ]);
        $schools = School::factory()->count(2)->create(['group_id' => $group, 'verified_at' => now()]);
        $user = User::factory()->create(['is_active' => true]);
        foreach ($schools as $school) {
            $user->schools()->attach($school, ['role' => 'admin', 'is_active' => true]);
        }
        $this->actingAs($user);

        return [$schools[0], $schools[1], $subscription, $plan];
    }

    private function student(string $number): array
    {
        return ['admission_number' => $number, 'first_name' => 'Test', 'last_name' => 'Student', 'status' => 'active'];
    }
}
