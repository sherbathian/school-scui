# T14 · Plan limits and enforcement

Effort: **medium** · Depends on: T08 · Frontend build: **no** · Decision needed: none

## Goal
Count usage across all branches of an organisation and enforce plan limits where records are created.

## Read ONLY these
- `AGENTS.md`
- `docs/specs/spec-entitlements.md` (limits rules)
- `backend/app/Http/Controllers/Api/StudentController.php`, `StaffController.php` (store methods only)
- `backend/app/Http/Controllers/SchoolController.php` (store only)

## Touch ONLY these
- `backend/app/Services/PlanLimits.php` (new)
- The three store methods above (add the check only)
- `backend/tests/Feature/PlanLimitTest.php` (new)

## Out of scope (do not do)
- Billing, UI
- Changing what counts as a student (active only)

## Tests first (write, run red, then build)
1. Effective limit = subscription limit if set, otherwise the plan limit; an empty value is unlimited.
2. Boundary: 79 allowed, 80 allowed to reach, 81 refused with the stable code `limit_reached` and upgrade information.
3. Only active students and staff count; archiving frees a place.
4. Counts include all branches of the organisation (DC24, DC25).
5. Viewing and exporting keep working at the limit.
6. The branch count limit is enforced when a branch is added.

## Build
- Check inside the same transaction as the insert to avoid two simultaneous creates passing the check.

## Acceptance
- [ ] New and existing tests green.
- [ ] Existing tests still green; no file outside the Touch list changed (or the reason is stated in the PR).

## Finish
Targeted tests green, then the whole suite once, then push branch `t14/plan-limits`, open the PR, update `docs/PROGRESS.md`, and give the 12-line final answer from `AGENTS.md`.
