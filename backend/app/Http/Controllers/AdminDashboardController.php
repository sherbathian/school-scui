<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Services\BranchModules;
use App\Services\Dashboard\WidgetRegistry;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request, BranchModules $modules, WidgetRegistry $widgets, ?int $branch = null): Response
    {
        $user = $request->user();
        abort_unless($user->canEnterSchoolhouse(), 403);

        $platform = Gate::forUser($user)->allows('moderateListings', School::class);
        $schools = ($platform ? School::query() : $user->activeSchools())->orderBy('name')->get();
        $school = $schools->find($branch);
        abort_if($branch !== null && ! $school, 404);

        $enabledByBranch = [];
        $enabledFor = function (School $item) use ($modules, &$enabledByBranch): array {
            return $enabledByBranch[$item->id] ??= $modules->forBranch($item->id)->where('enabled', true)->pluck('slug')->all();
        };
        if (! $school && ! $platform) {
            $school = $schools->sortByDesc(fn (School $item) => count(array_intersect(
                $user->schoolhouseOperationModules($item),
                $enabledFor($item),
            )))->first();
            abort_unless($school, 403);
        }

        $role = $platform ? 'Super Admin' : match ($user->schoolAccessRole($school)) {
            'admin', 'school_admin' => 'Branch Admin',
            'teacher' => 'Teacher',
            'account', 'accountant' => 'Accountant',
            'student' => 'Student',
            'owner' => 'Organisation Owner',
            'group_admin' => 'Organisation Admin',
            'group_accountant' => 'Organisation Accountant',
            'group_viewer' => 'Organisation Viewer',
            default => 'Branch Member',
        };

        $enabled = $school ? $enabledFor($school) : [];
        $permissions = $school && $role !== 'Student' ? $user->permissionsForSchool($school) : [];
        $scope = fn (string $table) => DB::table($table)->where('school_id', $school->id);
        $month = now()->startOfMonth();
        $nextMonth = $month->copy()->addMonth();
        $cards = [];
        $studentLinked = false;

        if (! $school) {
            $cards = [
                'Institution records' => $schools->count(),
                'Verified listings' => $schools->where('listing_status', 'published')->where('status', 'active')->whereNotNull('verified_at')->count(),
                'Pending claims' => DB::table('listing_claims')->where('status', 'pending')->count(),
            ];
        } else {
            if (in_array('academics', $enabled, true) && in_array('academics.manage', $permissions, true)) {
                $cards['Grades'] = $scope('grades')->count();
            }
            if (in_array('students', $enabled, true) && in_array('students.manage', $permissions, true)) {
                $cards['Active students'] = $scope('students')->where('status', 'active')->count();
            }
            if (in_array('staff', $enabled, true) && in_array('staff.manage', $permissions, true)) {
                $cards['Staff records'] = $scope('staff')->count();
            }
            if (in_array('attendance', $enabled, true) && in_array('attendance.manage', $permissions, true)) {
                $attendance = $scope('attendance_records')->where('attendance_date', today()->toDateString());
                $cards[$role === 'Teacher' ? 'Attendance entries you marked today' : 'Attendance entries today'] = $attendance
                    ->when($role === 'Teacher', fn ($query) => $query->where('marked_by_user_id', $user->id))->count();
            }
            if (in_array('fees', $enabled, true) && in_array('fees.manage', $permissions, true) && $role !== 'Teacher') {
                $cards['Unpaid fee invoices'] = $this->unpaidInvoices($school)->count();
                $received = $scope('fee_payments')->where('paid_on', '>=', $month)->where('paid_on', '<', $nextMonth)->sum('amount_cents');
                $cards['Fees received this month'] = 'PKR '.number_format($received / 100, 2);
            }
            if (in_array('expenses', $enabled, true) && in_array('expenses.manage', $permissions, true) && $role !== 'Teacher') {
                $cards['Expenses recorded this month'] = $scope('expenses')->where('expense_date', '>=', $month)
                    ->where('expense_date', '<', $nextMonth)->count();
            }
            if ($role === 'Student') {
                $cards['Campus'] = $school->name;
                $student = $school->students()->join('school_user', function ($join): void {
                    $join->on('school_user.student_id', '=', 'students.id')->on('school_user.school_id', '=', 'students.school_id');
                })->where('school_user.user_id', $user->id)->where('school_user.is_active', true)
                    ->where('school_user.role', 'student')->first(['students.id']);
                $studentLinked = $student !== null;
                if ($student && in_array('attendance', $enabled, true)) {
                    $attendance = $scope('attendance_records')->whereIn('enrollment_id', $scope('enrollments')
                        ->where('student_id', $student->id)->select('id'))->where('attendance_date', '>=', $month)
                        ->where('attendance_date', '<', $nextMonth);
                    $cards['Days present this month'] = $attendance->where('status', 'present')->distinct()->count('attendance_date');
                }
                if ($student && in_array('fees', $enabled, true)) {
                    $cards['My unpaid fee invoices'] = $this->unpaidInvoices($school)->where('student_id', $student->id)->count();
                }
            }
        }

        Inertia::setRootView('admin.app');

        return Inertia::render('Admin/Dashboard', [
            'adminName' => $user->name,
            'platform' => $platform,
            'role' => $role,
            'branch' => $school?->only('id', 'name'),
            'branches' => $schools->map->only('id', 'name')->values(),
            'cards' => $cards,
            'widgets' => $widgets->layout($user, $platform, $school),
            'studentLinked' => $studentLinked,
            'schoolAccess' => $school ? array_values(array_filter(['members', 'roles'], fn (string $screen) => in_array(
                $screen === 'members' ? 'users.manage' : 'roles.manage', $permissions, true,
            ))) : [],
            'operations' => $school !== null && ($platform || array_intersect($user->schoolhouseOperationModules($school), $enabled) !== []),
            'reportDate' => today()->toDateString(),
        ])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }

    private function unpaidInvoices(School $school): Builder
    {
        return DB::table('fee_invoices')->where('school_id', $school->id)->where('status', 'open')
            ->whereRaw('amount_cents > (select coalesce(sum(fee_payments.amount_cents), 0) from fee_payments where fee_payments.school_id = fee_invoices.school_id and fee_payments.fee_invoice_id = fee_invoices.id)');
    }
}
