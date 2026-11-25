# R6 follow-up · Billing source inventory

## Outcome

Verify the finance source gaps identified in the R6 assessment and deliver a source inventory and a proposed first implementation slice for separate approval. Keep the finance freeze in place.

## Read (proposed)

- `docs/R6-billing-consolidation-assessment.md` and the finance-related entries in `docs/REPO-MAP.md`.
- `backend/routes/api.php` and `backend/routes/web.php`, finance and relevant admin routes only.
- Under `backend/app/Http/Controllers/`: `AdminDashboardController.php`, `AdminReportController.php`, and `Api/{LegacyFeeType,FeeType,FeeGroup,FeeMaster,FeeDiscount,FeeInvoice,FamilyFeeReceipt,Expense,Student}Controller.php`, finance-related methods only.
- Under `backend/app/Models/`: `{FeeInvoice,FeePayment,FamilyFeeReceipt,LegacyStudentFeeSnapshot,StudentFeeCharge,Expense}.php`.
- Migrations under `backend/database/migrations/` that create or alter `fee_types`, `legacy_fee_types`, `fee_groups`, `fee_group_grade`, `fee_masters`, `fee_discounts`, `student_fee_discounts`, `student_fee_charges`, `fee_invoices`, `fee_payments`, `family_fee_receipts`, `legacy_student_fee_snapshots`, `expenses` or `expense_heads`. Read only matching definitions and constraints.
- Under `backend/resources/js/pages/Admin/branch/`: `{FeeManagement,FeeSetup,DataTools,ExpenseManagement,StudentRoster}.jsx`, finance-related sections only.
- `backend/tests/Feature/{FeeManagement,FeeStructure,AdminDashboard,BranchReferenceIntegrity}Test.php`, relevant methods only.
- If a reviewed path delegates to a file outside this list, record the unresolved path and propose it for approval.

## Touch (proposed)

- `docs/R6-billing-source-inventory.md` (new)
- `docs/PROGRESS.md`
- `docs/DECISIONS.md` (approved decisions only)

## Acceptance checks

- Map each reviewed write, import and balance reader to its source with file and symbol references.
- For each relevant table verify money precision, currency fields, composite branch constraints, uniqueness, foreign keys and delete rules. Separate database guarantees from controller validation.
- Record which sources actually feed dashboard and report totals, including date, status and branch filtering. Identify financial totals that currently have no report.
- Map how duplicate requests are handled, how records can be matched, and where scope or reconciliation gaps remain. Run targeted tests on synthetic data and mark any unverified assumptions.
- Deliver a recommended first implementation slice with exact proposed Read and Touch paths, acceptance tests, reconciliation gates and deployment or rollback assumptions.
- Do not change finance code, schema, configuration or live data.

Frontend build: no
