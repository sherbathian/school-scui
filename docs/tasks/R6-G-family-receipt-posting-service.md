# R6-G · Internal family receipt posting service

## Outcome

Implement an internal service that atomically posts a family receipt and its allocations to v4 monthly charges. Test it with synthetic data before adding an API, UI or production posting flow.

## Read

- `docs/R6-F-receipt-posting-rules.md`
- `docs/R6-billing-source-inventory.md`
- `backend/app/Models/{FamilyFeeReceipt,StudentFeeCharge,User}.php`
- `backend/app/Services/BillingObligationMappingPreview.php`
- Migration definitions for family receipts, student charges and branch reference keys listed in the source inventory.
- `.github/pull_request_template.md`

## Touch (proposed for approval)

- `backend/app/Services/PostFamilyReceipt.php` (new)
- One new timestamped migration in `backend/database/migrations/` ending in `_create_family_receipt_posting_tables.php`
- `backend/tests/Feature/PostFamilyReceiptTest.php` (new)
- `docs/PROGRESS.md`
- `docs/DECISIONS.md` (approved product decisions only)
- Temporary synthetic MySQL concurrency harness outside the repository

## Behaviour and acceptance

- Accept a validated actor, branch and currency context. Validate exact positive minor-unit amounts, supported pilot currency, and one allocation per charge. Require allocation amounts to add up to the receipt amount.
- Scope every charge and optional imported receipt reference to the specified branch. Use database constraints for branch consistency, per-receipt charge uniqueness, one posting per import, and unique branch-scoped operation keys. Preserve imported evidence as a separate source.
- Reserve the idempotency key and lock target charges in ascending ID order. Recalculate available balances using current committed allocations while holding the locks. A 1,000.00 charge with 600.00 allocated must reject 500.00 and accept 400.00.
- Commit the receipt, allocations, replay result and finance audit event in one transaction. Record actor, branch, timestamps, currency, record IDs and before/after balances. A failure must leave no partial receipt, allocation, audit event or consumed operation key.
- Equivalent retries return the original result. A changed payload with the same key conflicts. A second key cannot post the same import again.
- Verify exact and partial balances, invalid and foreign references, rollback, one audit event per success, and reversible migrations on disposable databases. Verify same-key and competing-balance requests concurrently on MySQL as well as running the repository suite.

No external entry points, reversals, live postings, legacy finance changes, or report cutover in this slice. Frontend build: no.
