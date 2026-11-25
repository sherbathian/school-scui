# R6-B · Verify billing reconciliation on MySQL

## Outcome

Compare the merged R6-A preview with independent MySQL control queries. Use a newly created disposable MySQL database loaded with synthetic fixtures only. Document any differences and leave production and local application databases untouched.

## Read (proposed for approval)

- `docs/R6-billing-source-inventory.md`
- `backend/app/Console/Commands/PreviewBillingReconciliation.php`
- `backend/app/Services/BillingReconciliationPreview.php`
- `backend/tests/Feature/BillingReconciliationPreviewTest.php`
- Finance table definitions referenced in the inventory, limited to the tables the preview reads.

## Touch (proposed for approval)

- `docs/R6-B-billing-reconciliation-verification.md` (new)
- `docs/PROGRESS.md`
- Temporary fixture and control-query scripts outside the repository.

## Acceptance checks

- Verify the selected MySQL database is the newly created disposable database before migrating or inserting fixtures.
- Use two synthetic branches and include cutoff-day and next-day records, partial and overpayments, a void invoice, monthly charges and snapshots, nullable family receipt amounts, and expenses.
- Compare all source counts, amounts and exception counters to independent SQL. Keep legacy payments and family receipts separate even when their amounts overlap.
- Run the preview twice at the same cutoff and compare outputs. Record the expected and actual values and every variance.
- Identify what would still require a separately approved historical-data reconciliation before cutover. A passing synthetic run does not establish historical balances.
- Keep finance code, posting, and schema unchanged. Record any discovered defect for separate approval.

Frontend build: no
