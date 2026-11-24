<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\SchoolRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportCentreTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_require_branch_access_role_and_explicit_legacy_enablement(): void
    {
        $this->withoutVite();
        $school = School::factory()->create();
        $other = School::factory()->create();
        $url = '/admin/reports/'.$school->id;
        $this->assertDatabaseHas('modules', ['slug' => 'reports', 'is_core' => false]);
        $this->get($url)->assertRedirect();
        $this->actingAs($this->member($school, 'admin'))->get($url)->assertForbidden();
        $this->switchModule($school, 'reports', true);
        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Reports')->where('branch.id', $school->id)->where('canViewAttendance', true));
        $this->get('/admin/reports/'.$other->id)->assertForbidden();
        foreach (['teacher', 'student'] as $role) {
            $this->actingAs($this->member($school, $role))->get($url)->assertForbidden();
        }
        $this->actingAs($this->member($school, 'account'))->get($url)->assertInertia(
            fn (Assert $page) => $page->where('canViewAttendance', false)
        );
        $this->get($url.'/csv')->assertForbidden();
        $this->actingAs(User::factory()->create(['is_super_admin' => true, 'is_active' => true]))
            ->get($url.'/print')->assertInertia(fn (Assert $page) => $page->where('print', true));
        $this->switchModule($school, 'reports', false);
        foreach (['', '/csv', '/print'] as $suffix) {
            $this->get($url.$suffix)->assertForbidden();
        }
    }

    public function test_attendance_report_filters_and_exports_use_the_same_authorized_rows(): void
    {
        $this->withoutVite();
        $school = School::factory()->create();
        $other = School::factory()->create();
        $grade = Grade::factory()->for($school)->create(['title' => '=Grade']);
        $section = Section::factory()->for($school)->create(['title' => 'Section A']);
        DB::table('grade_section')->insert(['school_id' => $school->id, 'grade_id' => $grade->id, 'section_id' => $section->id]);
        $enrollment = Enrollment::factory()->create([
            'school_id' => $school->id, 'student_id' => Student::factory()->for($school),
            'grade_id' => $grade->id, 'section_id' => $section->id, 'academic_year' => '2026-2027',
        ]);
        foreach (['2026-11-23' => 'present', '2026-11-24' => 'absent'] as $date => $status) {
            DB::table('attendance_records')->insert([
                'school_id' => $school->id, 'enrollment_id' => $enrollment->id, 'attendance_date' => $date, 'status' => $status,
            ]);
        }
        $this->switchModule($school, 'reports', true);
        $url = '/admin/reports/'.$school->id;
        $filters = '?from=2026-11-24&to=2026-11-24&session=2026-2027&grade='.$grade->id;
        $this->actingAs($this->member($school, 'admin'))->get($url.$filters)->assertInertia(
            fn (Assert $page) => $page->where('rows', [['2026-11-24', '=Grade', 'Section A', 0, 1, 0, 0]])
        );
        $csv = $this->get($url.'/csv'.$filters)->assertOk()->assertDownload('attendance.csv')->streamedContent();
        $this->assertStringContainsString("2026-11-24,'=Grade,\"Section A\",0,1,0,0", $csv);
        $this->assertStringNotContainsString('2026-11-23', $csv);
        $this->get($url.'/print'.$filters)->assertInertia(fn (Assert $page) => $page->where('print', true)->has('rows', 1));
        $foreignGrade = Grade::factory()->for($other)->create();
        $this->getJson($url.'?grade='.$foreignGrade->id)->assertUnprocessable()->assertJsonValidationErrors('grade');
        $this->getJson($url.'/csv?from=2026-11-24&to=2026-11-23')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->switchModule($school, 'attendance', false);
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('canViewAttendance', false));
        $this->get($url.'/csv')->assertForbidden();
        $this->get($url.'/print')->assertForbidden();
    }

    public function test_subscription_grants_and_organisation_roles_are_scoped_to_their_own_group(): void
    {
        $this->withoutVite();
        $group = DB::table('school_groups')->insertGetId(['name' => 'Test organisation', 'slug' => 'test-org']);
        $school = School::factory()->create(['group_id' => $group, 'verified_at' => now()]);
        $other = School::factory()->create();
        $this->switchModule($other, 'reports', true);
        $plan = DB::table('plans')->insertGetId(['name' => 'Starter', 'slug' => 'starter-test']);
        DB::table('plan_modules')->insert([
            'plan_id' => $plan, 'module_id' => DB::table('modules')->where('slug', 'attendance')->value('id'),
        ]);
        $subscription = DB::table('school_subscriptions')->insertGetId([
            'school_group_id' => $group, 'plan_id' => $plan, 'starts_at' => now()->subDay(),
        ]);
        foreach (['owner', 'group_admin', 'group_viewer'] as $role) {
            $user = User::factory()->create(['is_active' => true]);
            DB::table('school_group_user')->insert(['school_group_id' => $group, 'user_id' => $user->id, 'role' => $role]);
            $users[$role] = $user;
        }
        $this->switchModule($school, 'reports', true);
        $this->actingAs($users['owner'])->get('/admin/reports/'.$school->id)->assertForbidden();
        DB::table('subscription_modules')->insert([
            'school_subscription_id' => $subscription,
            'module_id' => DB::table('modules')->where('slug', 'reports')->value('id'),
            'starts_at' => now()->subHour(),
        ]);
        foreach ($users as $role => $user) {
            $this->actingAs($user)->get('/admin/reports')->assertInertia(fn (Assert $page) => $page
                ->where('branch.id', $school->id)->has('branches', 1)->where('canViewAttendance', $role !== 'group_viewer'));
            $this->get('/admin/reports/'.$other->id.'/csv')->assertForbidden();
        }
    }

    private function member(School $school, string $role): User
    {
        app(SchoolRoles::class)->ensureDefaults($school);
        $user = User::factory()->create(['is_active' => true]);
        $user->schools()->attach($school, ['role' => $role, 'is_active' => true]);
        setPermissionsTeamId($school->id);
        $user->assignRole($role);
        setPermissionsTeamId(null);

        return $user;
    }

    private function switchModule(School $school, string $slug, bool $enabled): void
    {
        DB::table('school_modules')->updateOrInsert([
            'school_id' => $school->id, 'module_id' => DB::table('modules')->where('slug', $slug)->value('id'),
        ], ['is_enabled' => $enabled]);
    }
}
