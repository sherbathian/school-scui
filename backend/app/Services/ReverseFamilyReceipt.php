<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReverseFamilyReceipt
{
    public function __construct(
        private readonly PostFamilyReceipt $posting,
        private readonly BranchAccountingPeriods $calendar,
    ) {}

    /**
     * Internal only. The caller supplies an authorised actor and branch.
     *
     * @return array{reversal_id: int}
     */
    public function reverse(int $actor, int $branch, int $receiptId, string $key, string $reason): array
    {
        if ($receiptId < 1 || trim($key) === '' || strlen($key) > 128
            || ! in_array($reason, ['duplicate', 'entry_error'], true)) {
            throw ValidationException::withMessages(['reversal' => 'The reversal request is invalid.']);
        }
        $keyHash = hash('sha256', $key);
        $payloadHash = hash('sha256', json_encode([$receiptId, $reason], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $branch, $receiptId, $keyHash, $payloadHash, $reason): array {
            $this->calendar->lockBranch($branch);
            DB::table('family_receipt_reversal_keys')->upsert(
                [['school_id' => $branch, 'operation_key' => $keyHash, 'payload_hash' => $payloadHash]],
                ['school_id', 'operation_key'], ['operation_key'],
            );
            $operation = DB::table('family_receipt_reversal_keys')->where('school_id', $branch)
                ->where('operation_key', $keyHash)->lockForUpdate()->first();
            if ($operation->payload_hash !== $payloadHash && ! $this->matchesLegacyReplay($branch, $operation, $receiptId, $reason)) {
                throw ValidationException::withMessages(['key' => 'This key already belongs to another reversal request.'])->status(409);
            }
            if ($operation->reversal_id !== null) {
                return ['reversal_id' => (int) $operation->reversal_id];
            }
            $period = $this->calendar->current($branch);
            $date = $period['accounting_date'];

            $receipt = DB::table('posted_family_receipts')->where('school_id', $branch)->where('id', $receiptId)->lockForUpdate()->first();
            if ($receipt === null) {
                throw ValidationException::withMessages(['receipt' => 'The original receipt is unavailable.']);
            }
            if ($receipt->period_id === null || $receipt->accounting_date === null || $date < $receipt->accounting_date) {
                throw ValidationException::withMessages(['period' => 'The original receipt has no eligible accounting context.']);
            }
            if (DB::table('family_receipt_reversals')->where('school_id', $branch)->where('receipt_id', $receiptId)->lockForUpdate()->first(['id'])) {
                throw ValidationException::withMessages(['receipt' => 'The receipt has already been reversed.'])->status(409);
            }

            $allocations = DB::table('posted_family_receipt_allocations')->where('school_id', $branch)
                ->where('receipt_id', $receiptId)->orderBy('charge_id')->get(['id', 'charge_id', 'amount_minor']);
            if ($allocations->isEmpty() || $allocations->sum('amount_minor') !== (int) $receipt->amount_minor) {
                throw ValidationException::withMessages(['receipt' => 'The original receipt allocations are incomplete.']);
            }
            $balances = [];
            $allocationIds = [];
            foreach ($allocations as $allocation) {
                $id = (int) $allocation->charge_id;
                $before = $this->posting->lockChargeBalance($branch, $id, posting: false);
                $balances[] = ['charge_id' => $id, 'before_minor' => $before, 'after_minor' => $before + (int) $allocation->amount_minor];
                $allocationIds[] = (int) $allocation->id;
            }

            $time = $period['recorded_at_utc'];
            $context = [
                'school_id' => $branch, 'receipt_id' => $receiptId, 'actor_id' => $actor,
                'currency' => $receipt->currency, 'amount_minor' => $receipt->amount_minor,
                'reason_code' => $reason, 'recorded_at' => $time,
                'period_id' => $period['period_id'], 'accounting_date' => $date,
                'accounting_timezone' => $period['timezone'], 'accounting_recorded_at_utc' => $time,
            ];
            $reversal = DB::table('family_receipt_reversals')->insertGetId($context + ['reversed_on' => $date]);
            DB::table('family_receipt_finance_events')->insert($context + [
                'action' => 'receipt.reversed', 'reversal_id' => $reversal,
                'balances' => json_encode($balances, JSON_THROW_ON_ERROR),
                'allocation_ids' => json_encode($allocationIds, JSON_THROW_ON_ERROR),
            ]);
            DB::table('family_receipt_reversal_keys')->where('id', $operation->id)->update(['reversal_id' => $reversal]);

            return ['reversal_id' => $reversal];
        }, 3);
    }

    private function matchesLegacyReplay(int $branch, object $operation, int $receiptId, string $reason): bool
    {
        if ($operation->reversal_id === null) {
            return false;
        }

        $original = DB::table('family_receipt_reversals')->where('school_id', $branch)
            ->where('id', $operation->reversal_id)->whereNull('period_id')->lockForUpdate()->first();
        if ($original === null || (int) $original->receipt_id !== $receiptId || $original->reason_code !== $reason) {
            return false;
        }

        $legacyHash = hash('sha256', json_encode([$receiptId, $reason, $original->reversed_on], JSON_THROW_ON_ERROR));

        return $operation->payload_hash === $legacyHash;
    }
}
