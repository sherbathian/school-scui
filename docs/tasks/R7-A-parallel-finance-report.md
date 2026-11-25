# R7-A · Parallel read-only finance report

## Outcome

Add a branch finance report in the synthetic pilot. Show legacy controls and v4 ledger totals side by side for an explicit month and inclusive cutoff. This report is for reconciliation before any production switch.

## Read

- `docs/R6-Q-reconciled-reporting-and-cutover.md`
- `backend/app/Http/Controllers/{AdminDashboard,AdminReport}Controller.php`, finance totals and report registration only
- `backend/app/Http/Controllers/Api/BranchFinanceAuditController.php`
- `backend/app/Services/{BranchModules,SchoolRoles,BillingReconciliationPreview}.php`
- `backend/app/Models/User.php`
- Relevant finance table definitions and pilot route group in `backend/routes/api.php`
- `backend/resources/js/pages/Admin/{BranchWorkspace.jsx,branch/FinancePilot.jsx}`
- `backend/tests/Feature/BranchFinanceAuditApiTest.php`
- `.github/pull_request_template.md`

## Touch (proposed for approval)

- `backend/app/Services/BranchFinanceReconciliationReport.php` (new)
- `backend/app/Http/Controllers/Api/BranchFinanceReconciliationReportController.php` (new)
- `backend/routes/api.php` (one read-only pilot route)
- `backend/app/Http/Controllers/Api/PostedFamilyReceiptController.php` (workspace visibility grant)
- `backend/app/Services/SchoolRoles.php` (exclude the new permission from default roles)
- One new migration ending in `_register_finance_reconciliation_report_permission.php`
- `backend/resources/js/pages/Admin/branch/FinancePilot.jsx`
- `backend/tests/Feature/BranchFinanceReconciliationReportTest.php` (new)
- `docs/PROGRESS.md` and `docs/DECISIONS.md` (approved decisions only)

## Behaviour and acceptance

- Require active access to the branch, an explicit `fees.reconciliation.view` grant, the Fees and Reports read gates, and the existing testing-only PKR pilot gate. Apply the same paid Reports eligibility rule used in the Report Centre. Register the permission without granting it to default roles. Return aggregate fields only and prevent shared caching.
- Require an explicit month and cutoff within it. Label legacy receipt totals by payment date, and show the current open-invoice dashboard count separately from the legacy cutoff balance. Label v4 activity by branch accounting date. Show v4 gross receipts, reversal offsets, net movement, and remaining charge balances at cutoff. Earlier receipts affect the cutoff balance. Later reversals must leave earlier cutoff balances unchanged.
- Keep sources separate. Include no combined collections or receivables figure. Imported receipt evidence and monthly snapshots never contribute to cash totals. Flag relevant unassigned v4 rows and avoid presenting affected totals as reconciled.
- Verify both R6-Q cutoffs against independent controls, including the November split receipt, December reversal, December net of PKR -650, and a foreign-branch control. Test missing grants, disabled modules, branch scope, invalid cutoff, and no finance writes. Verify the workspace on desktop and mobile using synthetic data.

Existing dashboard totals, production activation, exports, historical backfill and the recovery drill are outside this slice. Frontend build: yes.
