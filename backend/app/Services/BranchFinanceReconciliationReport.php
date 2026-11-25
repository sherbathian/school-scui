<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class BranchFinanceReconciliationReport
{
    public function forBranch(int $school, string $month, string $cutoff): array
    {
        return DB::transaction(fn (): array => [
            'school_id' => $school, 'month' => $month, 'cutoff' => $cutoff, 'currency' => 'PKR',
            'legacy' => $this->legacy($school, $month.'-01', $cutoff),
            'v4' => $this->ledger($school, $month, $cutoff),
        ]);
    }

    private function legacy(int $school, string $start, string $cutoff): array
    {
        $payments = DB::table('fee_payments')->where('school_id', $school);
        $invoices = DB::table('fee_invoices')->where('school_id', $school)->where('status', 'open');
        $currentPaid = (clone $payments)->groupBy('fee_invoice_id')
            ->selectRaw('fee_invoice_id, sum(amount_cents) as paid');
        $cutoffPaid = (clone $payments)->where('paid_on', '<=', $cutoff)->groupBy('fee_invoice_id')
            ->selectRaw('fee_invoice_id, sum(amount_cents) as paid');
        $open = (clone $invoices)->leftJoinSub($currentPaid, 'totals', 'totals.fee_invoice_id', '=', 'fee_invoices.id')
            ->whereRaw('fee_invoices.amount_cents > coalesce(totals.paid, 0)')->count();
        $outstanding = $invoices->leftJoinSub($cutoffPaid, 'totals', 'totals.fee_invoice_id', '=', 'fee_invoices.id')
            ->whereDate('fee_invoices.created_at', '<=', $cutoff)
            ->selectRaw('sum(case when fee_invoices.amount_cents > coalesce(totals.paid, 0)
                then fee_invoices.amount_cents - coalesce(totals.paid, 0) else 0 end) as outstanding')->value('outstanding');

        return [
            'collected_minor' => (int) $payments->whereBetween('paid_on', [$start, $cutoff])->sum('amount_cents'),
            'open_invoice_count_current' => $open,
            'collectible_minor_at_cutoff' => (int) $outstanding,
            'collection_basis' => 'Payment date from month start through cutoff.',
            'balance_basis' => 'Invoices created by cutoff and payments dated by cutoff, using current invoice status.',
        ];
    }

    private function ledger(int $school, string $month, string $cutoff): array
    {
        $start = $month.'-01';
        $receipts = $this->ledgerRows('posted_family_receipts', $school);
        $reversals = $this->ledgerRows('family_receipt_reversals', $school);
        $gross = (int) (clone $receipts)->whereNotNull('period_id')->whereBetween('accounting_date', [$start, $cutoff])->sum('amount_minor');
        $offsets = (int) (clone $reversals)->whereNotNull('period_id')->whereBetween('accounting_date', [$start, $cutoff])->sum('amount_minor');
        $unassignedReceipts = $this->unassigned($receipts, $cutoff);
        $unassignedReversals = $this->unassigned($reversals, $cutoff);

        $effectiveReceipts = (clone $receipts)->whereNotNull('period_id')->where('accounting_date', '<=', $cutoff)
            ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')->from('family_receipt_reversals as reversal')
                ->whereColumn('reversal.receipt_id', 'posted_family_receipts.id')
                ->whereColumn('reversal.school_id', 'posted_family_receipts.school_id')
                ->whereColumn('reversal.currency', 'posted_family_receipts.currency')
                ->whereNotNull('reversal.period_id')->where('reversal.accounting_date', '<=', $cutoff))
            ->select('id');
        $allocated = DB::table('posted_family_receipt_allocations')->where('school_id', $school)
            ->whereIn('receipt_id', $effectiveReceipts)->groupBy('charge_id')
            ->selectRaw('charge_id, sum(amount_minor) as paid');
        $remaining = DB::table('student_fee_charges')
            ->leftJoinSub($allocated, 'totals', 'totals.charge_id', '=', 'student_fee_charges.id')
            ->where('school_id', $school)->where('billing_month', '<=', $month)->whereNotIn('status', ['void', 'cancelled'])
            ->selectRaw('sum(round(amount * 100) - coalesce(totals.paid, 0)) as remaining')->value('remaining');

        return [
            'gross_minor' => $gross, 'reversal_minor' => $offsets, 'net_minor' => $gross - $offsets,
            'remaining_minor_at_cutoff' => (int) $remaining,
            'unassigned_receipt_count' => $unassignedReceipts, 'unassigned_reversal_count' => $unassignedReversals,
            'accounting_context_complete' => $unassignedReceipts === 0 && $unassignedReversals === 0,
            'activity_basis' => 'Branch accounting date from month start through cutoff.',
            'balance_basis' => 'Charges through billing month, less allocations effective at cutoff, using current charge status.',
        ];
    }

    private function ledgerRows(string $table, int $school): Builder
    {
        return DB::table($table)->where('school_id', $school)->where('currency', 'PKR');
    }

    private function unassigned(Builder $rows, string $cutoff): int
    {
        return (clone $rows)->where(fn (Builder $query) => $query->whereNull('accounting_date')
            ->orWhere(fn (Builder $query) => $query->whereNull('period_id')->where('accounting_date', '<=', $cutoff)))->count();
    }
}
