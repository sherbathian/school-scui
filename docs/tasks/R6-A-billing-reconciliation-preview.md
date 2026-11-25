# R6-A · Billing reconciliation preview

## Outcome

Add a read-only Artisan command for an explicitly selected branch and inclusive cutoff date. Use an isolated database copy for verification. Report each billing source separately so an imported family receipt and a legacy payment can never be added together as collected cash.

## Read

- `docs/R6-billing-source-inventory.md` and `docs/R6-billing-consolidation-assessment.md`
- `backend/bootstrap/app.php` and `backend/routes/console.php`, command registration only
- `backend/app/Models/{FeeInvoice,FeePayment,FamilyFeeReceipt,LegacyStudentFeeSnapshot,StudentFeeCharge,Expense}.php`
- Finance table definitions referenced in the inventory, limited to the tables this command reads

## Touch (proposed for approval)

- `backend/app/Console/Commands/PreviewBillingReconciliation.php` (new)
- `backend/app/Services/BillingReconciliationPreview.php` (new)
- `backend/tests/Feature/BillingReconciliationPreviewTest.php` (new)
- `docs/PROGRESS.md`
- `docs/DECISIONS.md`, if an approved decision needs recording

## Behaviour

- Require a valid branch ID and a calendar date in YYYY-MM-DD format. Reject invalid input. Emit deterministic, machine-readable output with the branch ID, cutoff date, and date basis for each section.
- Include legacy invoices created by the end of the cutoff day and payments dated on or before the cutoff; only apply those payments to included invoices. Show gross, paid, collectible balances, and voids separately. Flag payments on voids, payments dated before invoice creation, and overpaid invoices. Label invoice status as current because the legacy schema has no historical void date.
- Show v4 charge counts and amounts by billing month through the cutoff month, family receipts by receipt date, and expenses by expense date. Label the month granularity for charges and snapshots. Preserve null receipt amounts as missing and flag family receipts as lacking verified allocations.
- Show legacy snapshots by billing month with received and balance amounts kept separate. Do not sum snapshot balances across periods.
- Use exact minor units for money, with no inferred currency. Exclude names, contact details, free-text references, raw imports and attachments. Perform no writes.
- Keep the finance freeze in place. No changes to existing posting flows, schema, reports, or live data.

## Acceptance tests

- Use synthetic data from two branches, with overlapping payment and receipt amounts. Assert complete branch isolation and separate source totals.
- Verify cent-level arithmetic, inclusive cutoff boundaries, current status labelling, void and overpayment exceptions, null receipts and monthly snapshot handling.
- Verify identical output for identical inputs, invalid branch and date errors, and zero database writes.
- Run the targeted suite on SQLite and use SQLite and MySQL CI before review.

Frontend build: no
