<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use JsonException;
use OverflowException;

class BillingObligationMappingPreview
{
    public function fromFile(string $path, int $branch, string $date): array
    {
        if (! is_file($path) || ! is_readable($path) || ($contents = file_get_contents($path)) === false) {
            throw new InvalidArgumentException('Mapping evidence must be a readable local JSON file.');
        }

        try {
            $evidence = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('Mapping evidence must contain valid JSON.');
        }

        if (! str_starts_with(ltrim($contents), '{')) {
            throw new InvalidArgumentException('Mapping evidence must be a JSON object.');
        }

        $positive = ['required', 'integer:strict', 'min:1'];
        $validator = Validator::make(['document' => $evidence], [
            'document' => ['array:invoice_links,receipt_allocations'],
            'document.invoice_links' => ['sometimes', 'array', 'list'],
            'document.invoice_links.*' => ['required', 'array:invoice_id,charge_id,billing_month,legacy_fee_type_id,v4_fee_type_id,invoice_currency,charge_currency'],
            'document.invoice_links.*.invoice_id' => $positive,
            'document.invoice_links.*.charge_id' => $positive,
            'document.invoice_links.*.billing_month' => ['required', 'date_format:Y-m'],
            'document.invoice_links.*.legacy_fee_type_id' => $positive,
            'document.invoice_links.*.v4_fee_type_id' => $positive,
            'document.invoice_links.*.invoice_currency' => ['required', 'regex:/^[A-Z]{3}$/D'],
            'document.invoice_links.*.charge_currency' => ['required', 'regex:/^[A-Z]{3}$/D'],
            'document.receipt_allocations' => ['sometimes', 'array', 'list'],
            'document.receipt_allocations.*' => ['required', 'array:receipt_id,allocations'],
            'document.receipt_allocations.*.receipt_id' => $positive,
            'document.receipt_allocations.*.allocations' => ['present', 'array', 'list'],
            'document.receipt_allocations.*.allocations.*' => ['required', 'array:charge_id,amount_minor'],
            'document.receipt_allocations.*.allocations.*.charge_id' => $positive,
            'document.receipt_allocations.*.allocations.*.amount_minor' => $positive,
        ]);
        if ($validator->fails()) {
            throw new InvalidArgumentException('Invalid mapping evidence field: '.$validator->errors()->keys()[0].'.');
        }

        $charges = DB::table('student_fee_charges as charge')
            ->join('fee_masters as master', function ($join): void {
                $join->on('master.id', '=', 'charge.fee_master_id')->on('master.school_id', '=', 'charge.school_id');
            })
            ->join('school_sessions as session', function ($join): void {
                $join->on('session.id', '=', 'charge.school_session_id')->on('session.school_id', '=', 'charge.school_id');
            })
            ->where('charge.school_id', $branch)->where('charge.billing_month', '<=', substr($date, 0, 7))
            ->whereNotIn('charge.status', ['void', 'cancelled'])->orderBy('charge.id')
            ->get(['charge.id', 'charge.student_id', 'charge.billing_month', 'charge.amount', 'master.fee_type_id', 'session.starts_on', 'session.ends_on'])
            ->keyBy('id');

        $links = $evidence['invoice_links'] ?? [];
        $receipts = collect($evidence['receipt_allocations'] ?? [])->groupBy('receipt_id');

        $invoices = $this->invoices($branch, $date, $charges, $links);
        $mappedCharges = array_fill_keys(array_column($invoices['confirmed']['records'], 'charge_id'), true);
        $chargeTotals = $this->sections('confirmed', 'unresolved');
        foreach ($charges as $charge) {
            $confirmed = isset($mappedCharges[$charge->id]);
            $this->record($chargeTotals[$confirmed ? 'confirmed' : 'unresolved'], [
                'charge_id' => (int) $charge->id,
                'reason' => $confirmed ? 'verified_link' : 'no_confirmed_invoice',
            ], $this->minor($charge->amount));
        }

        return [
            'evidence_basis' => 'operator-supplied links, period, crosswalk and currency; no records posted',
            'invoices' => $invoices,
            'charges' => $chargeTotals,
            'receipts' => $this->receipts($branch, $date, $charges, $receipts),
        ];
    }

    private function invoices(int $branch, string $date, Collection $charges, array $links): array
    {
        $totals = $this->sections('confirmed', 'unresolved', 'excluded');
        $byInvoice = collect($links)->groupBy('invoice_id');
        $chargeUse = array_count_values(array_column($links, 'charge_id'));
        $invoices = DB::table('fee_invoices')->where('school_id', $branch)->whereDate('created_at', '<=', $date)
            ->orderBy('id')->cursor(['id', 'student_id', 'fee_type_id', 'academic_year', 'amount_cents', 'status']);

        foreach ($invoices as $invoice) {
            $evidence = $byInvoice->get($invoice->id, collect());
            $link = $evidence->first();
            $charge = $charges->get($link['charge_id'] ?? 0);
            $amount = $this->integer($invoice->amount_cents);
            $reason = match (true) {
                $invoice->status === 'void' => 'void_invoice',
                $link === null => 'missing_evidence',
                $evidence->count() !== 1 || $chargeUse[$link['charge_id']] !== 1 => 'ambiguous_evidence',
                $charge === null => 'charge_unavailable',
                (int) $invoice->student_id !== (int) $charge->student_id => 'different_student',
                $invoice->academic_year !== substr($charge->starts_on, 0, 4).'-'.substr($charge->ends_on, 0, 4)
                    || $link['billing_month'] !== $charge->billing_month => 'period_mismatch',
                $link['legacy_fee_type_id'] !== (int) $invoice->fee_type_id
                    || $link['v4_fee_type_id'] !== (int) $charge->fee_type_id => 'fee_type_mismatch',
                $link['invoice_currency'] !== $link['charge_currency'] => 'currency_mismatch',
                $amount !== $this->minor($charge->amount) => 'amount_mismatch',
                default => 'verified_link',
            };
            $section = match ($reason) {
                'verified_link' => 'confirmed',
                'void_invoice' => 'excluded',
                default => 'unresolved',
            };
            $record = ['invoice_id' => (int) $invoice->id, 'reason' => $reason];
            if ($reason === 'verified_link') {
                $record['charge_id'] = (int) $charge->id;
            }
            $this->record($totals[$section], $record, $amount);
        }

        return $totals;
    }

    private function receipts(int $branch, string $date, Collection $charges, Collection $evidence): array
    {
        $totals = $this->sections('allocatable', 'unresolved');
        $receipts = DB::table('family_fee_receipts')->where('school_id', $branch)->where('receipt_date', '<=', $date)
            ->orderBy('id')->cursor(['id', 'received']);
        $records = [];
        $proposed = [];

        foreach ($receipts as $receipt) {
            $links = $evidence->get($receipt->id, collect());
            $allocations = $links->first()['allocations'] ?? [];
            $amount = $receipt->received === null ? null : $this->minor($receipt->received);
            $reason = match (true) {
                $amount === null => 'missing_received_amount',
                $links->count() > 1 => 'ambiguous_evidence',
                $allocations === [] => 'missing_allocation_evidence',
                default => $this->allocationReason($allocations, $charges, $amount),
            };
            $record = ['receipt_id' => (int) $receipt->id, 'reason' => $reason];
            if ($reason === 'verified_allocations') {
                usort($allocations, fn (array $a, array $b): int => $a['charge_id'] <=> $b['charge_id']);
                $record['allocations'] = array_map(fn (array $item): array => [
                    'charge_id' => $item['charge_id'], 'amount_minor' => $item['amount_minor'],
                ], $allocations);
                foreach ($allocations as $allocation) {
                    $id = $allocation['charge_id'];
                    $proposed[$id] = $this->integer(($proposed[$id] ?? 0) + $allocation['amount_minor']);
                }
            }
            $records[] = [$record, $amount];
        }

        $overAllocated = [];
        ksort($proposed, SORT_NUMERIC);
        foreach ($proposed as $id => $amount) {
            $chargeAmount = $this->minor($charges->get($id)->amount);
            if ($amount > $chargeAmount) {
                $overAllocated[$id] = [
                    'charge_id' => $id,
                    'proposed_minor' => $amount,
                    'charge_minor' => $chargeAmount,
                    'excess_minor' => $amount - $chargeAmount,
                ];
            }
        }

        foreach ($records as [$record, $amount]) {
            $chargeIds = array_column($record['allocations'] ?? [], 'charge_id');
            if (array_any($chargeIds, fn (int $id): bool => isset($overAllocated[$id]))) {
                $record['reason'] = 'cumulative_over_allocation';
                unset($record['allocations']);
            }
            $section = $record['reason'] === 'verified_allocations' ? 'allocatable' : 'unresolved';
            $this->record($totals[$section], $record, $amount);
        }

        return $totals + ['over_allocated_charges' => array_values($overAllocated)];
    }

    private function allocationReason(array $allocations, Collection $charges, int $amount): string
    {
        $total = 0;
        $used = [];
        foreach ($allocations as $allocation) {
            $id = $allocation['charge_id'];
            $charge = $charges->get($id);
            if ($charge === null || isset($used[$id]) || $allocation['amount_minor'] > $this->minor($charge->amount)) {
                return 'invalid_charge_allocation';
            }
            $used[$id] = true;
            $total = $this->integer($total + $allocation['amount_minor']);
        }

        return $total === $amount ? 'verified_allocations' : 'allocation_total_mismatch';
    }

    private function sections(string ...$names): array
    {
        return array_fill_keys($names, ['count' => 0, 'amount_minor' => 0, 'missing_amount_count' => 0, 'records' => []]);
    }

    private function record(array &$section, array $record, ?int $amount): void
    {
        $section['count']++;
        $section['amount_minor'] = $this->integer($section['amount_minor'] + ($amount ?? 0));
        $section['missing_amount_count'] += (int) ($amount === null);
        $section['records'][] = $record + ['amount_minor' => $amount];
    }

    private function minor(string|int|float $amount): int
    {
        $decimal = is_float($amount) ? number_format($amount, 2, '.', '') : (string) $amount;
        [$whole, $fraction] = array_pad(explode('.', ltrim($decimal, '-'), 2), 2, '');
        $minor = (int) ($whole.str_pad($fraction, 2, '0'));

        return str_starts_with($decimal, '-') ? -$minor : $minor;
    }

    private function integer(mixed $amount): int
    {
        if (is_float($amount) || ($integer = filter_var($amount, FILTER_VALIDATE_INT)) === false) {
            throw new OverflowException('A mapping amount exceeds the supported integer range.');
        }

        return $integer;
    }
}
