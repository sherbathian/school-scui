# R6-P · Repeatable finance pilot and manual acceptance

## Outcome

Make the finance workspace reproducible on an isolated, loopback-only testing instance. Use synthetic PKR fixtures with separate poster, reverser, period manager, auditor, and out-of-branch accounts. Document how to start, verify and stop the pilot.

## Read (proposed)

- `docs/R6-F-receipt-posting-rules.md` and `docs/R6-I-accounting-period-authority.md`
- `backend/app/Http/Controllers/Api/{PostedFamilyReceipt,BranchAccountingPeriod,BranchFinanceAudit}Controller.php`
- `backend/resources/js/pages/Admin/branch/FinancePilot.jsx`
- `backend/tests/Feature/{PostedFamilyReceiptApi,BranchAccountingPeriodApi,BranchFinanceAuditApi}Test.php`
- `.github/pull_request_template.md`

## Touch (proposed)

- `docs/R6-P-finance-pilot-acceptance.md` (setup, test results and recovery steps)
- `docs/PROGRESS.md`
- Temporary synthetic fixture and loopback server scripts outside the repository

## Acceptance

- Verify the disposable database connection before migration, seeding or posting. Keep the local application and production databases out of scope.
- Run the workspace with separate roles. Verify direct requests and navigation for each grant, an outside-branch user, and a disabled Fees module.
- Post partial and split receipts. Simulate a lost response and confirm the same key, one receipt and one audit event. Verify that exceeding a remaining balance is rejected.
- Reverse a receipt in full, verify the balance restores once, and confirm the original remains in audit history. Check closed-period denial and replay after closure.
- Verify accounting dates across Asia/Karachi month-end, audit UTC filtering, desktop and mobile use. Record verified, failed and untested scenarios with expected and actual results.
- Compare final receipt, allocation, reversal, and audit counts and balances against independent controls. Record any defects for a separately approved implementation task.

No production activation, schema change, finance code changes, or report cutover. Frontend build: yes for browser verification.
