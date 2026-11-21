# T07 · ModuleAccess service

Effort: **high** · Depends on: T06 · Frontend build: **no** · Decision needed: none

## Goal
Implement the single place that answers 'may this branch use this module right now, and in which mode?'. Pure server logic, no routes and no UI.

## Read ONLY these
- `AGENTS.md`
- `docs/specs/spec-entitlements.md` (rules + the numbered Access decision)
- The models created in T06

## Touch ONLY these
- `backend/app/Services/ModuleAccess.php` (new)
- `backend/app/Support/AccessDecision.php` (new: mode none/full/read_only plus a reason)
- `backend/tests/Feature/ModuleAccessTest.php` (new)

## Out of scope (do not do)
- Middleware, routes, UI (T08, T09)
- Limits (T14)

## Tests first (write, run red, then build)
1. Port Design Check DC29 to DC49 from the spec as separate test methods (one behaviour each). They cover: starter plan modules, unverified branch closed, verified opens, upgrade, owner switch off (platform admin switch), re-applying a plan does not override a switch, switching off keeps data, switch back restores, dependencies, add-on for one branch, add-on end date, trial, expiry with grace (read_only) then closed, institute-type restriction, listing core module free, downgrade keeps data.
2. The free listing (core module) stays `full` for a verified branch even when the subscription has expired.
3. No decision path deletes or changes any row (assert row counts before and after).

## Build
- Follow the numbered algorithm in the spec exactly.
- Accept an optional `at` time so tests can travel in time.
- Keep it to a small number of queries per decision and memoise per request (scoped binding), without caching across requests.

## Acceptance
- [ ] All ported tests green; existing tests green.
- [ ] Existing tests still green; no file outside the Touch list changed (or the reason is stated in the PR).

## Finish
Targeted tests green, then the whole suite once, then push branch `t07/module-access-service`, open the PR, update `docs/PROGRESS.md`, and give the 12-line final answer from `AGENTS.md`.
