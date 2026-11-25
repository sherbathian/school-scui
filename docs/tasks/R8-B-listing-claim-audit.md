# R8-B · Audit listing and claim decisions

## Outcome

Show who verified, published, or suspended an institution listing, and who approved or declined a claim, in the existing branch-scoped Audit Log.

## Read

- `backend/app/Http/Controllers/Api/AdminListingController.php`
- `backend/app/Http/Controllers/InstituteClaimController.php`, admin review only
- `backend/app/Services/BranchAuditLog.php`
- `backend/tests/Feature/AdminListingTest.php`
- `backend/tests/Feature/InstituteClaimTest.php`
- `.github/pull_request_template.md`

## Touch

- `backend/app/Services/BranchAuditLog.php`, register filter action names

- `backend/app/Http/Controllers/Api/AdminListingController.php`
- `backend/app/Http/Controllers/InstituteClaimController.php`
- `backend/tests/Feature/AdminListingTest.php`
- `backend/tests/Feature/InstituteClaimTest.php`
- `docs/PROGRESS.md` and `docs/DECISIONS.md`

## Acceptance

- Record listing verification, publication and suspension, and claim approval and decline using the existing audit service. Include the actor, branch, target, action, timestamp, and a minimal before/after status payload. Assign the audit entry to the same transaction as the state change.
- When an approval declines competing claims, record each resulting state change with the originating approval as its cause.
- Rejected requests and retries that leave state unchanged create no duplicate events. Test rollback and ensure codes, email addresses, phone numbers and free-text notes stay out of the audit payload.
- Confirm existing Audit Log permissions and branch scoping apply. Preserve public listing eligibility and the claim approval workflow.

Frontend build: no.
