# R6-H · Internal full family receipt reversal

## Outcome

Add an internal service to reverse a posted v4 receipt once. Preserve the original receipt and its allocations, restore the charge balances, and record the reversal and audit event atomically.

## Read

- `docs/R6-F-receipt-posting-rules.md`
- `backend/app/Services/PostFamilyReceipt.php`
- `backend/database/migrations/2026_11_25_235901_create_family_receipt_posting_tables.php`
- `backend/tests/Feature/PostFamilyReceiptTest.php`
- `.github/pull_request_template.md`

## Touch (proposed for approval)

- `backend/app/Services/ReverseFamilyReceipt.php` (new)
- `backend/app/Services/PostFamilyReceipt.php` (account for committed reversals when calculating available balance)
- One new timestamped migration under `backend/database/migrations/` ending in `_create_family_receipt_reversal_tables.php`
- `backend/tests/Feature/PostFamilyReceiptTest.php`
- `docs/PROGRESS.md`
- Temporary synthetic MySQL concurrency harness outside the repository

## Behaviour and acceptance

- Accept an authorised caller context, branch, original receipt ID, operation key, and controlled reason code. Require a verified open-period context from the caller for a new reversal. Test this with synthetic dates. A separate task must establish the production period authority and branch timezone before exposing the service.
- Reserve a branch-scoped reversal key, lock the original receipt and then its charges in ascending ID order. Use the same charge lock and committed-balance rules as posting. Link one immutable reversal to the original receipt and offset all its allocations in full. Preserve the original records and their dates.
- Write the reversal, replay result and finance event in one transaction. Record actor, branch, currency, original and reversal references, controlled reason, and before/after charge balances. An audit failure rolls everything back.
- An identical retry returns the original result. Changed input under the same key or a second key for an already reversed receipt conflicts without restoring balances again.
- Verify that reversing a PKR 600.00 allocation restores exactly PKR 600.00 and allows a subsequent valid posting. Test split receipts, foreign receipts, invalid periods, rollback, and same-key retries. On disposable MySQL, test simultaneous reversals and a reversal racing with a new posting.
- Test migration up and down on disposable databases, run the full suite before pushing, and preserve existing legacy readers and writers.

No HTTP or UI entry points, live reversals, refunds, partial reversals, or report cutover. Frontend build: no.
