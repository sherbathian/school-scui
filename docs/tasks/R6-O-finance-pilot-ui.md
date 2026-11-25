# R6-O · Branch finance pilot UI

## Outcome

Add a v4 receipt workspace under Branch Operations for the configured synthetic pilot. Let authorised users post receipts, reverse a posted receipt in full, manage accounting periods, or review finance audit events according to their explicit grants.

## Read

- R6-F posting rules and R6-I period authority designs
- `backend/routes/web.php` and `backend/routes/api.php`, branch workspace and finance routes only
- `backend/app/Http/Controllers/Api/{PostedFamilyReceipt,BranchAccountingPeriod,BranchFinanceAudit}Controller.php`
- Existing branch workspace, navigation and API helper files listed below
- `.github/pull_request_template.md`

## Touch (proposed for approval)

- `backend/routes/web.php`, existing Branch Operations page props only
- `backend/routes/api.php`, pilot finance routes only
- `backend/app/Http/Controllers/Api/PostedFamilyReceiptController.php`, scoped posting options and receipt list reads
- `backend/resources/js/pages/Admin/BranchWorkspace.jsx`
- `backend/resources/js/pages/Admin/branch/FinancePilot.jsx` (new)
- `backend/tests/Feature/PostedFamilyReceiptApiTest.php`
- `docs/PROGRESS.md`

## Behaviour and verification

- Show the workspace only for the configured synthetic branch and users with a finance pilot grant. Present posting, reversal, period administration and audit actions according to their separate server-verified grants. Existing module gates remain authoritative.
- Provide branch-scoped, permission-checked reads for selectable monthly charges and posted receipts, so users do not have to enter record IDs manually. Return only the fields needed for the workflow.
- Use exact minor-unit amounts. Keep one idempotency key for a retry of the same operation and generate a new key if the user changes the request. Show validation errors and preserve input when a request fails.
- Show timezone and period state, and support full reversals with a controlled reason. Display audit events with pagination and distinguish UTC recorded time from branch accounting date.
- Verify permissions, list scoping, and disabled states with synthetic data. Test form validation, receipt posting, retry, reversal, and audit visibility on desktop and mobile in a disposable test environment.

No production activation, legacy finance changes, migration, or report cutover. Frontend build: yes.
