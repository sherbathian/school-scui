# R6-Q · Reconciled reporting and cutover review

## Outcome

Compare legacy reporting and the v4 ledger side by side on disposable synthetic data. Deliver a report with independent SQL controls, a cutover checklist and a recovery plan. Actual report switching, production activation and historical backfill require separate approval.

## Read (proposed)

- `docs/R6-billing-source-inventory.md`, `docs/R6-F-receipt-posting-rules.md`, and `docs/R6-P-finance-pilot-acceptance.md`
- `backend/app/Http/Controllers/{AdminDashboard,AdminReport}Controller.php`, finance totals and report registration only
- `backend/app/Services/{BillingReconciliationPreview,PostFamilyReceipt,ReverseFamilyReceipt}.php`
- Finance schema definitions for legacy invoices and payments, v4 monthly charges, posted receipts and allocations, reversals, accounting periods and audit events
- `backend/tests/Feature/{BillingReconciliationPreview,PostFamilyReceipt,AdminDashboard}Test.php`, relevant methods only
- Existing local R6-P synthetic harness, if available

## Touch (proposed)

- `docs/R6-Q-reconciled-reporting-and-cutover.md` (new)
- `docs/PROGRESS.md`
- Temporary synthetic fixtures, reporting comparisons and SQL controls outside the repository

## Acceptance checks

- Reproduce current legacy dashboard counts and monthly totals. Independently calculate v4 gross posted receipts, reversals, net receipts and remaining charge balances by branch, currency and accounting period. Record expected and actual values and explain differences in dates, statuses and sources.
- Include a split receipt, a reversal in a later period, an overlapping legacy/v4 obligation with explicit mapping evidence, a legacy-only obligation, and an outside-branch control. Verify that imported family receipts and historical snapshots do not add to posted collections.
- Verify the comparison computes gross, offsets and net using the accounting date of each event. For outstanding balances at a cutoff, include only postings and reversals effective at that cutoff. Keep legacy and v4 totals separate whenever a match cannot be proved.
- Document an implementation and rollout plan with source ownership, permissions, variance thresholds, sign-off, and a recovery drill. Preserve audit history and protect any entries made after a future switch. Require zero unexplained variance and separate approval before switching production reporting.
- Use synthetic data only. Record any unresolved reporting dependencies or missing historical evidence. No changes to application code, schema, finance configuration, report routing or live data.

Frontend build: no.
