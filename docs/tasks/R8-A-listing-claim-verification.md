# R8-A · Listing claim email verification

## Outcome

Require claimants to verify access to their account email before a new listing claim can be approved. Keep the existing Super Admin review of affiliation and authority. Email verification proves mailbox access only.

## Read

- `docs/PROGRESS.md`, manager-pack claim rows
- `backend/app/Http/Controllers/InstituteClaimController.php`
- `backend/routes/web.php`, listing claim routes
- `backend/routes/api.php`, admin claim review routes
- `backend/database/migrations/2026_11_19_125247_create_v4_platform_entitlements.php`, claim and OTP definitions
- `backend/resources/views/directory/institute-claim.blade.php`
- `backend/resources/js/pages/Admin/Claims.jsx`
- `backend/tests/Feature/InstituteClaimTest.php`
- `.github/pull_request_template.md`

## Touch

- `backend/app/Http/Controllers/InstituteClaimController.php`
- `backend/app/Services/ListingClaimVerification.php` (new)
- `backend/app/Notifications/ListingClaimVerificationCode.php` (new)
- `backend/routes/web.php`, listing claim routes only
- One new migration ending in `_add_listing_claim_verification.php` for claim-bound challenges and the verification state
- `backend/resources/views/directory/institute-claim.blade.php`
- `backend/resources/js/pages/Admin/Claims.jsx`
- `backend/tests/Feature/InstituteClaimTest.php`
- `docs/PROGRESS.md` and `docs/DECISIONS.md`

## Behaviour and acceptance

- Send a short-lived, single-use code to the authenticated claimant's account email using the existing mail transport. Bind each challenge to the claim, claimant and email. Store only a protected verifier. Resending invalidates the previous code. Apply rate limits to sends and attempts. Cover expiration, retries and concurrent use.
- Reuse an eligible pending claim for repeat submissions by the same claimant for the same listing. Verify submitting or verifying a claim never grants membership by itself. Enforce claimant ownership on the server.
- Show the verification state in the admin queue and reject approval of unverified new claims on the server. For pre-existing pending claims, require verification before approval as well. Preserve existing decisions on approved and rejected claims.
- Test delivery with a mail fake, invalid and expired codes, replay, resending, isolation, duplicate submissions and approvals, and the existing transactional approval flow. No real messages during tests.
- Production mail delivery verification is a separate deployment check.

Frontend build: yes.
