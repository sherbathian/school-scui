# R6-K · Require an open accounting period for receipt writes

## Outcome

Make internal receipt posting and reversal use the branch accounting-period service. Derive the accounting date on the server and serialize financial writes with period closure.

## Read

- `docs/R6-I-accounting-period-authority.md`
- `backend/app/Services/{BranchAccountingPeriods,PostFamilyReceipt,ReverseFamilyReceipt}.php`
- `backend/tests/Feature/{BranchAccountingPeriods,PostFamilyReceipt}Test.php`
- The receipt posting, receipt reversal and branch accounting period migrations
- `.github/pull_request_template.md`

## Touch (proposed for approval)

- `backend/app/Services/{BranchAccountingPeriods,PostFamilyReceipt,ReverseFamilyReceipt}.php`
- One new timestamped migration ending in `_add_accounting_context_to_family_receipt_writes.php`
- `backend/tests/Feature/PostFamilyReceiptTest.php`
- `docs/PROGRESS.md`
- Temporary synthetic MySQL concurrency harness outside the repository

## Behaviour

- Acquire the branch lock first, then resolve the operation key. Return an identical committed replay even if its period has since closed. New writes require a valid timezone and an open period covering the server-derived branch business date. A failed attempt leaves the key reusable.
- Persist the accounting date, branch-scoped period ID, timezone and UTC timestamp atomically with each new receipt or reversal and its audit event. Keep the funds-received date separate. Reject future received dates. For this slice, approve earlier received dates only when backed by a matching same-branch imported receipt; a later task may add other evidence.
- Derive the reversal date from the current branch period. Preserve the original receipt and accounting context. A closed prior period can be corrected by a full reversal in the current open period. Retain existing pre-integration rows as unassigned history; require separate approval before assigning periods or reversing a receipt whose original accounting context is missing.
- Keep server-derived context out of request fingerprints. Preserve replay compatibility for pre-integration keys where the original request can be recognised without inferring a period.
- Keep the work internal. No authenticated endpoints, production settings, legacy finance changes, or report cutover.

## Acceptance tests

- Verify missing and closed periods reject new writes. Verify timezone midnight, future received dates, authorised historical import dates, cross-period reversal and replay after closure.
- Test both lock orders for period closure racing with posting or reversal on disposable MySQL. Verify the resulting posting, audit and idempotency rows in each case.
- Test migration up and down on disposable SQLite and MySQL. Run the full suite before pushing.

Frontend build: no
