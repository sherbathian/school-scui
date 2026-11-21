# T15 · Audit trail for sensitive actions

Effort: **medium** · Depends on: T09, T13 · Frontend build: **no** · Decision needed: none (package pre-approved: spatie/laravel-activitylog; check the locked Laravel version first)

## Goal
Record who did what and when for verification, publication, suspension, claim decisions and module switches.

## Read ONLY these
- `AGENTS.md`
- `backend/composer.json` and the locked Laravel version only (`composer show laravel/framework | head -5`)
- `backend/app/Actions/Listings/*.php`, `Claims/*.php`
- `backend/app/Http/Controllers/Admin/BranchModuleController.php`

## Touch ONLY these
- `backend/composer.json`, `composer.lock`
- `backend/config/activitylog.php`, its migration (published)
- The action classes above (add logging)
- `backend/tests/Feature/AuditTrailTest.php` (new)

## Out of scope (do not do)
- An audit screen
- Logging every model change

## Tests first (write, run red, then build)
1. Verify, publish and suspend each write one log row with the causer, subject and the old and new status.
2. Approve and reject claim each write one row.
3. Switching a module writes one row with old and new value.
4. Passwords, one-time codes and tokens never appear in any log row.
5. Log rows survive deletion of the logged record.

## Build
- If the locked Laravel version is lower than the package needs, STOP and report instead of upgrading.

## Acceptance
- [ ] New and existing tests green.
- [ ] Existing tests still green; no file outside the Touch list changed (or the reason is stated in the PR).

## Finish
Targeted tests green, then the whole suite once, then push branch `t15/audit-trail`, open the PR, update `docs/PROGRESS.md`, and give the 12-line final answer from `AGENTS.md`.
