# T08 · Module enforcement on existing routes

Effort: **medium** · Depends on: T07 · Frontend build: **no** · Decision needed: none

## Goal
Protect the existing management routes with a `module:<slug>` middleware, and keep the existing tests green through a test helper instead of rewriting them.

## Read ONLY these
- `AGENTS.md`
- `docs/specs/spec-entitlements.md` (rules only)
- `backend/app/Services/ModuleAccess.php`
- `backend/app/Http/Middleware/RequireSchoolPermission.php`
- `backend/routes/api.php`: route groups only (`grep -n 'attendance\|fee\|expense\|staff\|student\|grade\|section\|subject\|session' backend/routes/api.php`)
- One existing feature test's `setUp` (for example `AttendanceManagementTest.php`, first 40 lines)

## Touch ONLY these
- `backend/app/Http/Middleware/RequireModule.php` (new); alias in `backend/bootstrap/app.php`
- `backend/routes/api.php` (add `module:` to existing groups)
- `backend/tests/Concerns/GrantsModules.php` (new helper)
- Existing feature tests: `setUp` lines only, to grant modules
- `backend/tests/Feature/ModuleEnforcementTest.php` (new)

## Out of scope (do not do)
- Changing any controller logic
- UI

## Tests first (write, run red, then build)
1. A request to a module route with the module off returns 403 with a stable code `module_unavailable`.
2. In read-only mode GET works and POST, PUT, PATCH, DELETE return 403 with `module_read_only`.
3. With the module on, the request works.
4. The restriction holds for direct API calls (no UI involved).
5. Another branch's switch does not leak into this branch.
6. The public directory and the free listing are unaffected.

## Build
- Mapping (default): students → students, enrollments, guardians, grades, sections, sessions, subjects, inquiries; attendance → attendance; fees → fee types, groups, masters, discounts, invoices, payments, charges; expenses → expense routes; staff → staff routes.
- `GrantsModules::grantModules($school, [...])` creates the entitlement and switches for tests; use it in existing tests' `setUp` and change nothing else in them.

## Acceptance
- [ ] All 74 original tests and the new tests green.
- [ ] Existing tests still green; no file outside the Touch list changed (or the reason is stated in the PR).

## Finish
Targeted tests green, then the whole suite once, then push branch `t08/module-enforcement`, open the PR, update `docs/PROGRESS.md`, and give the 12-line final answer from `AGENTS.md`.
