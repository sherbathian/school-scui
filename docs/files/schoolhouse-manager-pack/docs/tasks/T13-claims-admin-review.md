# T13 · Claims: admin review queue and approval

Effort: **high** · Depends on: T12, T05 · Frontend build: **yes** · Decision needed: D1 organisation provisioning (default: create the organisation automatically when a claim is approved)

## Goal
Platform admins review claims. Approving is atomic and makes the claimant the owner of the organisation.

## Read ONLY these
- `AGENTS.md`
- `docs/specs/spec-claims.md`
- `docs/specs/spec-organisations.md` (rules only)
- `docs/specs/admin-ui.md`
- `backend/app/Services/Claims/*.php`
- `backend/app/Models/SchoolGroup.php`

## Touch ONLY these
- `backend/app/Http/Controllers/Admin/ClaimController.php` (new)
- `backend/app/Actions/Claims/ApproveClaim.php`, `RejectClaim.php` (new)
- `backend/routes/web.php` (admin routes)
- `backend/resources/js/pages/Claims/Index.jsx`, `Show.jsx` (new); sidebar entry
- `backend/tests/Feature/ClaimReviewTest.php`, `ClaimApprovalRaceTest.php` (new)

## Out of scope (do not do)
- Plan assignment on approval (a later task)
- Notifications beyond one simple email

## Tests first (write, run red, then build)
1. The queue lists pending claims with filters and shows the claimant's contact proof (code verified or not).
2. Only a platform admin can approve or reject (403 for everyone else, including owners).
3. Approving runs in a transaction: sets `status = verified`, `schools.claim_status = claimed`, creates the organisation if none exists, links the branch, and makes the claimant an owner (`school_group_user`).
4. Two admins approving the same claim at once: exactly one succeeds and the other gets a clear refusal (test with two sequential calls on a locked row and with the unique rule).
5. Rejecting stores the reason and lets the user resubmit.
6. Expired pending claims are closed by a scheduled command and the listing returns to unclaimed.
7. Approving never changes whether the listing is public.

## Build
- Use `lockForUpdate()` inside the transaction.

## Acceptance
- [ ] New and existing tests green; build green.
- [ ] Existing tests still green; no file outside the Touch list changed (or the reason is stated in the PR).

## Finish
Targeted tests green, then the whole suite once, then push branch `t13/claims-admin-review`, open the PR, update `docs/PROGRESS.md`, and give the 12-line final answer from `AGENTS.md`.
