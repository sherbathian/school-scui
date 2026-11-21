# T09 · Platform-admin module switches (API and admin page)

Effort: **medium** · Depends on: T07, T02 · Frontend build: **yes** · Decision needed: none

## Goal
Let a platform admin see each branch's modules (entitled? switched?) and switch them on or off. Nobody else may.

## Read ONLY these
- `AGENTS.md`
- `docs/specs/spec-entitlements.md`
- `docs/specs/admin-ui.md`
- `backend/app/Services/ModuleAccess.php`
- `backend/routes/web.php` (admin block only)

## Touch ONLY these
- `backend/app/Http/Controllers/Admin/BranchModuleController.php` (new)
- `backend/app/Http/Requests/Admin/BranchModuleRequest.php` (new)
- `backend/routes/web.php` (admin routes)
- `backend/resources/js/pages/Branches/Modules.jsx` (new); add a sidebar entry
- `backend/tests/Feature/BranchModuleSwitchTest.php` (new)

## Out of scope (do not do)
- Plan management screens
- Billing

## Tests first (write, run red, then build)
1. A platform admin can switch a module on and off for a branch.
2. An organisation owner, a group manager and a branch admin all receive 403 on direct requests.
3. Switching off keeps all data (row counts unchanged).
4. Switching on a module whose dependency is off returns a clear validation message.
5. Switching on a module the organisation is not entitled to is refused with a clear message.
6. The page lists each module with: entitled (plan, add-on, trial), switched, effective mode.

## Build
- Page follows the admin pattern; use toasts and a confirmation dialog for switching off.

## Acceptance
- [ ] New and existing tests green; build green.
- [ ] Existing tests still green; no file outside the Touch list changed (or the reason is stated in the PR).

## Finish
Targeted tests green, then the whole suite once, then push branch `t09/module-switches`, open the PR, update `docs/PROGRESS.md`, and give the 12-line final answer from `AGENTS.md`.
