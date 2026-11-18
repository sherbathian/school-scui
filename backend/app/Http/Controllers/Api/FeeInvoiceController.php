<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class FeeInvoiceController extends Controller
{
    public function index(Request $request, int $school): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $validated = $request->validate([
            'academic_year' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/', 'max:20'],
        ]);
        if ($validated['academic_year'] !== $this->activeAcademicYear($schoolRecord)) {
            throw ValidationException::withMessages(['academic_year' => ['Fee invoices must use the school’s active academic year.']]);
        }
        $invoices = $schoolRecord->feeInvoices()
            ->with(['student:id,admission_number,first_name,last_name', 'feeType:id,title', 'payments' => fn ($query) => $query->orderBy('paid_on')->orderBy('id')])
            ->where('academic_year', $validated['academic_year'])
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $invoices->map(fn (FeeInvoice $invoice) => $this->invoiceData($invoice))->values()]);
    }

    public function store(Request $request, int $school): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $validated = $request->validate([
            'student_id' => ['required', 'integer'],
            'fee_type_id' => ['required', 'integer'],
            'academic_year' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/', 'max:20'],
            'due_date' => ['sometimes', 'nullable', 'date'],
        ]);
        if ($validated['academic_year'] !== $this->activeAcademicYear($schoolRecord)) {
            throw ValidationException::withMessages(['academic_year' => ['Fee invoices must use the school’s active academic year.']]);
        }
        $feeType = $schoolRecord->legacyFeeTypes()
            ->where('status', 'active')
            ->where('academic_year', $validated['academic_year'])
            ->findOrFail($validated['fee_type_id']);
        $student = $schoolRecord->students()
            ->where('status', 'active')
            ->whereHas('enrollments', fn (Builder $query) => $query->where('academic_year', $validated['academic_year']))
            ->findOrFail($validated['student_id']);

        $alreadyIssued = $schoolRecord->feeInvoices()
            ->where('student_id', $student->id)
            ->where('fee_type_id', $feeType->id)
            ->where('academic_year', $validated['academic_year'])
            ->exists();
        if ($alreadyIssued) {
            throw ValidationException::withMessages([
                'fee_type_id' => ['This fee item has already been invoiced to this student for the selected academic year.'],
            ]);
        }

        $invoice = $schoolRecord->feeInvoices()->create([
            'student_id' => $student->id,
            'fee_type_id' => $feeType->id,
            'academic_year' => $validated['academic_year'],
            'description' => $feeType->title,
            'amount_cents' => $feeType->amount_cents,
            'due_date' => $validated['due_date'] ?? null,
            'status' => 'open',
        ])->load(['student:id,admission_number,first_name,last_name', 'feeType:id,title', 'payments']);

        return response()->json(['data' => $this->invoiceData($invoice)], 201);
    }

    public function destroy(Request $request, int $school, int $feeInvoice): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $invoice = $schoolRecord->feeInvoices()->with('payments')->findOrFail($feeInvoice);
        if ($invoice->payments->isNotEmpty()) {
            throw ValidationException::withMessages([
                'invoice' => ['An invoice with recorded payments cannot be voided.'],
            ]);
        }
        $invoice->update(['status' => 'void']);

        return response()->json([], 204);
    }

    public function recordPayment(Request $request, int $school, int $feeInvoice): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
            'paid_on' => ['sometimes', 'date', 'before_or_equal:today'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);
        $amountCents = $this->toCents($validated['amount']);

        $invoice = DB::transaction(function () use ($schoolRecord, $feeInvoice, $validated, $amountCents, $request): FeeInvoice {
            $invoice = $schoolRecord->feeInvoices()->whereKey($feeInvoice)->lockForUpdate()->firstOrFail();
            if ($invoice->status === 'void') {
                throw ValidationException::withMessages(['invoice' => ['Payments cannot be added to a void invoice.']]);
            }

            $balanceCents = $invoice->amount_cents - $invoice->payments()->sum('amount_cents');
            if ($amountCents > $balanceCents) {
                throw ValidationException::withMessages(['amount' => ['The payment cannot be greater than the remaining balance.']]);
            }

            FeePayment::query()->create([
                'school_id' => $schoolRecord->id,
                'fee_invoice_id' => $invoice->id,
                'amount_cents' => $amountCents,
                'paid_on' => $validated['paid_on'] ?? now()->toDateString(),
                'reference' => $validated['reference'] ?? null,
                'recorded_by_user_id' => $request->user()->id,
            ]);

            return $invoice->load(['student:id,admission_number,first_name,last_name', 'feeType:id,title', 'payments']);
        });

        return response()->json(['data' => $this->invoiceData($invoice)]);
    }

    private function accessibleSchool(Request $request, int $school, string $ability): School
    {
        $schools = $request->user()->isSuperAdmin() ? School::query() : $request->user()->activeSchools();
        $schoolRecord = $schools->whereKey($school)->firstOrFail();
        Gate::authorize($ability, $schoolRecord);

        return $schoolRecord;
    }

    private function toCents(string|int|float $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', (string) $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function activeAcademicYear(School $school): string
    {
        $session = $school->schoolSessions()->where('is_current', true)->first();
        if (! $session) {
            throw ValidationException::withMessages(['academic_year' => ['Set an active academic year before managing fees.']]);
        }

        return $session->starts_on->format('Y').'-'.$session->ends_on->format('Y');
    }

    /** @return array<string, mixed> */
    private function invoiceData(FeeInvoice $invoice): array
    {
        $paidCents = (int) $invoice->payments->sum('amount_cents');
        $balanceCents = max(0, $invoice->amount_cents - $paidCents);
        $displayStatus = match (true) {
            $invoice->status === 'void' => 'void',
            $balanceCents === 0 => 'paid',
            $paidCents > 0 => 'partial',
            $invoice->due_date?->lt(today()) => 'overdue',
            default => 'open',
        };

        return [
            'id' => $invoice->id,
            'student' => [
                'id' => $invoice->student->id,
                'admission_number' => $invoice->student->admission_number,
                'first_name' => $invoice->student->first_name,
                'last_name' => $invoice->student->last_name,
            ],
            'fee_type' => ['id' => $invoice->fee_type_id, 'title' => $invoice->description],
            'academic_year' => $invoice->academic_year,
            'amount' => number_format($invoice->amount_cents / 100, 2, '.', ''),
            'amount_paid' => number_format($paidCents / 100, 2, '.', ''),
            'balance' => number_format($balanceCents / 100, 2, '.', ''),
            'due_date' => $invoice->due_date?->toDateString(),
            'status' => $displayStatus,
            'payments' => $invoice->payments->map(fn (FeePayment $payment) => [
                'id' => $payment->id,
                'amount' => number_format($payment->amount_cents / 100, 2, '.', ''),
                'paid_on' => $payment->paid_on->toDateString(),
                'reference' => $payment->reference,
            ])->values(),
        ];
    }
}
