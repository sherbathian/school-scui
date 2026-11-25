<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BranchModules;
use App\Services\PostFamilyReceipt;
use App\Services\ReverseFamilyReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PostedFamilyReceiptController extends Controller
{
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

        $pilot = config('billing.receipt_pilot');
        abort_unless(app()->environment('testing') && ($pilot['branch_id'] ?? null) === $school && ($pilot['currency'] ?? null) === 'PKR', 403);

        return $pilot['currency'];
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
