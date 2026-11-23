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
                ->has('widgets', 2)->where('widgets.0.key', 'platform.listings_by_status')
                ->where('widgets.1.key', 'platform.claims_queue'));
        }

        foreach (['admin', 'teacher', 'account', 'student'] as $role) {
            $this->actingAs($this->member($school, $role))->get('/admin/dashboard')
                ->assertInertia(fn (Assert $page) => $role === 'admin'
                    ? $page->has('widgets', 2)->where('widgets.0.key', 'branch.attendance_today')
                        ->where('widgets.1.key', 'branch.class_strength')
                    : $page->has('widgets', 0));
        }
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

        $this->get('/admin/dashboard')->assertInertia(fn (Assert $page) => $page->has('widgets', 0));
        foreach (['branch.attendance_today', 'branch.class_strength'] as $key) {
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
