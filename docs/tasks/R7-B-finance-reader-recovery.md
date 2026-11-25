# R7-B · Pilot finance reader and rollback rehearsal

## Outcome

Give the synthetic Finance Pilot a selected reporting source that can be switched back without touching its transaction history. Use the R7-A parallel report as the independent comparison surface during the drill.

## Read

- `docs/R6-Q-reconciled-reporting-and-cutover.md`, recovery and rollout sections
- `backend/app/Services/BranchFinanceReconciliationReport.php`
- `backend/app/Http/Controllers/Api/BranchFinanceReconciliationReportController.php`
- `backend/tests/Feature/BranchFinanceReconciliationReportTest.php`
- `backend/resources/js/pages/Admin/branch/FinancePilot.jsx`
- `backend/routes/api.php`, pilot routes only
- `.github/pull_request_template.md`

## Touch

- `backend/app/Services/BranchFinanceReconciliationReport.php` (isolate the selected source queries)
- `backend/config/finance_reporting.php` (new, default legacy)
- `backend/app/Http/Controllers/Api/BranchFinanceReconciliationReportController.php`
- `backend/routes/api.php`, one new read-only pilot route
- `backend/resources/js/pages/Admin/branch/FinancePilot.jsx`
- `backend/tests/Feature/BranchFinanceReaderRecoveryTest.php` (new)
- `docs/PROGRESS.md` and `docs/DECISIONS.md`

## Behaviour

- Add a separate selected-source view beside the parallel report. Reuse the report calculations, branch checks, explicit reconciliation permission, Fees and Reports gates, and testing-only PKR pilot gate. The source is selected by trusted application configuration for the existing pilot branch. Request parameters cannot select it. Invalid configuration resolves to legacy.
- Label the source and show the source's own metrics and date basis. Mark v4 totals provisional when accounting context is incomplete. Preserve the parallel report as the comparison surface. The normal dashboard and production reports retain their existing readers.
- The switch affects aggregate reads only. Posting, reversal, periods, idempotency and audit continue to use their existing services. No schema changes.

## Acceptance

1. On a disposable environment with the full current schema and synthetic finance data, record the legacy and parallel totals and the receipt, reversal and audit high-water marks. Save the branch, currency and cutoff.
2. Select the v4 reader. Post a split receipt in November, post a second receipt in December, and reverse the November receipt in December. Verify November gross PKR 750.00 and remaining PKR 1,250.00. Verify December gross PKR 100.00, reversal PKR 750.00, net PKR −650.00 and remaining PKR 1,900.00 against independent controls.
3. Inject an incorrect result at the test reader boundary. Detect the variance, stop new pilot entry in the rehearsal, and switch back to legacy through configuration. Verify the legacy result matches the baseline and the parallel report remains reachable.
4. Verify the new ledger rows by ID and accounting period, the reversal-to-receipt link, audit event counts, and original idempotency keys. Replay the same requests and verify zero duplicate financial rows or audit events. Clear the injected mismatch and re-enable the candidate on the disposable environment. Recheck both cutoffs and record expected versus actual values.
5. Test default and invalid configuration, disabled modules, missing permission, foreign branch, and the production gate. Run the frontend build and verify source labels in the pilot workspace.

Production activation and changes to legacy billing, data mapping, exports, and live finance writes are outside this task.

Frontend build: yes.
