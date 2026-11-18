<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FamilyFeeReceipt;
use App\Models\School;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FamilyFeeReceiptController extends Controller
{
    public function index(Request $request, int $school): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'view');
        $query = $schoolRecord->familyFeeReceipts()->with('guardian:id,father_name,father_phone,source_parent_id');
        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($filter) use ($search): void {
                $filter->where('receipt_number', 'like', "%{$search}%")
                    ->orWhere('source_parent_id', 'like', "%{$search}%")
                    ->orWhereHas('guardian', fn ($guardian) => $guardian
                        ->where('father_name', 'like', "%{$search}%")
                        ->orWhere('mother_name', 'like', "%{$search}%")
                        ->orWhere('father_phone', 'like', "%{$search}%"));
            });
        }

        return response()->json(['data' => $query->orderByDesc('receipt_date')->orderByDesc('receipt_number')->get()
            ->map(fn (FamilyFeeReceipt $receipt): array => $this->receiptData($receipt))->values()]);
    }

    public function store(Request $request, int $school): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $validated = $request->validate([
            'source_parent_id' => ['required', 'string', 'max:50'],
            'receipt_number' => ['required', 'string', 'max:100', Rule::unique('family_fee_receipts', 'receipt_number')->where(fn (Builder $query) => $query->where('school_id', $schoolRecord->id))],
            'source_key' => ['nullable', 'string', 'max:100', Rule::unique('family_fee_receipts', 'source_key')->where(fn (Builder $query) => $query->where('school_id', $schoolRecord->id))],
            'receipt_date' => ['required', 'date'],
            'total_fee' => ['nullable', 'numeric', 'min:0'], 'discount' => ['nullable', 'numeric', 'min:0'],
            'arrears' => ['nullable', 'numeric', 'min:0'], 'fund' => ['nullable', 'numeric', 'min:0'],
            'manual_discount' => ['nullable', 'numeric', 'min:0'], 'total_payable' => ['nullable', 'numeric', 'min:0'],
            'received' => ['nullable', 'numeric', 'min:0'], 'balance' => ['nullable', 'numeric', 'min:0'],
            'source_sheet' => ['nullable', 'string', 'max:80'], 'source_row' => ['nullable', 'integer', 'min:1'],
            'source_data' => ['nullable', 'array'],
        ]);
        $parentId = trim($validated['source_parent_id']);
        $guardian = $schoolRecord->guardians()->where('source_parent_id', $parentId)->first();
        $receipt = $schoolRecord->familyFeeReceipts()->create([
            ...$validated,
            'source_parent_id' => $parentId,
            'guardian_id' => $guardian?->id,
        ]);

        return response()->json(['data' => $this->receiptData($receipt->load('guardian:id,father_name,father_phone,source_parent_id'))], 201);
    }

    private function accessibleSchool(Request $request, int $school, string $ability): School
    {
        $schools = $request->user()->isSuperAdmin() ? School::query() : $request->user()->activeSchools();
        $record = $schools->whereKey($school)->firstOrFail();
        Gate::authorize($ability, $record);

        return $record;
    }

    /** @return array<string, mixed> */
    private function receiptData(FamilyFeeReceipt $receipt): array
    {
        return [
            ...$receipt->only([
                'id', 'source_parent_id', 'receipt_number', 'source_key', 'total_fee', 'discount', 'arrears',
                'fund', 'manual_discount', 'total_payable', 'received', 'balance', 'source_sheet', 'source_row', 'source_data',
            ]),
            'receipt_date' => $receipt->receipt_date->toDateString(),
            'guardian' => $receipt->guardian?->only(['id', 'father_name', 'father_phone', 'source_parent_id']),
        ];
    }
}
