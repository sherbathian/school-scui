<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BranchAccountingPeriods;
use App\Services\BranchModules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class BranchAccountingPeriodController extends Controller
{
    public function index(Request $request, int $school, BranchModules $modules): JsonResponse
    {
        $this->authorizePilot($request, $school, $modules, writing: false);
        $rows = DB::table('schools')
            ->leftJoin('branch_accounting_settings as settings', 'settings.school_id', '=', 'schools.id')
            ->leftJoin('branch_accounting_periods as periods', 'periods.school_id', '=', 'schools.id')
            ->where('schools.id', $school)->orderByDesc('periods.month')
            ->get(['settings.timezone', 'periods.id', 'periods.month', 'periods.starts_on', 'periods.ends_on', 'periods.open_marker']);
        $periods = $rows->whereNotNull('id')->values()->map(fn ($row): array => [
            'id' => (int) $row->id,
            'month' => $row->month,
            'starts_on' => $row->starts_on,
            'ends_on' => $row->ends_on,
            'status' => $row->open_marker === 'open' ? 'open' : 'closed',
        ]);

        return response()->json(['data' => ['timezone' => $rows->first()?->timezone, 'periods' => $periods]])
            ->header('Cache-Control', 'no-store');
    }

    public function configureTimezone(Request $request, int $school, BranchModules $modules, BranchAccountingPeriods $calendar): Response
    {
        $this->authorizePilot($request, $school, $modules, writing: true);
        $data = $request->validate(['timezone' => ['required', 'string', 'max:64']]);
        $calendar->configureTimezone($school, $data['timezone']);

        return response()->noContent();
    }

    public function store(Request $request, int $school, BranchModules $modules, BranchAccountingPeriods $calendar): JsonResponse
    {
        $this->authorizePilot($request, $school, $modules, writing: true);
        $data = $request->validate(['month' => ['required', 'string', 'size:7']]);
        $id = $calendar->open($request->user()->id, $school, $data['month']);

        return response()->json(['data' => ['period_id' => $id]]);
    }

    public function close(Request $request, int $school, int $period, BranchModules $modules, BranchAccountingPeriods $calendar): Response
    {
        $this->authorizePilot($request, $school, $modules, writing: true);
        $calendar->close($request->user()->id, $school, $period);

        return response()->noContent();
    }

    private function authorizePilot(Request $request, int $school, BranchModules $modules, bool $writing): void
    {
        $user = $request->user();
        $branch = $user->activeSchools()->whereKey($school)->first();
        abort_unless($branch, 404);
        abort_unless($user->schoolAccessRole($branch) !== 'student' && $user->checkPermissionTo('fees.period.manage', 'web'), 403);
        $modules->assertEnabled($school, 'fees', writing: $writing);

        $pilot = config('billing.receipt_pilot');
        abort_unless(app()->environment('testing') && ($pilot['branch_id'] ?? null) === $school && ($pilot['currency'] ?? null) === 'PKR', 403);
    }
}
