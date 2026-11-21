# T12 · Claims: submit and prove contact with a one-time code

Effort: **high** · Depends on: T10, T11 · Frontend build: **yes** · Decision needed: D4 OTP channel (default: email now, SMS later through the same interface)

## Goal
A signed-in user can claim an unclaimed published listing and prove the contact detail with a one-time code.

## Read ONLY these
- `AGENTS.md`
- `docs/specs/spec-claims.md`
- `backend/app/Models/ListingClaim.php`, `OtpCode.php`
- `backend/app/Http/Controllers/PublicDirectoryController.php` (profile method only)
- `backend/resources/views/directory/` (profile view name only)

## Touch ONLY these
- `backend/app/Services/Claims/*.php` (new: ClaimSubmission, OtpIssuer, OtpVerifier)
- `backend/app/Notifications/ClaimCodeNotification.php` (new)
- `backend/app/Http/Controllers/Claim/*.php` (new)
- `backend/routes/web.php` (claim routes)
- Minimal claim pages (Inertia) `backend/resources/js/pages/Claim/*.jsx`
- `backend/tests/Feature/ClaimSubmissionTest.php`, `ClaimOtpTest.php` (new)

## Out of scope (do not do)
- Admin review and organisation creation (T13)
- SMS provider integration
- Changing which listings are public

## Tests first (write, run red, then build)
1. A guest is sent to sign in; a signed-in user can submit a claim for an unclaimed published listing.
2. A second pending claim on the same listing is refused (by anyone).
3. A listing that is already claimed, unverified or suspended cannot be claimed.
4. The code is stored hashed, expires after the configured time, and allows a limited number of attempts, then locks.
5. Requesting codes is rate-limited per user and per destination.
6. Entering the right code sets `verified_at`; claim stays pending for admin review.
7. Submitting a claim never changes whether the listing is public.

## Build
- The notification uses a channel interface so SMS can be added later; default channel is mail (use the `log`/`array` mailer in tests).
- Config values: code length, expiry minutes, max attempts, claim expiry days (default 14).

## Acceptance
- [ ] New and existing tests green; build green.
- [ ] Existing tests still green; no file outside the Touch list changed (or the reason is stated in the PR).

## Finish
Targeted tests green, then the whole suite once, then push branch `t12/claims-submit-and-otp`, open the PR, update `docs/PROGRESS.md`, and give the 12-line final answer from `AGENTS.md`.
