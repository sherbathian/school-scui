<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ListingClaimVerificationCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class ListingClaimVerification
{
    public function send(int $school, User $user): void
    {
        [$claimId, $code] = DB::transaction(function () use ($school, $user): array {
            $claim = $this->pendingClaim($school, $user);
            abort_if($claim->email_verified_at && $claim->email === $user->email, 409);
            $codes = DB::table('listing_claim_verification_codes');
            $previous = (clone $codes)->where('listing_claim_id', $claim->id)->first();
            if ($previous && Carbon::parse($previous->sent_at)->addMinute()->isFuture()) {
                throw ValidationException::withMessages(['code' => 'Wait one minute before requesting another code.']);
            }

            do {
                $code = (string) random_int(100000, 999999);
                $verifier = $this->verifier($claim->id, $user, $code);
            } while ($previous?->code_hash === $verifier);

            $codes->updateOrInsert(['listing_claim_id' => $claim->id], [
                'email' => $user->email, 'code_hash' => $verifier,
                'attempts' => 0, 'sent_at' => now(), 'expires_at' => now()->addMinutes(10),
            ]);

            return [$claim->id, $code];
        }, 3);

        try {
            Notification::route('mail', $user->email)->notify(new ListingClaimVerificationCode($code));
        } catch (TransportExceptionInterface) {
            DB::table('listing_claim_verification_codes')->where('listing_claim_id', $claimId)
                ->where('code_hash', $this->verifier($claimId, $user, $code))->delete();
            throw ValidationException::withMessages(['code' => 'The code could not be sent. Try again later.']);
        }
    }

    public function verify(int $school, User $user, string $code): void
    {
        $verified = DB::transaction(function () use ($school, $user, $code): bool {
            $claim = $this->pendingClaim($school, $user);
            $codes = DB::table('listing_claim_verification_codes')->where('listing_claim_id', $claim->id);
            $challenge = $codes->first();
            if (! $challenge || $challenge->email !== $user->email || $challenge->attempts >= 5
                || Carbon::parse($challenge->expires_at)->lte(now())) {
                return false;
            }
            if (! hash_equals($challenge->code_hash, $this->verifier($claim->id, $user, $code))) {
                $codes->increment('attempts');

                return false;
            }

            DB::table('listing_claims')->where('id', $claim->id)->update([
                'email' => $user->email, 'email_verified_at' => now(), 'updated_at' => now(),
            ]);
            $codes->delete();

            return true;
        }, 3);

        if (! $verified) {
            throw ValidationException::withMessages(['code' => 'The code is invalid, expired, or used. Request a new code if needed.']);
        }
    }

    private function pendingClaim(int $school, User $user): object
    {
        return DB::table('listing_claims')->where('school_id', $school)->where('user_id', $user->id)
            ->where('status', 'pending')->latest('id')->lockForUpdate()->firstOrFail();
    }

    private function verifier(int $claim, User $user, string $code): string
    {
        return hash_hmac('sha256', json_encode([$claim, $user->id, $user->email, $code], JSON_THROW_ON_ERROR), config('app.key'));
    }
}
