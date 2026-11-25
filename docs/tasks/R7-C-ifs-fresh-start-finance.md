# R7-C · IFS fresh-start finance readiness

## Outcome

Prepare a branch-scoped production release path for IFS with zero opening balances. The confirmed fresh-start decision carries no historical data into the new ledger. The effective launch date and target branch must be recorded before activation.

## Read

- `docs/R6-Q-reconciled-reporting-and-cutover.md`
- `backend/app/Http/Controllers/Api/{PostedFamilyReceiptController,BranchAccountingPeriodController,BranchFinanceAuditController,BranchFinanceReconciliationReportController}.php`
- `backend/tests/Feature/BranchFinanceReaderRecoveryTest.php`
- `backend/config/finance_reporting.php`
- `.github/pull_request_template.md`

## Touch

- `backend/config/finance_reporting.php` (explicit operational branch and PKR configuration, disabled by default)
- `backend/app/Services/BranchFinanceAvailability.php` (new shared branch gate)
- The four finance API controllers listed under Read, including the workspace grant checks in PostedFamilyReceiptController
- `backend/tests/Feature/BranchFinanceAvailabilityTest.php` (new)
- `docs/PROGRESS.md` and `docs/DECISIONS.md`

## Acceptance

- Operational finance is off by default. An explicit deployment-side enablement selects one approved IFS branch and PKR. Preserve the existing synthetic pilot path, branch membership checks, explicit permissions, and applicable read and write gates.
- Verify that the enabled branch can use the receipt, period, audit and reporting flows through the normal workspace entry points. Confirm other branches and ungranted users are denied using direct requests.
- Rehearse on current-schema MySQL. Verify zero opening balances, open the effective local period in Asia/Karachi, post and replay a synthetic receipt, reverse it, and confirm exact balances and audit links.
- Document the effective cutoff, accountable owners, backup verification and write boundary. Verify rollback can stop new writes while preserving ledger history and authorised audit access.
- Leave existing IFS records intact. Production configuration and activation are a separate deployment step on the approved empty target.

Frontend build: no.

## Deployment checklist

- Record the verified production branch ID, business date in Asia/Karachi, finance owner, technical owner and rollback owner. Capture the backup identifier, restore evidence and ledger and audit high-water marks.
- Start with writes paused. Set `FINANCE_OPERATIONAL_ENABLED=true`, `FINANCE_OPERATIONAL_BRANCH_ID` to the verified ID and `FINANCE_OPERATIONAL_CURRENCY=PKR`. Refresh the application configuration and verify all opening controls are zero on the approved target.
- Verify explicit grants and paid Reports access. Coordinate a write boundary with finance and confirm staff will use the agreed receipt workflow. Obtain deployment approval before enabling writes and setting up the opening period.
- To pause v4 writes, set `FINANCE_OPERATIONAL_WRITES_ENABLED=false` and refresh configuration. Verify direct receipt, reversal and period write requests are denied and authorised audit and reconciliation still load. Reconcile any requests in flight at the boundary.
