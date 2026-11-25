<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostFamilyReceipt
{
    public function __construct(private readonly BranchAccountingPeriods $calendar) {}

    /**
     * Internal only. The caller supplies an authorised actor, branch and verified currency.
     *
     * @param array<int, int> $allocations Minor units keyed by charge ID
     * @return array{receipt_id: int}
     */
    public function post(
        int $actor, int $branch, string $currency, string $key, string $receivedOn,
        int $amount, array $allocations, ?int $source = null,
    ): array {
        $this->validate($currency, $key, $receivedOn, $amount, $allocations, $source);
        ksort($allocations, SORT_NUMERIC);
        $keyHash = hash('sha256', $key);
        $payloadHash = hash('sha256', json_encode([$currency, $receivedOn, $amount, $allocations, $source], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $branch, $currency, $keyHash, $payloadHash, $receivedOn, $amount, $allocations, $source): array {
            $this->calendar->lockBranch($branch);
            DB::table('family_receipt_posting_keys')->upsert(
                [['school_id' => $branch, 'operation_key' => $keyHash, 'payload_hash' => $payloadHash]],
                ['school_id', 'operation_key'], ['operation_key'],
            );
            $operation = DB::table('family_receipt_posting_keys')->where('school_id', $branch)
                ->where('operation_key', $keyHash)->lockForUpdate()->first();
            if ($operation->payload_hash !== $payloadHash) {
                throw ValidationException::withMessages(['key' => 'This key already belongs to another posting request.'])->status(409);
            }
            if ($operation->receipt_id !== null) {
                return ['receipt_id' => (int) $operation->receipt_id];
            }

            $period = $this->calendar->current($branch);
            $day = $period['accounting_date'];
            if ($receivedOn > $day || ($receivedOn < $day && $source === null)) {
                throw ValidationException::withMessages(['received_on' => 'Use the current business date or supply matching evidence for an earlier receipt.']);
            }
            $accounting = [
                'period_id' => $period['period_id'], 'accounting_date' => $day,
                'accounting_timezone' => $period['timezone'], 'accounting_recorded_at_utc' => $period['recorded_at_utc'],
            ];

            if ($source !== null) {
                $this->lockSource($branch, $source, $amount, $receivedOn);
            }
            $balances = $this->balances($branch, $allocations);
            $time = $period['recorded_at_utc'];
            $receipt = DB::table('posted_family_receipts')->insertGetId([
                'school_id' => $branch, 'actor_id' => $actor, 'source_receipt_id' => $source,
                'currency' => $currency, 'amount_minor' => $amount, 'received_on' => $receivedOn, 'posted_at' => $time,
            ] + $accounting);
            $allocationIds = [];
            foreach ($allocations as $id => $allocated) {
                $allocationIds[] = DB::table('posted_family_receipt_allocations')->insertGetId([
                    'school_id' => $branch, 'receipt_id' => $receipt, 'charge_id' => $id, 'amount_minor' => $allocated,
                ]);
            }
            DB::table('family_receipt_finance_events')->insert([
                'school_id' => $branch, 'receipt_id' => $receipt, 'actor_id' => $actor,
                'action' => 'receipt.posted', 'currency' => $currency, 'amount_minor' => $amount, 'recorded_at' => $time,
                'balances' => json_encode($balances, JSON_THROW_ON_ERROR),
                'allocation_ids' => json_encode($allocationIds, JSON_THROW_ON_ERROR),
            ] + $accounting);
            DB::table('family_receipt_posting_keys')->where('id', $operation->id)->update(['receipt_id' => $receipt]);

            return ['receipt_id' => $receipt];
        }, 3);
    }

    private function validate(string $currency, string $key, string $date, int $amount, array $allocations, ?int $source): void
    {
        if ($currency !== 'PKR' || trim($key) === '' || strlen($key) > 128 || $amount < 1 || $allocations === []
            || ($source !== null && $source < 1) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $parts)
            || (int) $parts[1] < 1000 || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            throw ValidationException::withMessages(['receipt' => 'The posting request is invalid.']);
        }
        $remaining = $amount;
        foreach ($allocations as $id => $allocated) {
            if (! is_int($id) || $id < 1 || ! is_int($allocated) || $allocated < 1 || $allocated > $remaining) {
                throw ValidationException::withMessages(['allocations' => 'Provide positive minor-unit allocations that sum to the receipt amount.']);
            }
            $remaining -= $allocated;
        }
        if ($remaining !== 0) {
            throw ValidationException::withMessages(['allocations' => 'Allocations must sum to the receipt amount.']);
        }
    }

    private function lockSource(int $branch, int $source, int $amount, string $receivedOn): void
    {
        $record = DB::table('family_fee_receipts')->where('school_id', $branch)->where('id', $source)->lockForUpdate()->first(['received', 'receipt_date']);
        if ($record === null || $record->received === null || $this->minor($record->received) !== $amount || $record->receipt_date !== $receivedOn) {
            throw ValidationException::withMessages(['source' => 'The source receipt is unavailable or does not match the posting.']);
        }
        if (DB::table('posted_family_receipts')->where('school_id', $branch)->where('source_receipt_id', $source)->lockForUpdate()->first(['id'])) {
            throw ValidationException::withMessages(['source' => 'The source receipt has already been posted.'])->status(409);
        }
    }

    private function balances(int $branch, array $allocations): array
    {
        $balances = [];
        foreach ($allocations as $id => $amount) {
            $balance = $this->lockChargeBalance($branch, $id, posting: true);
            if ($amount > $balance) {
                throw ValidationException::withMessages(['allocations' => 'An allocation exceeds the remaining charge balance.']);
            }
            $balances[] = ['charge_id' => $id, 'before_minor' => $balance, 'after_minor' => $balance - $amount];
        }

        return $balances;
    }

    /** Caller holds the transaction and visits charges in ascending ID order. */
    public function lockChargeBalance(int $branch, int $id, bool $posting): int
    {
        $charge = DB::table('student_fee_charges')->where('school_id', $branch)->where('id', $id)
            ->lockForUpdate()->first(['status', 'amount']);
        if ($charge === null || ($posting && $charge->status !== 'open')) {
            throw ValidationException::withMessages(['allocations' => 'A charge is unavailable.']);
        }

        $balance = $this->minor($charge->amount);
        $posted = DB::table('posted_family_receipt_allocations as allocation')
            ->leftJoin('family_receipt_reversals as reversal', function ($join): void {
                $join->on('reversal.school_id', '=', 'allocation.school_id')
                    ->on('reversal.receipt_id', '=', 'allocation.receipt_id');
            })
            ->where('allocation.school_id', $branch)->where('allocation.charge_id', $id)
            ->whereNull('reversal.id')->lockForUpdate()->pluck('allocation.amount_minor');
        foreach ($posted as $amount) {
            $balance -= (int) $amount;
        }

        return $balance;
    }

    private function minor(string|int|float $amount): int
    {
        $decimal = is_float($amount) ? number_format($amount, 2, '.', '') : (string) $amount;
        [$whole, $fraction] = array_pad(explode('.', $decimal, 2), 2, '');

        return (int) ($whole.str_pad($fraction, 2, '0'));
    }
}
