# R6-M · Authenticated accounting-period administration

## Outcome

Expose branch accounting-period settings, period listing, and audited open and close operations through authenticated APIs for a synthetic pilot. Reuse the existing branch calendar service and its locking rules.

## Read

- `docs/R6-I-accounting-period-authority.md`
- `backend/app/Services/{BranchAccountingPeriods,BranchModules,SchoolRoles}.php`
- `backend/app/Models/User.php`
- `backend/app/Http/Controllers/Api/PostedFamilyReceiptController.php`
- `backend/app/Http/Middleware/{ResolveSchoolPermissionTeam,RestrictPersonalAccessToken}.php`
- `backend/routes/api.php`, finance and authentication groups only
- `backend/tests/Feature/{BranchAccountingPeriods,PostedFamilyReceiptApi}Test.php`
- The branch accounting period and receipt permission migrations
- `.github/pull_request_template.md`

## Touch (proposed for approval)

- `backend/routes/api.php`
- `backend/app/Http/Controllers/Api/BranchAccountingPeriodController.php` (new)
- `backend/app/Services/SchoolRoles.php` (exclude the new permission from default roles)
- One new timestamped migration ending in `_register_accounting_period_management_permission.php`
- `backend/tests/Feature/BranchAccountingPeriodApiTest.php` (new)
- `docs/PROGRESS.md` and `docs/DECISIONS.md` for approved decisions

## Behaviour and verification

- Require active branch access and an explicit `fees.period.manage` grant to view or manage the branch calendar. Enforce the Fees module gate for reads and the write gate for timezone, open and close requests. Grant no permissions automatically.
- Keep endpoints restricted to the configured synthetic branch in the testing environment. Production activation and grants require separate approval.
- Read branch timezone and period state from the server. Accept an IANA timezone for configuration, a YYYY-MM month for opening, and a branch-scoped period ID for closure. Derive actor, effective business date and audit timestamps on the server.
- Verify guest, ungranted, cross-branch and disabled module requests are denied. Verify timezone locking, future periods, early closure rejection, repeated open and close requests, and one audit event per successful transition. Assert denied requests leave state and audit records unchanged.
- Keep finance audit access, UI, production activation and report cutover for separate tasks.

Frontend build: no
