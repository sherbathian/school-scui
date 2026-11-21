# T10 · Claims data layer

Effort: **low** · Depends on: T05 · Frontend build: **no** · Decision needed: none

## Goal
Create the tables and models for claims and one-time codes with their state rules. No screens.

## Read ONLY these
- `AGENTS.md`
- `docs/specs/spec-claims.md`
- `backend/app/Models/School.php`

## Touch ONLY these
- `backend/database/migrations/` new files for listing_claims and otp_codes (and any `schools` claim columns still missing per `docs/SCHEMA-GAP.md`)
- `backend/app/Models/ListingClaim.php`, `OtpCode.php` (new)
- `backend/database/factories/` for both
- `backend/tests/Feature/ClaimSchemaTest.php` (new)

## Out of scope (do not do)
- Routes, services, UI (T12, T13)

## Tests first (write, run red, then build)
1. A school can have only one pending claim at a time.
2. Claim statuses are limited to pending, verified, rejected, withdrawn.
3. A claim without a school or user is refused by the database.
4. `OtpCode` stores only a hash and has an expiry and an attempts counter.
5. `schools.claim_status` moves unclaimed → pending → claimed through model methods, and refuses impossible moves.

## Build
- Interpretation (follow unless the manager says otherwise): `listing_claims.verified_at` = the claimant's contact was proven by code; `status = verified` = a platform admin approved the claim.

## Acceptance
- [ ] Migration up/down on SQLite; new tests green; existing tests green.
- [ ] Existing tests still green; no file outside the Touch list changed (or the reason is stated in the PR).

## Finish
Targeted tests green, then the whole suite once, then push branch `t10/claims-data-layer`, open the PR, update `docs/PROGRESS.md`, and give the 12-line final answer from `AGENTS.md`.
