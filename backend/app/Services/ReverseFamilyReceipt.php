<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReverseFamilyReceipt
{
    public function __construct(private readonly PostFamilyReceipt $posting) {}

    /**
     * Internal only. The caller supplies a verified open period for the authorised branch.
     *
     * @param array{0: string, 1: string}|null $openPeriod Inclusive YYYY-MM-DD boundaries
     * @return array{reversal_id: int}
     */
    public function reverse(int $actor, int $branch, int $receiptId, string $key, string $reason, string $date, ?array $openPeriod): array
    {
        if ($receiptId < 1 || trim($key) === '' || strlen($key) > 128
            || ! in_array($reason, ['duplicate', 'entry_error'], true) || ! $this->validDate($date)) {
            throw ValidationException::withMessages(['reversal' => 'The reversal request is invalid.']);
        }
        $keyHash = hash('sha256', $key);
        $payloadHash = hash('sha256', json_encode([$receiptId, $reason, $date], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $branch, $receiptId, $keyHash, $payloadHash, $reason, $date, $openPeriod): array {
            DB::table('family_receipt_reversal_keys')->upsert(
                [['school_id' => $branch, 'operation_key' => $keyHash, 'payload_hash' => $payloadHash]],
                ['school_id', 'operation_key'], ['operation_key'],
            );
            $operation = DB::table('family_receipt_reversal_keys')->where('school_id', $branch)
                ->where('operation_key', $keyHash)->lockForUpdate()->first();
            if ($operation->payload_hash !== $payloadHash) {
                throw ValidationException::withMessages(['key' => 'This key already belongs to another reversal request.'])->status(409);
            }
            if ($operation->reversal_id !== null) {
                return ['reversal_id' => (int) $operation->reversal_id];
            }
            if (! isset($openPeriod[0], $openPeriod[1]) || ! $this->validDate($openPeriod[0]) || ! $this->validDate($openPeriod[1])
                || $date < $openPeriod[0] || $date > $openPeriod[1]) {
                throw ValidationException::withMessages(['period' => 'The reversal date must be within a verified open period.']);
            }

            $receipt = DB::table('posted_family_receipts')->where('school_id', $branch)->where('id', $receiptId)->lockForUpdate()->first();
            if ($receipt === null) {
                throw ValidationException::withMessages(['receipt' => 'The original receipt is unavailable.']);
            }
            if ($date < $receipt->received_on) {
                throw ValidationException::withMessages(['period' => 'The reversal cannot predate receipt of funds.']);
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

            $time = now();
            $context = [
                'school_id' => $branch, 'receipt_id' => $receiptId, 'actor_id' => $actor,
                'currency' => $receipt->currency, 'amount_minor' => $receipt->amount_minor,
                'reason_code' => $reason, 'recorded_at' => $time,
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

    private function validDate(mixed $date): bool
    {
        return is_string($date) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $parts)
            && (int) $parts[1] >= 1000 && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }
}
