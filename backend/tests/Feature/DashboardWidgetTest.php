<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\BranchModules;
use App\Services\Dashboard\DashboardContext;
use App\Services\Dashboard\WidgetRegistry;
use App\Services\SchoolRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardWidgetTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/admin/dashboard/widgets/';

    public function test_fixed_layouts_follow_the_role_even_when_platform_selects_a_branch(): void
    {
        $this->withoutVite();
        $school = School::factory()->create();
        $platform = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);
        foreach (['/admin/dashboard', "/admin/dashboard/{$school->id}"] as $url) {
            $this->actingAs($platform)->get($url)->assertInertia(fn (Assert $page) => $page
                ->has('widgets', 7)->where('widgets.0.key', 'platform.listings_by_status')
                ->where('widgets.1.key', 'platform.claims_queue')
                ->where('widgets.2.key', 'platform.verification_queue')
                ->where('widgets.3.key', 'platform.listings_by_city')
                ->where('widgets.4.key', 'platform.new_demo_requests')
                ->where('widgets.5.key', 'platform.subscription_watch')
                ->where('widgets.6.key', 'platform.module_adoption'));
        }

        foreach (['admin', 'teacher', 'account', 'student'] as $role) {
            $this->actingAs($this->member($school, $role))->get('/admin/dashboard')
                ->assertInertia(fn (Assert $page) => match ($role) {
                    'admin' => $page->has('widgets', 6)->where('widgets.0.key', 'branch.attendance_today')
                        ->where('widgets.1.key', 'branch.class_strength')
                        ->where('widgets.2.key', 'branch.attendance_trend')
                        ->where('widgets.3.key', 'branch.admissions_funnel')
                        ->where('widgets.4.key', 'branch.new_students_month')
                        ->where('widgets.5.key', 'branch.unmarked_classes'),
                    'teacher' => $page->has('widgets', 1)->where('widgets.0.key', 'branch.unmarked_classes'),
                    'student' => $page->has('widgets', 3)->where('widgets.0.key', 'student.attendance_month')
                        ->where('widgets.1.key', 'student.attendance_trend')->where('widgets.2.key', 'student.recent_absences'),
                    default => $page->has('widgets', 0),
                });
        }
    }

    public function test_module_adoption_matches_effective_entitlements_using_at_most_three_data_queries(): void
    {
        $this->travelTo(Carbon::parse('2026-12-15 12:00:00 UTC'));
        $platform = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);
        $plan = DB::table('plans')->insertGetId(['name' => 'Adoption plan', 'slug' => 'test-adoption']);
        $ids = DB::table('modules')->pluck('id', 'slug');
        foreach (['students', 'attendance'] as $key) {
            DB::table('plan_modules')->insert(['plan_id' => $plan, 'module_id' => $ids[$key]]);
        }

        $branches = collect([School::factory()->create(), School::factory()->create()]);
        DB::table('school_modules')->insert([
            'school_id' => $branches->first()->id, 'module_id' => $ids['students'], 'is_enabled' => false,
        ]);
        foreach (['grace', 'expired', 'future', 'unverified', 'no-subscription'] as $case) {
            $group = DB::table('school_groups')->insertGetId(['name' => $case, 'slug' => 'adoption-'.$case]);
            $branches->push(School::factory()->create([
                'group_id' => $group, 'verified_at' => $case === 'unverified' ? null : now()->subMonth(),
            ]));
            if ($case === 'no-subscription') {
                continue;
            }
            $subscription = DB::table('school_subscriptions')->insertGetId([
                'school_group_id' => $group, 'plan_id' => $plan,
                'starts_at' => $case === 'future' ? now()->addDay() : now()->subMonth(),
                'ends_at' => now()->subDay(),
                'grace_ends_at' => $case === 'expired' ? now() : now()->addDay(),
            ]);
            if ($case === 'grace') {
                DB::table('school_subscriptions')->insert([
                    'school_group_id' => $group, 'plan_id' => $plan, 'starts_at' => now()->addDay(),
                ]);
            }
            foreach (['academics' => now()->addDay(), 'staff' => now()] as $key => $end) {
                DB::table('subscription_modules')->insert([
                    'school_subscription_id' => $subscription, 'module_id' => $ids[$key],
                    'starts_at' => now()->subDay(), 'ends_at' => $end,
                ]);
            }
        }
        School::factory()->create()->delete();

        $expected = $branches->flatMap(fn (School $branch) => app(BranchModules::class)->forBranch($branch->id))
            ->where('enabled', true)->countBy('name');
        $widget = app(WidgetRegistry::class)->find('platform.module_adoption');
        $this->assertNotNull($widget);
        DB::enableQueryLog();
        try {
            $payload = $widget->data(new DashboardContext(null, $platform));
            $this->assertLessThanOrEqual(3, count(DB::getQueryLog()));
        } finally {
            DB::disableQueryLog();
        }

        foreach ($payload['data']['items'] as $item) {
            $this->assertSame($expected->get($item['label'], 0), $item['value'], $item['label']);
        }
        $this->assertCount($ids->count(), $payload['data']['items']);
        $this->actingAs($platform)->getJson(self::URL.'platform.module_adoption')->assertOk()
            ->assertJsonPath('data.items', $payload['data']['items']);
        $this->actingAs($this->member($branches->first()))->getJson(self::URL.'platform.module_adoption')->assertForbidden();
    }

    public function test_student_trend_and_absences_only_include_the_linked_students_distinct_dates(): void
    {
        config(['app.timezone' => 'Asia/Karachi']);
        $this->travelTo(Carbon::parse('2026-12-14 19:00:00 UTC'));
        $school = School::factory()->create();
        $foreign = School::factory()->create();
        $student = $this->member($school, 'student');
        $first = $this->enrollment($school);
        $second = $this->enrollment($school, ['student_id' => $first->student_id]);
        $classmate = $this->enrollment($school);
        $student->schools()->updateExistingPivot($school, ['student_id' => $first->student_id]);
        foreach ([
            [$first, '2026-11-15', 'absent'],
            [$first, '2026-11-16', 'absent'],
            [$second, '2026-11-16', 'absent'],
            [$first, '2026-12-12', 'excused'],
            [$first, '2026-12-13', 'late'],
            [$second, '2026-12-13', 'absent'],
            [$classmate, '2026-12-14', 'absent'],
            [$first, '2026-12-15', 'absent'],
            [$first, '2026-12-16', 'present'],
        ] as [$record, $date, $status]) {
            DB::table('attendance_records')->insert([
                'school_id' => $school->id, 'enrollment_id' => $record->id,
                'attendance_date' => $date, 'status' => $status,
            ]);
        }

        $branch = '?branch='.$school->id;
        $this->actingAs($student)->getJson(self::URL.'student.attendance_trend'.$branch)
            ->assertOk()->assertJsonCount(30, 'data.points')
            ->assertJsonPath('data.points.0', ['x' => '2026-11-16', 'y' => 0])
            ->assertJsonPath('data.points.26.y', null)->assertJsonPath('data.points.27.y', 100)
            ->assertJsonPath('data.points.28.y', null)->assertJsonPath('data.points.29.y', 0);
        $this->getJson(self::URL.'student.recent_absences'.$branch.'&student_id='.$classmate->student_id)
            ->assertOk()->assertJsonPath('data.rows', [
                ['title' => '2026-12-15', 'meta' => 'Absent'],
                ['title' => '2026-11-16', 'meta' => 'Absent'],
            ]);
        foreach (['student.attendance_trend', 'student.recent_absences'] as $key) {
            $this->actingAs($student)->getJson(self::URL.$key.'?branch='.$foreign->id)->assertForbidden();
            $this->actingAs($this->member($school))->getJson(self::URL.$key.$branch)->assertForbidden();
        }
    }

    public function test_platform_widgets_show_review_queue_city_counts_and_new_demo_requests(): void
    {
        $this->travelTo(Carbon::parse('2026-12-15 12:00:00 UTC'));
        $city = DB::table('cities')->insertGetId(['name' => 'Test City', 'slug' => 'test-city']);
        $school = School::factory()->create([
            'name' => 'Pending School', 'city_id' => $city, 'listing_status' => 'pending_review',
            'verified_at' => null, 'created_at' => now()->subDays(4),
        ]);
        School::factory()->create(['city_id' => $city, 'listing_status' => 'published', 'verified_at' => now()]);
        School::factory()->create(['city_id' => $city, 'listing_status' => 'draft', 'verified_at' => null])->delete();
        foreach (['new', 'contacted'] as $status) {
            DB::table('demo_requests')->insert(['name' => 'Test Request', 'phone' => '0000000000', 'status' => $status]);
        }

        $this->actingAs(User::factory()->create(['is_super_admin' => true, 'is_active' => true]));
        $this->getJson(self::URL.'platform.verification_queue')->assertOk()->assertJsonPath('data.rows', [
            ['title' => 'Pending School', 'meta' => '4 days'],
        ]);
        $this->getJson(self::URL.'platform.listings_by_city')->assertOk()
            ->assertJsonPath('data.items', [['label' => 'Test City', 'value' => 2]]);
        $this->getJson(self::URL.'platform.new_demo_requests')->assertOk()->assertJsonPath('data.value', 1);
        $this->actingAs($this->member($school));
        foreach (['platform.verification_queue', 'platform.listings_by_city', 'platform.new_demo_requests', 'platform.subscription_watch'] as $key) {
            $this->getJson(self::URL.$key.'?branch='.$school->id)->assertForbidden();
        }
    }

    public function test_subscription_watch_uses_the_latest_started_subscription_and_the_grace_and_expiry_windows(): void
    {
        $this->travelTo(Carbon::parse('2026-12-15 12:00:00 UTC'));
        $plan = DB::table('plans')->insertGetId(['name' => 'Test plan', 'slug' => 'test-plan']);
        foreach (['Ending soon', 'In grace', 'Ended', 'Superseded', 'Cancelled'] as $name) {
            $group = DB::table('school_groups')->insertGetId(['name' => $name, 'slug' => str($name)->slug()->toString()]);
            $subscription = ['school_group_id' => $group, 'plan_id' => $plan, 'starts_at' => now()->subMonth()];
            DB::table('school_subscriptions')->insert($subscription + match ($name) {
                'Ending soon', 'Superseded' => ['ends_at' => now()->addDays(14)],
                'In grace' => ['ends_at' => now()->subDay(), 'grace_ends_at' => now()->addDay()],
                'Ended' => ['ends_at' => now()->subDay(), 'grace_ends_at' => now()],
                'Cancelled' => ['status' => 'cancelled', 'ends_at' => now()->addDay()],
            });
            if ($name === 'Superseded') {
                DB::table('school_subscriptions')->insert(array_replace($subscription, ['starts_at' => now()->subDay()]));
            }
        }

        $this->actingAs(User::factory()->create(['is_super_admin' => true, 'is_active' => true]))
            ->getJson(self::URL.'platform.subscription_watch')->assertOk()->assertJsonPath('data.rows', [
                ['title' => 'In grace', 'meta' => 'Grace ends 2026-12-16'],
                ['title' => 'Ending soon', 'meta' => 'Ends 2026-12-29'],
            ]);
    }

    public function test_branch_widgets_aggregate_only_current_data_from_the_selected_branch(): void
    {
        $this->travelTo(Carbon::parse('2026-12-14 12:00:00 UTC'));
        $school = School::factory()->create();
        $other = School::factory()->create();
        $marked = $this->enrollment($school);
        $unmarked = $this->enrollment($school);
        $partial = $this->enrollment($school, [
            'grade_id' => $marked->grade_id, 'section_id' => $marked->section_id,
        ]);
        $foreign = $this->enrollment($other);
        foreach ([
            [$marked, '2026-11-30', 'present'],
            [$marked, '2026-12-01', 'present'],
            [$marked, '2026-12-14', 'late'],
            [$partial, '2026-12-01', 'absent'],
            [$foreign, '2026-12-14', 'absent'],
        ] as [$record, $date, $status]) {
            DB::table('attendance_records')->insert([
                'school_id' => $record->school_id, 'enrollment_id' => $record->id,
                'attendance_date' => $date, 'status' => $status,
            ]);
        }
        foreach ([[$school, 'new'], [$school, 'registered'], [$other, 'new']] as [$branch, $status]) {
            DB::table('student_inquiries')->insert([
                'school_id' => $branch->id, 'first_name' => 'Test', 'last_name' => 'Applicant',
                'inquiry_date' => '2026-12-01', 'status' => $status,
            ]);
        }
        Student::factory()->for($school)->create(['created_at' => '2026-11-30 00:00:00']);
        $admin = $this->member($school);
        $teacher = $this->member($school, 'teacher');
        $branch = '?branch='.$school->id;

        $this->actingAs($admin)->getJson(self::URL.'branch.attendance_trend'.$branch)
            ->assertOk()->assertJsonCount(14, 'data.points')
            ->assertJsonPath('data.points.0', ['x' => '2026-12-01', 'y' => 50])
            ->assertJsonPath('data.points.1.y', null)->assertJsonPath('data.points.13.y', 100);
        $this->getJson(self::URL.'branch.admissions_funnel'.$branch)->assertOk()
            ->assertJsonPath('data.items.0', ['label' => 'New', 'value' => 1])
            ->assertJsonPath('data.items.3', ['label' => 'Registered', 'value' => 1]);
        $this->getJson(self::URL.'branch.new_students_month'.$branch)->assertOk()->assertJsonPath('data.value', 3);
        $url = self::URL.'branch.unmarked_classes'.$branch;
        $this->getJson($url)->assertOk()->assertJsonPath('data.rows', [
            ['title' => $unmarked->grade->title.' / '.$unmarked->section->title, 'meta' => '1 of 1 unmarked'],
        ]);
        $this->actingAs($teacher)->getJson($url)->assertOk()->assertJsonCount(2, 'data.rows')
            ->assertJsonFragment(['title' => $marked->grade->title.' / '.$marked->section->title, 'meta' => '1 of 2 unmarked']);
    }

    public function test_branch_widgets_enforce_role_branch_and_module_on_layout_and_direct_requests(): void
    {
        $this->withoutVite();
        $school = School::factory()->create();
        $other = School::factory()->create();
        $admin = $this->member($school);
        $student = $this->member($school, 'student');
        $teacher = $this->member($school, 'teacher');
        $widgets = ['branch.attendance_trend', 'branch.admissions_funnel', 'branch.new_students_month', 'branch.unmarked_classes'];
        foreach ($widgets as $key) {
            $this->actingAs($admin)->getJson(self::URL.$key.'?branch='.$other->id)->assertForbidden();
            $this->actingAs($student)->getJson(self::URL.$key.'?branch='.$school->id)->assertForbidden();
        }

        foreach (['attendance', 'inquiries', 'students'] as $module) {
            DB::table('school_modules')->insert([
                'school_id' => $school->id, 'module_id' => DB::table('modules')->where('slug', $module)->value('id'),
                'is_enabled' => false,
            ]);
        }
        $this->actingAs($admin)->get('/admin/dashboard')->assertInertia(fn (Assert $page) => $page->has('widgets', 0));
        foreach ($widgets as $key) {
            $this->getJson(self::URL.$key.'?branch='.$school->id)->assertForbidden();
        }
        $this->actingAs($teacher)->get('/admin/dashboard')->assertInertia(fn (Assert $page) => $page->has('widgets', 0));
        $this->getJson(self::URL.'branch.unmarked_classes?branch='.$school->id)->assertForbidden();
    }

    public function test_student_monthly_attendance_counts_distinct_local_dates_only_for_the_linked_student(): void
    {
        config(['app.timezone' => 'Asia/Karachi']);
        $this->travelTo(Carbon::parse('2026-11-30 19:00:00 UTC'));
        $school = School::factory()->create();
        $other = School::factory()->create();
        $student = $this->member($school, 'student');
        $first = $this->enrollment($school);
        $second = $this->enrollment($school, ['student_id' => $first->student_id]);
        $classmate = $this->enrollment($school);
        $foreign = $this->enrollment($other);
        $student->schools()->updateExistingPivot($school, ['student_id' => $first->student_id]);

        foreach ([
            [$first, '2026-11-30', 'present'],
            [$first, '2026-12-01', 'present'],
            [$second, '2026-12-01', 'absent'],
            [$first, '2026-12-02', 'late'],
            [$first, '2026-12-03', 'absent'],
            [$first, '2026-12-04', 'excused'],
            [$first, '2027-01-01', 'present'],
            [$classmate, '2026-12-01', 'absent'],
            [$foreign, '2026-12-01', 'absent'],
        ] as [$record, $date, $status]) {
            DB::table('attendance_records')->insert([
                'school_id' => $record->school_id, 'enrollment_id' => $record->id,
                'attendance_date' => $date, 'status' => $status,
            ]);
        }

        $url = self::URL.'student.attendance_month?branch=';
        $this->actingAs($student)->getJson($url.$school->id.'&student_id='.$classmate->student_id)
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('key', 'student.attendance_month')->assertJsonPath('type', 'kpi')
            ->assertJsonPath('asOf', '2026-12-01T00:00:00+05:00')
            ->assertJsonPath('data.value', 67)->assertJsonPath('data.unit', '%')
            ->assertJsonPath('summary', '2 of 3 recorded days attended.');
        $this->getJson($url.$other->id)->assertForbidden();
        $this->actingAs($this->member($school))->getJson($url.$school->id)->assertForbidden();
    }

    public function test_student_attendance_has_an_empty_state_and_obeys_the_module_switch(): void
    {
        $this->withoutVite();
        $school = School::factory()->create();
        $student = $this->member($school, 'student');
        $url = self::URL.'student.attendance_month?branch='.$school->id;
        $this->actingAs($student)->getJson($url)->assertOk()
            ->assertJsonPath('data.value', null)->assertJsonPath('summary', 'No attendance recorded this month.');
        $branch = '?branch='.$school->id;
        $this->getJson(self::URL.'student.attendance_trend'.$branch)->assertOk()
            ->assertJsonPath('summary', 'No attendance recorded in the last 30 days.');
        $this->getJson(self::URL.'student.recent_absences'.$branch)->assertOk()->assertJsonPath('data.rows', []);

        DB::table('school_modules')->insert([
            'school_id' => $school->id,
            'module_id' => DB::table('modules')->where('slug', 'attendance')->value('id'),
            'is_enabled' => false,
        ]);

        $this->get('/admin/dashboard')->assertInertia(fn (Assert $page) => $page->has('widgets', 0));
        $this->getJson($url)->assertForbidden();
        $this->getJson(self::URL.'student.attendance_trend'.$branch)->assertForbidden();
        $this->getJson(self::URL.'student.recent_absences'.$branch)->assertForbidden();
    }

    public function test_unknown_keys_and_wrong_roles_are_denied_before_branch_validation(): void
    {
        $school = School::factory()->create();
        $url = self::URL.'branch.attendance_today?branch=bad';
        $this->get($url)->assertRedirect();
        $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
        $denied = $this->actingAs($this->member($school, 'teacher'))->getJson($url)->assertForbidden();
        $this->getJson(self::URL.'unknown?branch=bad')->assertForbidden()->assertExactJson($denied->json());
    }

    public function test_platform_admin_cannot_request_a_branch_widget(): void
    {
        $school = School::factory()->create();
        $platform = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);

        $this->actingAs($platform)->getJson(self::URL.'branch.attendance_today?branch='.$school->id)
            ->assertForbidden();
    }

    public function test_branch_admin_cannot_request_a_platform_widget(): void
    {
        $school = School::factory()->create();

        $this->actingAs($this->member($school))->getJson(self::URL.'platform.claims_queue')->assertForbidden();
    }

    public function test_branch_id_may_not_exceed_ten_digits(): void
    {
        $school = School::factory()->create();

        $this->actingAs($this->member($school))
            ->getJson(self::URL.'branch.attendance_today?branch=10000000000')->assertUnprocessable();
    }

    public function test_missing_or_nonnumeric_branch_is_invalid_and_unavailable_branches_share_a_denial(): void
    {
        $school = School::factory()->create();
        $other = School::factory()->create();
        $this->actingAs($this->member($school));
        $url = self::URL.'branch.attendance_today';
        $this->getJson($url)->assertUnprocessable();
        $this->getJson($url.'?branch=bad')->assertUnprocessable();
        $denied = $this->getJson($url.'?branch='.$other->id)->assertForbidden();
        $this->getJson($url.'?branch=99999')->assertForbidden()->assertExactJson($denied->json());
    }

    public function test_widget_permissions_and_module_switches_apply_to_layout_and_direct_requests(): void
    {
        $this->withoutVite();
        $school = School::factory()->create();
        $this->actingAs($this->member($school));
        $role = Role::query()->where('team_id', $school->id)->where('name', 'admin')->firstOrFail();
        $role->syncPermissions(['attendance.manage', 'students.view']);
        DB::table('school_modules')->insert([
            'school_id' => $school->id,
            'module_id' => DB::table('modules')->where('slug', 'students')->value('id'),
            'is_enabled' => false,
        ]);

        $this->get('/admin/dashboard')->assertInertia(fn (Assert $page) => $page
            ->has('widgets', 1)->where('widgets.0.key', 'branch.admissions_funnel'));
        foreach (['branch.attendance_today', 'branch.class_strength', 'branch.attendance_trend', 'branch.new_students_month', 'branch.unmarked_classes'] as $key) {
            $this->getJson(self::URL.$key.'?branch='.$school->id)->assertForbidden();
        }
    }

    public function test_attendance_uses_the_configured_local_date_across_midnight_and_month_end(): void
    {
        config(['app.timezone' => 'Asia/Karachi']);
        $school = School::factory()->create();
        $other = School::factory()->create();
        $this->actingAs($this->member($school));
        $role = Role::query()->where('team_id', $school->id)->where('name', 'admin')->firstOrFail();
        $role->syncPermissions(['attendance.view']);
        foreach ([[$school, '2026-11-30', 'late'], [$school, '2026-12-01', 'excused'], [$other, '2026-12-01', 'present']] as [$branch, $date, $status]) {
            $record = $this->enrollment($branch);
            DB::table('attendance_records')->insert([
                'school_id' => $branch->id, 'enrollment_id' => $record->id, 'attendance_date' => $date, 'status' => $status,
            ]);
        }

        $url = self::URL.'branch.attendance_today?branch='.$school->id;
        $this->travelTo(Carbon::parse('2026-11-30 18:59:00 UTC'));
        $this->getJson($url)->assertOk()->assertJsonPath('asOf', '2026-11-30T23:59:00+05:00')
            ->assertJsonPath('data.items.0.value', 0)->assertJsonPath('data.items.2.value', 1);
        $this->travelTo(Carbon::parse('2026-11-30 19:00:00 UTC'));
        $this->getJson($url)->assertOk()->assertJsonPath('asOf', '2026-12-01T00:00:00+05:00')
            ->assertJsonPath('data.items.0.value', 0)->assertJsonPath('data.items.2.value', 0)
            ->assertJsonPath('data.items.3.value', 1);
    }

    public function test_class_strength_uses_the_latest_active_roster_enrollment_in_the_selected_branch(): void
    {
        $school = School::factory()->create();
        $other = School::factory()->create();
        $this->actingAs($this->member($school));
        $first = $this->enrollment($school);
        $current = $this->enrollment($school, ['student_id' => $first->student_id]);
        $this->enrollment($school, ['status' => 'withdrawn']);
        $this->enrollment($other);
        $this->getJson(self::URL.'branch.class_strength?branch='.$school->id)->assertOk()
            ->assertJsonPath('data.items', [['label' => $current->grade->title, 'value' => 1]]);
    }

    public function test_class_strength_matches_the_unfiltered_rosters_active_students_by_current_grade(): void
    {
        $school = School::factory()->create();
        $this->actingAs($this->member($school));
        $first = $this->enrollment($school);
        $duplicate = $this->enrollment($school, ['student_id' => $first->student_id]);
        $previousYear = $this->enrollment($school, ['academic_year' => '2025-2026']);
        $withdrawn = $this->enrollment($school, ['status' => 'withdrawn']);
        $archived = Student::factory()->for($school)->create(['status' => 'inactive']);
        $this->enrollment($school, ['student_id' => $archived->id]);

        $roster = collect($this->getJson("/api/schools/{$school->id}/students")->assertOk()->json('data'))->keyBy('id');
        $this->assertSame($duplicate->id, $roster[$first->student_id]['current_enrollment']['id']);
        $this->assertSame('2025-2026', $roster[$previousYear->student_id]['current_enrollment']['academic_year']);
        $this->assertNull($roster[$withdrawn->student_id]['current_enrollment']);
        $this->assertSame('inactive', $roster[$archived->id]['status']);

        $counts = $roster->filter(fn (array $student) => $student['status'] === 'active' && $student['current_enrollment'] !== null)
            ->groupBy('current_enrollment.grade.id')
            ->map(fn ($students) => ['label' => $students->first()['current_enrollment']['grade']['title'], 'value' => $students->count()])
            ->values()->all();
        $response = $this->getJson(self::URL.'branch.class_strength?branch='.$school->id)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertEqualsCanonicalizing($counts, $response->json('data.items'));
    }

    public function test_platform_widgets_return_listing_status_and_pending_claim_age(): void
    {
        $this->travelTo(Carbon::parse('2026-11-30 12:00:00 UTC'));
        $school = School::factory()->create(['listing_status' => 'published']);
        $platform = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);
        foreach (['pending', 'approved'] as $status) {
            DB::table('listing_claims')->insert([
                'school_id' => $school->id, 'user_id' => $platform->id, 'claimant_name' => 'Fixture',
                'phone' => '00000000000', 'status' => $status, 'created_at' => now()->subDays(3),
            ]);
        }

        $this->actingAs($platform)->getJson(self::URL.'platform.listings_by_status')->assertOk()
            ->assertJsonStructure(['key', 'title', 'type', 'asOf', 'summary', 'data'])
            ->assertJsonPath('data.items', [['label' => 'Published', 'value' => 1]]);
        $this->getJson(self::URL.'platform.claims_queue')->assertOk()
            ->assertJsonCount(1, 'data.rows')->assertJsonPath('data.rows.0.meta', '3 days');
    }

    private function member(School $school, string $role = 'admin'): User
    {
        app(SchoolRoles::class)->ensureDefaults($school);
        $user = User::factory()->create(['is_active' => true]);
        $user->schools()->attach($school, ['role' => $role, 'is_active' => true]);
        setPermissionsTeamId($school->id);
        $user->assignRole($role);
        setPermissionsTeamId(null);

        return $user;
    }

    private function enrollment(School $school, array $attributes = []): Enrollment
    {
        $grade = Grade::factory()->for($school)->create();
        $section = Section::factory()->for($school)->create();
        DB::table('grade_section')->insert(['school_id' => $school->id, 'grade_id' => $grade->id, 'section_id' => $section->id]);

        return Enrollment::factory()->create([
            'school_id' => $school->id,
            'student_id' => $attributes['student_id'] ?? Student::factory()->for($school),
            'grade_id' => $grade->id,
            'section_id' => $section->id,
            ...$attributes,
        ]);
    }
}
