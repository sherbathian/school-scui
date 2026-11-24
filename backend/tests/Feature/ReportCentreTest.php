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

    public function test_monthly_and_low_attendance_reports_reconcile_with_filtered_records_and_exports(): void
    {
        $this->withoutVite();
        $school = School::factory()->create();
        $grade = Grade::factory()->for($school)->create(['title' => 'Grade A']);
        $section = Section::factory()->for($school)->create(['title' => 'Section A']);
        DB::table('grade_section')->insert(['school_id' => $school->id, 'grade_id' => $grade->id, 'section_id' => $section->id]);
        $student = Student::factory()->for($school)->create([
            'admission_number' => 'TEST-100', 'first_name' => 'Test', 'last_name' => 'Student',
        ]);
        $enrollment = Enrollment::factory()->create([
            'school_id' => $school->id, 'student_id' => $student->id, 'grade_id' => $grade->id,
            'section_id' => $section->id, 'academic_year' => '2026-2027',
        ]);
        foreach (['2026-10-31' => 'present', '2026-11-01' => 'absent', '2026-11-02' => 'late', '2026-11-03' => 'excused'] as $date => $status) {
            DB::table('attendance_records')->insert([
                'school_id' => $school->id, 'enrollment_id' => $enrollment->id,
                'attendance_date' => $date, 'status' => $status,
            ]);
        }
        $previousEnrollment = Enrollment::factory()->create([
            'school_id' => $school->id, 'student_id' => $student->id, 'grade_id' => $grade->id,
            'section_id' => $section->id, 'academic_year' => '2025-2026',
        ]);
        DB::table('attendance_records')->insert([
            'school_id' => $school->id, 'enrollment_id' => $previousEnrollment->id,
            'attendance_date' => '2026-11-02', 'status' => 'absent',
        ]);
        $this->switchModule($school, 'reports', true);
        $url = '/admin/reports/'.$school->id;
        $filters = '&from=2026-10-31&to=2026-11-30&session=2026-2027&grade='.$grade->id.'&section='.$section->id;
        $this->actingAs($this->member($school, 'admin'))->get($url.'?report=monthly'.$filters)->assertInertia(
            fn (Assert $page) => $page->where('report', 'monthly')->where('rows', [
                ['2026-10', 'Grade A', 'Section A', 1, 0, 0, 0, 100],
                ['2026-11', 'Grade A', 'Section A', 0, 1, 1, 1, 50],
            ])
        );
        $monthlyCsv = $this->get($url.'/csv?report=monthly'.$filters)->assertDownload('attendance-monthly.csv')->streamedContent();
        $this->assertStringContainsString('2026-11,"Grade A","Section A",0,1,1,1,50', $monthlyCsv);
        $lowFilters = '&from=2026-11-01&to=2026-11-30';
        $rows = [['TEST-100', 'Test Student', 2, 1, 50]];
        $this->get($url.'?report=low-attendance'.$lowFilters)->assertInertia(fn (Assert $page) => $page
            ->where('report', 'low-attendance')->where('filters.threshold', 75)->where('rows', $rows));
        $this->get($url.'?report=low-attendance&threshold=50'.$lowFilters)->assertInertia(fn (Assert $page) => $page->where('rows', []));
        $this->get($url.'/print?report=low-attendance'.$lowFilters)->assertInertia(fn (Assert $page) => $page
            ->where('print', true)->where('rows', $rows));
        $csv = $this->get($url.'/csv?report=low-attendance'.$lowFilters)->assertDownload('low-attendance.csv')->streamedContent();
        $this->assertStringContainsString('TEST-100,"Test Student",2,1,50', $csv);
        $this->getJson($url.'?report=low-attendance&threshold=101')->assertUnprocessable()->assertJsonValidationErrors('threshold');
        $this->getJson($url.'?report=invalid')->assertUnprocessable()->assertJsonValidationErrors('report');
        $this->switchModule($school, 'attendance', false);
        foreach (['', '/csv', '/print'] as $suffix) {
            $this->get($url.$suffix.'?report=low-attendance')->assertForbidden();
        }
    }

    public function test_student_and_admissions_reports_use_branch_records_and_source_modules(): void
    {
        $this->withoutVite();
        $school = School::factory()->create();
        $other = School::factory()->create();
        $grade = Grade::factory()->for($school)->create(['title' => 'Grade A']);
        $section = Section::factory()->for($school)->create(['title' => 'Section A']);
        DB::table('grade_section')->insert(['school_id' => $school->id, 'grade_id' => $grade->id, 'section_id' => $section->id]);
        $student = Student::factory()->for($school)->create(['admission_number' => 'TEST-1', 'first_name' => 'Test', 'last_name' => 'Student']);
        $attributes = ['school_id' => $school->id, 'student_id' => $student->id, 'grade_id' => $grade->id, 'section_id' => $section->id];
        Enrollment::factory()->create($attributes + ['academic_year' => '2025-2026', 'enrolled_on' => '2026-01-01']);
        Enrollment::factory()->create($attributes + ['academic_year' => '2026-2027', 'enrolled_on' => '2026-11-01']);
        DB::table('student_lifecycle_events')->insert([
            'school_id' => $school->id, 'student_id' => $student->id, 'event_type' => 'withdrawal', 'event_date' => '2026-11-12',
        ]);
        foreach ([[$school, 'new', '2026-11-05'], [$school, 'registered', '2026-10-05'], [$other, 'new', '2026-11-05']] as [$branch, $status, $date]) {
            DB::table('student_inquiries')->insert([
                'school_id' => $branch->id, 'first_name' => 'Test', 'last_name' => 'Inquiry', 'status' => $status, 'inquiry_date' => $date,
            ]);
        }
        $this->switchModule($school, 'reports', true);
        $url = '/admin/reports/'.$school->id;
        $dates = '&from=2026-11-01&to=2026-11-30';
        $this->actingAs($this->member($school, 'admin'))->get($url.'?report=student-strength')
            ->assertInertia(fn (Assert $page) => $page->where('rows', [['Grade A', 'Section A', 1]]));
        $this->get($url.'?report=student-movement'.$dates)->assertInertia(fn (Assert $page) => $page
            ->where('rows', [['2026-11-12', 'Withdrawn', 'TEST-1', 'Test Student', 'Grade A', 'Section A']]));
        $this->get($url.'/csv?report=student-movement&from=2026-01-01&to=2026-11-30')
            ->assertDownload('student-movement.csv')
            ->assertStreamedContent("Date,Movement,\"Admission number\",Student,Grade,Section\n2026-01-01,New,TEST-1,\"Test Student\",\"Grade A\",\"Section A\"\n2026-11-12,Withdrawn,TEST-1,\"Test Student\",\"Grade A\",\"Section A\"\n");
        $this->get($url.'/print?report=admissions-funnel'.$dates)->assertInertia(fn (Assert $page) => $page
            ->where('rows.0', ['New', 1])->where('rows.3', ['Registered', 0]));
        $this->switchModule($school, 'students', false);
        $this->get($url.'/csv?report=student-strength')->assertForbidden();
        $this->switchModule($school, 'inquiries', false);
        $this->get($url.'/print?report=admissions-funnel')->assertForbidden();
    }

    public function test_staff_reports_filter_leave_by_session_and_exclude_other_branch_records(): void
    {
        $this->withoutVite();
        $school = School::factory()->create();
        $other = School::factory()->create();
        foreach ([$school, $other] as $branch) {
            $department = DB::table('departments')->insertGetId(['school_id' => $branch->id, 'title' => 'Teaching']);
            $designation = DB::table('designations')->insertGetId(['school_id' => $branch->id, 'title' => 'Teacher']);
            $staff = DB::table('staff')->insertGetId([
                'school_id' => $branch->id, 'department_id' => $department, 'designation_id' => $designation,
                'staff_no' => 'TEST-'.$branch->id, 'first_name' => 'Test', 'last_name' => 'Staff',
                'gender' => 'female', 'birth_date' => '1990-01-01', 'joining_date' => '2025-08-01',
            ]);
            $leaveType = DB::table('leave_types')->insertGetId(['school_id' => $branch->id, 'title' => 'Annual leave']);
            $session = DB::table('school_sessions')->insertGetId([
                'school_id' => $branch->id, 'title' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31',
            ]);
            DB::table('staff_leave_balances')->insert([
                'staff_id' => $staff, 'leave_type_id' => $leaveType, 'school_session_id' => $session,
                'allocated' => 10.5, 'used' => 2,
            ]);
        }
        $older = DB::table('school_sessions')->insertGetId([
            'school_id' => $school->id, 'title' => '2025-2026', 'starts_on' => '2025-08-01', 'ends_on' => '2026-07-31',
        ]);
        DB::table('staff_leave_balances')->insert([
            'staff_id' => DB::table('staff')->where('school_id', $school->id)->value('id'),
            'leave_type_id' => DB::table('leave_types')->where('school_id', $school->id)->value('id'),
            'school_session_id' => $older, 'allocated' => 5, 'used' => 1,
        ]);
        DB::table('staff_leave_balances')->insert([
            'staff_id' => DB::table('staff')->where('school_id', $school->id)->value('id'),
            'leave_type_id' => DB::table('leave_types')->where('school_id', $other->id)->value('id'),
            'school_session_id' => DB::table('school_sessions')->where('school_id', $school->id)->where('title', '2026-2027')->value('id'),
            'allocated' => 20, 'used' => 1,
        ]);
        $this->switchModule($school, 'reports', true);
        $url = '/admin/reports/'.$school->id;
        $this->actingAs($this->member($school, 'admin'))->get($url.'?report=staff-directory')
            ->assertInertia(fn (Assert $page) => $page->where('rows', [
                ['TEST-'.$school->id, 'Test Staff', 'Teaching', 'Teacher', '2025-08-01', 'active'],
            ]));
        $query = '?report=staff-leave&session=2026-2027';
        $rows = [['TEST-'.$school->id, 'Test Staff', 'Annual leave', '2026-2027', '10.5', '2.0', '8.5']];
        $this->get($url.$query)->assertInertia(fn (Assert $page) => $page
            ->where('rows', $rows)->where('sessions', ['2026-2027', '2025-2026']));
        $this->get($url.'/print'.$query)->assertInertia(fn (Assert $page) => $page->where('rows', $rows));
        $csv = $this->get($url.'/csv'.$query)->assertDownload('staff-leave.csv')->streamedContent();
        $this->assertStringContainsString('TEST-'.$school->id.',"Test Staff","Annual leave",2026-2027,10.5,2.0,8.5', $csv);
        $this->getJson($url.'?report=staff-leave&session=invalid')->assertUnprocessable()->assertJsonValidationErrors('session');
        $this->actingAs($this->member($school, 'account'))->get($url.'/csv'.$query)->assertForbidden();
        $this->actingAs($this->member($school, 'admin'));
        $this->switchModule($school, 'staff', false);
        foreach (['', '/csv', '/print'] as $suffix) {
            $this->get($url.$suffix.$query)->assertForbidden();
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
