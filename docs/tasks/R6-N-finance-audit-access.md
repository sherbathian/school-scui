# R6-N · Read-only branch finance audit API

## Outcome

Expose finance audit events to explicitly authorised branch users in the synthetic pilot. Include v4 receipt postings, full reversals, and accounting-period openings and closures.

## Read

- `docs/R6-F-receipt-posting-rules.md` and `docs/R6-I-accounting-period-authority.md`
- `backend/app/Http/Controllers/Api/{PostedFamilyReceipt,BranchAccountingPeriod}Controller.php`
- `backend/app/Services/{BranchModules,SchoolRoles}.php` and `backend/app/Models/User.php`
- Finance audit and period event definitions in the R6-G, R6-H, R6-J and R6-K migrations
- Finance pilot route group in `backend/routes/api.php`
- `backend/tests/Feature/{PostedFamilyReceiptApi,BranchAccountingPeriodApi}Test.php`
- `.github/pull_request_template.md`

## Touch (proposed for approval)

- `backend/routes/api.php`
- `backend/app/Http/Controllers/Api/BranchFinanceAuditController.php` (new)
- `backend/app/Services/SchoolRoles.php` (exclude the new grant from default roles)
- One new timestamped migration ending in `_register_finance_audit_view_permission.php`
- `backend/tests/Feature/BranchFinanceAuditApiTest.php` (new)
- `docs/PROGRESS.md` and `docs/DECISIONS.md` for approved decisions

## Behaviour and verification

- Require an active account, access to the requested branch, an explicit `fees.audit.view` grant, and Fees read access. Apply the same testing-only configured PKR pilot gate as the receipt and period APIs. Grant no default permissions.
- Provide paginated, deterministic results for receipt posting and full reversal events and for period opening and closure. Support event-type and date filters. Define the filter date as the recorded UTC date and preserve the accounting date separately when present. Pre-integration receipt events without a verified UTC timestamp appear in undated results and are labelled with their legacy timestamp basis.
- Return only allowlisted audit fields, including actor ID, branch, event type, timestamp, record IDs and applicable amounts, charge balances, period or reversal context, and controlled reason. Exclude names, contact information, raw source payloads and attachments. Prevent shared caching.
- Verify unauthenticated, ungranted, out-of-branch and disabled-module requests are denied. Verify that general finance management, receipt posting and administrative audit access do not substitute for the explicit grant. Verify filtering, ordering, pagination, and no finance writes.

No finance UI, exports, production activation, or reporting cutover. Frontend build: no.
