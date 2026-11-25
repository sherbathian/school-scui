<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use App\Services\BranchModules;
use App\Services\PostFamilyReceipt;
use App\Services\ReverseFamilyReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;

class PostedFamilyReceiptController extends Controller
{
    public function workspaceGrants(User $user, School $branch, BranchModules $modules): array
    {
        if (! $this->enabled($branch->id) || ! $user->is_active
            || ! $user->activeSchools()->whereKey($branch->id)->exists()
            || $user->schoolAccessRole($branch) === 'student') {
            return [];
        }

        $fees = $modules->forBranch($branch->id)->firstWhere('slug', 'fees');
        if (! ($fees['enabled'] ?? false)) {
            return [];
        }

        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($branch->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        try {
            $grants = [
                'post' => 'fees.receipt.post', 'reverse' => 'fees.receipt.reverse',
                'periods' => 'fees.period.manage', 'audit' => 'fees.audit.view',
            ];

            $allowed = array_keys(array_filter($grants, fn ($permission, $action) =>
                (! $fees['read_only'] || in_array($action, ['periods', 'audit'], true))
                && $user->checkPermissionTo($permission, 'web'), ARRAY_FILTER_USE_BOTH));

            return array_map(fn (string $action): string => $action === 'periods' && $fees['read_only']
                ? 'periods-read-only' : $action, $allowed);
        } finally {
            $registrar->setPermissionsTeamId($previous);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    public function charges(Request $request, int $school, BranchModules $modules): JsonResponse
    {
        $this->authorizePilot($request, $school, 'fees.receipt.post', $modules);
        $used = DB::table('posted_family_receipt_allocations as allocation')
            ->leftJoin('family_receipt_reversals as reversal', function ($join): void {
                $join->on('reversal.school_id', '=', 'allocation.school_id')->on('reversal.receipt_id', '=', 'allocation.receipt_id');
            })
            ->where('allocation.school_id', $school)->whereNull('reversal.id')->groupBy('allocation.charge_id')
            ->selectRaw('allocation.charge_id, sum(allocation.amount_minor) as allocated');
        $page = DB::table('student_fee_charges as charge')
            ->join('students as student', function ($join): void {
                $join->on('student.id', '=', 'charge.student_id')->on('student.school_id', '=', 'charge.school_id');
            })
            ->leftJoinSub($used, 'used', 'used.charge_id', '=', 'charge.id')
            ->where('charge.school_id', $school)->where('charge.status', 'open')
            ->whereRaw('round(charge.amount * 100) > coalesce(used.allocated, 0)')
            ->select(['charge.id', 'charge.billing_month', 'student.admission_number'])
            ->selectRaw('round(charge.amount * 100) - coalesce(used.allocated, 0) as remaining_minor')
            ->orderByDesc('charge.billing_month')->orderBy('charge.id')->simplePaginate(50)
            ->through(fn ($charge): array => [
                'id' => (int) $charge->id, 'billing_month' => $charge->billing_month,
                'admission_number' => $charge->admission_number, 'remaining_minor' => (int) $charge->remaining_minor,
            ]);

        return response()->json($page)->header('Cache-Control', 'no-store, private');
    }

    public function index(Request $request, int $school, BranchModules $modules): JsonResponse
    {
        $this->authorizePilot($request, $school, 'fees.receipt.reverse', $modules);
        $page = DB::table('posted_family_receipts as receipt')
            ->leftJoin('family_receipt_reversals as reversal', function ($join): void {
                $join->on('reversal.school_id', '=', 'receipt.school_id')->on('reversal.receipt_id', '=', 'receipt.id');
            })
            ->where('receipt.school_id', $school)->orderByDesc('receipt.id')->simplePaginate(50, [
                'receipt.id', 'receipt.amount_minor', 'receipt.received_on', 'receipt.accounting_date', 'reversal.id as reversal_id',
            ])->through(fn ($item): array => [
                'id' => (int) $item->id, 'amount_minor' => (int) $item->amount_minor,
                'received_on' => $item->received_on, 'accounting_date' => $item->accounting_date,
                'reversed' => $item->reversal_id !== null,
                'reversible' => $item->reversal_id === null && $item->accounting_date !== null,
            ]);

        return response()->json($page)->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, int $school, BranchModules $modules, PostFamilyReceipt $posting): JsonResponse
    {
        $currency = $this->authorizePilot($request, $school, 'fees.receipt.post', $modules);
        $data = $request->validate($this->serverFields() + [
            'received_on' => ['required', 'date_format:Y-m-d'],
            'amount_minor' => ['required', 'integer:strict', 'min:1'],
            'allocations' => ['required', 'array', 'list'],
            'allocations.*' => ['required', 'array:charge_id,amount_minor'],
            'allocations.*.charge_id' => ['required', 'integer:strict', 'min:1', 'distinct:strict'],
            'allocations.*.amount_minor' => ['required', 'integer:strict', 'min:1'],
            'source_receipt_id' => ['nullable', 'integer:strict', 'min:1'],
        ]);
        $result = $posting->post(
            $request->user()->id, $school, $currency, $this->key($request), $data['received_on'],
            $data['amount_minor'], array_column($data['allocations'], 'amount_minor', 'charge_id'),
            $data['source_receipt_id'] ?? null,
        );

        return response()->json(['data' => $result]);
    }

    public function reverse(Request $request, int $school, int $receipt, BranchModules $modules, ReverseFamilyReceipt $reversing): JsonResponse
    {
        $this->authorizePilot($request, $school, 'fees.receipt.reverse', $modules);
        $data = $request->validate($this->serverFields() + [
            'reason' => ['required', Rule::in(['duplicate', 'entry_error'])],
            'receipt_id' => ['missing'],
            'amount_minor' => ['missing'],
            'allocations' => ['missing'],
        ]);
        $result = $reversing->reverse($request->user()->id, $school, $receipt, $this->key($request), $data['reason']);

        return response()->json(['data' => $result]);
    }

    private function authorizePilot(Request $request, int $school, string $permission, BranchModules $modules): string
    {
        $user = $request->user();
        $branch = $user->activeSchools()->whereKey($school)->first();
        abort_unless($branch, 404);
        abort_unless($user->schoolAccessRole($branch) !== 'student' && $user->checkPermissionTo($permission, 'web'), 403);
        $modules->assertEnabled($school, 'fees', writing: true);

        abort_unless($this->enabled($school), 403);

        return 'PKR';
    }

    private function enabled(int $school): bool
    {
        $pilot = config('billing.receipt_pilot');

        return app()->environment('testing') && ($pilot['branch_id'] ?? null) === $school && ($pilot['currency'] ?? null) === 'PKR';
    }

    private function key(Request $request): string
    {
        $data = Validator::make(['idempotency_key' => $request->header('Idempotency-Key')], [
            'idempotency_key' => ['required', 'string', 'regex:/^[!-~]{1,128}$/D'],
        ])->validate();

        return $data['idempotency_key'];
    }

    private function serverFields(): array
    {
        return array_fill_keys([
            'actor_id', 'school_id', 'branch_id', 'currency', 'period_id', 'open_period', 'timezone',
            'accounting_date', 'accounting_timezone', 'accounting_recorded_at_utc', 'posted_at', 'reversed_on',
        ], ['missing']);
    }
}
