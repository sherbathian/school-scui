# R6 · Billing consolidation assessment

## Outcome

Map current billing write paths, identify which records drive balances and receipts, and propose a consolidation plan with reconciliation and a finance audit trail. This assessment authorises source review and synthetic testing only. A separate implementation brief must specify which finance code and schema can change.

## Read (proposed for approval)

- Finance routes and relevant migrations, models and test entries in `docs/REPO-MAP.md`.
- `backend/app/Http/Controllers/Api/FeeInvoiceController.php`
- `backend/app/Http/Controllers/Api/FamilyFeeReceiptController.php`
- `backend/app/Http/Controllers/Api/FeeMasterController.php`
- `backend/app/Http/Controllers/Api/FeeDiscountController.php`
- `backend/app/Http/Controllers/Api/ExpenseController.php`
- `backend/app/Models/FeeInvoice.php`
- `backend/app/Models/FeePayment.php`
- `backend/app/Models/FamilyFeeReceipt.php`
- `backend/app/Models/StudentFeeCharge.php`
- `backend/app/Models/Expense.php`
- `backend/tests/Feature/FeeManagementTest.php`
- `backend/tests/Feature/FeeStructureTest.php`

## Touch (proposed for approval)

- `docs/R6-billing-consolidation-assessment.md` (new)
- `docs/PROGRESS.md`
- `docs/DECISIONS.md` (approved decisions only)

## Acceptance criteria

- Trace fees from setup through charge, invoice, payment allocation, receipt and balance. Map legacy cents-based records and v4 fee setup, state which path writes each record, and flag any gaps that need further source review.
- Document the amount units, currency handling, branch ownership, and what a branch and organisation can reconcile today. Identify potential duplicate counting, rounding and historical balance risks.
- Propose the sequence for consolidating billing, including how to reconcile historical balances before and after cutover, handle retries, and recover if reconciliation fails. Separate implemented behaviour from proposed changes.
- Specify finance audit events for fee setup, discounts, posting, receipts, reversals or refunds, and expenses, where applicable. Recommend branch-scoped access and store record identifiers and relevant amounts or statuses without banking or card details.
- Run existing targeted finance tests using synthetic data. Provide manual scenarios for a partial payment, a family receipt across two students, a discount, a retry, a reversal and cross-branch access. Identify scenarios that require future implementation.
- No finance code, schema, live data, or configuration changes.

Frontend build: no
