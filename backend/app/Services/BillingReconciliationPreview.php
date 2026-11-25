<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use OverflowException;

class BillingReconciliationPreview
{
    public function forBranch(int $branch, string $date): array
    {
        $month = substr($date, 0, 7);
        $legacy = $this->legacy($branch, $date);

        $charges = [];
        foreach (DB::table('student_fee_charges')->where('school_id', $branch)->where('billing_month', '<=', $month)
            ->orderBy('billing_month')->orderBy('id')->cursor(['billing_month', 'amount']) as $charge) {
            $period = $charge->billing_month;
            $charges[$period] ??= ['month' => $period, 'count' => 0, 'amount_minor' => 0];
            $charges[$period]['count']++;
            $charges[$period]['amount_minor'] = $this->addAmounts($charges[$period]['amount_minor'], $this->minor($charge->amount));
        }

        $receipts = [
            'date_basis' => 'receipt_date',
            'count' => 0,
            'unverified_allocation_count' => 0,
            'total_payable_minor' => null,
            'received_minor' => null,
            'balance_minor' => null,
            'missing_total_payable_count' => 0,
            'missing_received_count' => 0,
            'missing_balance_count' => 0,
        ];
        foreach (DB::table('family_fee_receipts')->where('school_id', $branch)->where('receipt_date', '<=', $date)
            ->orderBy('id')->cursor(['total_payable', 'received', 'balance']) as $receipt) {
            $receipts['count']++;
            $receipts['unverified_allocation_count']++;
            foreach (['total_payable', 'received', 'balance'] as $field) {
                $this->addNullableAmount($receipts, $field, $receipt->{$field});
            }
        }

        $snapshots = [];
        foreach (DB::table('legacy_student_fee_snapshots')->where('school_id', $branch)->where('billing_month', '<=', $month)
            ->orderBy('billing_month')->orderBy('id')->cursor(['billing_month', 'balance_amount', 'received_amount']) as $snapshot) {
            $period = $snapshot->billing_month;
            $snapshots[$period] ??= [
                'month' => $period, 'count' => 0,
                'balance_minor' => null, 'received_minor' => null,
                'missing_balance_count' => 0, 'missing_received_count' => 0,
            ];
            $snapshots[$period]['count']++;
            $this->addNullableAmount($snapshots[$period], 'balance', $snapshot->balance_amount);
            $this->addNullableAmount($snapshots[$period], 'received', $snapshot->received_amount);
        }

        $expenses = ['date_basis' => 'expense_date', 'count' => 0, 'amount_minor' => 0];
        foreach (DB::table('expenses')->where('school_id', $branch)->where('expense_date', '<=', $date)
            ->orderBy('id')->cursor(['amount']) as $expense) {
            $expenses['count']++;
            $expenses['amount_minor'] = $this->addAmounts($expenses['amount_minor'], $this->minor($expense->amount));
        }

        return [
            'branch_id' => $branch,
            'as_of' => $date,
            'currency' => null,
            'amount_unit' => 'minor units at scale 2; currency unverified',
            ...$legacy,
            'monthly_charges' => ['date_basis' => 'billing_month through cutoff month', 'by_month' => array_values($charges)],
            'family_receipts' => $receipts,
            'legacy_snapshots' => ['date_basis' => 'billing_month through cutoff month', 'by_month' => array_values($snapshots)],
            'expenses' => $expenses,
        ];
    }

    private function legacy(int $branch, string $date): array
    {
        $payments = DB::table('fee_payments as payment')->where('payment.school_id', $branch)->where('payment.paid_on', '<=', $date)
            ->join('fee_invoices as invoice', function ($join): void {
                $join->on('invoice.id', '=', 'payment.fee_invoice_id')->on('invoice.school_id', '=', 'payment.school_id');
            })
            ->groupBy('payment.fee_invoice_id')
            ->selectRaw('payment.fee_invoice_id, count(*) as payment_count, sum(payment.amount_cents) as amount_paid')
            ->selectRaw('sum(case when payment.paid_on < date(invoice.created_at) then 1 else 0 end) as predates_invoice_count');

        $invoices = [
            'date_basis' => 'created_at calendar date through cutoff; null creation dates excluded',
            'status_basis' => 'current status, not historical status at cutoff',
            'count' => 0,
            'gross_minor' => 0,
            'collectible_minor' => 0,
            'void_count' => 0,
            'void_gross_minor' => 0,
            'void_balance_minor' => 0,
            'overpaid_invoice_count' => 0,
        ];
        $paymentTotals = [
            'date_basis' => 'paid_on through cutoff for included invoices',
            'count' => 0, 'amount_minor' => 0,
            'on_void_count' => 0, 'on_void_minor' => 0, 'predates_invoice_count' => 0,
        ];

        $rows = DB::table('fee_invoices')->where('school_id', $branch)->whereDate('created_at', '<=', $date)
            ->leftJoinSub($payments, 'payments', 'payments.fee_invoice_id', '=', 'fee_invoices.id')
            ->orderBy('fee_invoices.id')
            ->cursor(['amount_cents', 'status', 'payment_count', 'amount_paid', 'predates_invoice_count']);

        foreach ($rows as $row) {
            $gross = $this->integerAmount($row->amount_cents);
            $paid = $this->integerAmount($row->amount_paid ?? 0);
            $count = (int) $row->payment_count;
            $balance = max(0, $gross - $paid);

            $invoices['count']++;
            $invoices['gross_minor'] = $this->addAmounts($invoices['gross_minor'], $gross);
            $invoices['overpaid_invoice_count'] += (int) ($paid > $gross);
            $paymentTotals['count'] += $count;
            $paymentTotals['amount_minor'] = $this->addAmounts($paymentTotals['amount_minor'], $paid);
            $paymentTotals['predates_invoice_count'] += (int) $row->predates_invoice_count;

            if ($row->status === 'void') {
                $invoices['void_count']++;
                $invoices['void_gross_minor'] = $this->addAmounts($invoices['void_gross_minor'], $gross);
                $invoices['void_balance_minor'] = $this->addAmounts($invoices['void_balance_minor'], $balance);
                $paymentTotals['on_void_count'] += $count;
                $paymentTotals['on_void_minor'] = $this->addAmounts($paymentTotals['on_void_minor'], $paid);
            } else {
                $invoices['collectible_minor'] = $this->addAmounts($invoices['collectible_minor'], $balance);
            }
        }

        return ['legacy_invoices' => $invoices, 'legacy_payments' => $paymentTotals];
    }

    private function addNullableAmount(array &$totals, string $field, mixed $amount): void
    {
        if ($amount === null) {
            $totals["missing_{$field}_count"]++;

            return;
        }

        $key = "{$field}_minor";
        $totals[$key] = $this->addAmounts($totals[$key] ?? 0, $this->minor($amount));
    }

    private function integerAmount(string|int $amount): int
    {
        $integer = filter_var($amount, FILTER_VALIDATE_INT);
        if ($integer === false) {
            throw new OverflowException('A billing amount exceeds the supported integer range.');
        }

        return $integer;
    }

    private function addAmounts(int $amount, int $increment): int
    {
        $total = $amount + $increment;
        if (! is_int($total)) {
            throw new OverflowException('A billing total exceeds the supported integer range.');
        }

        return $total;
    }

    private function minor(string|int|float $amount): int
    {
        // MySQL returns DECIMAL as a string. SQLite returns numeric values.
        $decimal = is_float($amount) ? number_format($amount, 2, '.', '') : (string) $amount;
        [$whole, $fraction] = array_pad(explode('.', ltrim($decimal, '-'), 2), 2, '');
        $minor = (int) ($whole.str_pad($fraction, 2, '0'));

        return str_starts_with($decimal, '-') ? -$minor : $minor;
    }
}
