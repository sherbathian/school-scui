# T06 · Plans, modules and entitlement tables

Effort: **medium** · Depends on: T05 · Frontend build: **no** · Decision needed: D3 starter plan (default is used if not answered)

## Goal
Create the tables, models, factories and seeders for modules, plans, add-ons, limits and branch switches. No access logic yet.

## Read ONLY these
- `AGENTS.md`
- `docs/specs/spec-entitlements.md` (tables and rules; skip the algorithm)
- `backend/database/seeders/DatabaseSeeder.php`
- `backend/app/Models/School.php` (relationships only)

## Touch ONLY these
- `backend/database/migrations/` new files for: modules, module_dependencies, plans, plan_modules, plan_limits, school_subscriptions, subscription_modules, subscription_limits, school_modules
- `backend/app/Models/` Module, Plan, SchoolSubscription, SubscriptionModule, SubscriptionLimit, SchoolModule (new)
- `backend/database/factories/` for the same
- `backend/database/seeders/ModuleCatalogueSeeder.php`, `PlanSeeder.php` (new)
- `backend/tests/Feature/EntitlementSchemaTest.php`, `EntitlementSeederTest.php` (new)

## Out of scope (do not do)
- Any access decision (T07)
- Routes, middleware or UI

## Tests first (write, run red, then build)
1. Module slugs are unique; one `school_modules` row per branch and module.
2. `plan_limits` is unique per plan and key; `subscription_limits` per subscription and key.
3. Exactly one default plan exists after seeding.
4. Running the seeders twice gives the same row counts (idempotent).
5. Seeded catalogue: directory_listing (core), students, staff, attendance, fees, expenses, exams, parent_portal, sms, institute_website, university_academics (applies to universities only).
6. Seeded dependencies: attendance, fees, exams and parent_portal need students.
7. Rows with a missing parent (module, plan, school) are refused by the database.
8. A module that is in use cannot be deleted.

## Build
- Starter plan seed (default D3): students, staff, attendance; limits 80 students, 15 staff, 1 branch. Values live only in the seeder.

## Acceptance
- [ ] Migrations up/down on SQLite; new tests green; existing tests green.
- [ ] Existing tests still green; no file outside the Touch list changed (or the reason is stated in the PR).

## Finish
Targeted tests green, then the whole suite once, then push branch `t06/entitlements-data-layer`, open the PR, update `docs/PROGRESS.md`, and give the 12-line final answer from `AGENTS.md`.
