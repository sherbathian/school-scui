<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Services\BranchFinanceReconciliationReport;
use App\Services\BranchModules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchFinanceReconciliationReportController extends Controller
{
    public function index(Request $request, int $school, BranchModules $modules, BranchFinanceReconciliationReport $report): JsonResponse
    {
        $user = $request->user();
        $branch = $user->activeSchools()->whereKey($school)->first();
        abort_unless($branch, 404);
        abort_unless($user->schoolAccessRole($branch) !== 'student' && $user->checkPermissionTo('fees.reconciliation.view', 'web'), 403);
        $pilot = config('billing.receipt_pilot');
        abort_unless(app()->environment('testing') && ($pilot['branch_id'] ?? null) === $school && ($pilot['currency'] ?? null) === 'PKR', 403);
        $catalogue = $modules->forBranch($school);
        abort_unless($catalogue->firstWhere('slug', 'fees')['enabled'] ?? false, 403);
        abort_unless(self::reportsEnabled($branch, $catalogue->firstWhere('slug', 'reports')), 403);

        $data = $request->validate([
            'month' => ['required', 'regex:/^[1-9][0-9]{3}-(0[1-9]|1[0-2])$/D'],
            'cutoff' => ['required', 'date_format:Y-m-d'],
        ]);
        if (substr($data['cutoff'], 0, 7) !== $data['month']) {
            throw ValidationException::withMessages(['cutoff' => 'Choose a cutoff date within the selected month.']);
        }

        return response()->json(['data' => $report->forBranch($school, $data['month'], $data['cutoff'])])
            ->header('Cache-Control', 'no-store, private');
    }

    public static function reportsEnabled(School $branch, ?array $reports): bool
    {
        return ($reports['enabled'] ?? false) && ($reports['has_override'] || ($branch->group_id !== null
            && DB::table('school_subscriptions')->where('school_group_id', $branch->group_id)->exists()));
    }
}
