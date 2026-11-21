# R3 · Report centre v1 (non-finance, paid module `reports`)

Effort: **high** · Depends on: R1 · Frontend build: **yes** · Decision needed: none (new module `reports`; not part of the starter plan)

## Goal
Add a report centre with filters and exports for non-finance reports, gated by a new `reports` module.

## Read ONLY these
- `AGENTS.md`
- `docs/specs/spec-dashboards-reports.md` (Report centre v1)
- `backend/database/migrations/2026_11_19_065757_create_branch_module_catalogue.php` lines 28 to 75 (catalogue style)
- `backend/app/Services/BranchModules.php`: signatures only
- `grep -rn 'reports.view' backend/app backend/database` (where the permission lives)
- `grep -rn -i 'csv' backend/resources/js backend/app | head -20` (existing export approach)

## Touch ONLY these
- `backend/database/migrations/` one new file adding the `reports` module to the catalogue
- `backend/app/Services/Reports/` new: `ReportFilters.php`, `AttendanceReports.php`, `StudentReports.php`, `StaffReports.php`
- `backend/app/Http/Controllers/AdminReportController.php` (new)
- `backend/routes/web.php`: report routes behind `RequireBranchModule` for `reports`
- `backend/resources/js/pages/Admin/Reports.jsx` (new) and `components/reports/*`
- Where `reports.view` is granted to roles: extend minimally and add a test
- `backend/resources/css/admin-dashboard.css`: append only
- `backend/tests/Feature/ReportCentreTest.php`, `ReportExportTest.php` (new)

## Out of scope (do not do)
- Finance reports of any kind
- Scheduled email reports, PDF engine (print stylesheet only)
- Adding `reports` to any plan: it is a paid module
- New packages

## Tests first (write, run red, then build)
1. Module `reports` appears in the catalogue; a branch with it off gets 403 on every report route and export.
2. Roles: platform admin, owner, group_admin, group_viewer and branch admin may open the centre; teacher and student may not; accountant may open it but no finance report exists yet.
3. Each report also needs its source module (attendance, students, staff, inquiries) and `*.view` permission.
4. Attendance by day and class, monthly summary per class, and low-attendance students (default threshold 75 percent) return correct rows on synthetic fixtures.
5. Student strength by grade and section, new and withdrawn students in a date range, admissions funnel, staff directory and leave balances return correct rows.
6. Filters (branch, session, date range, class) change results correctly; invalid filters give a validation error.
7. Organisation roles can pick 'all my branches' and get a comparison table limited to branches where `reports` and the source module are on; a branch admin cannot see other branches.
8. CSV export contains the same rows as the screen; print view renders without the sidebar.
9. Direct requests with the wrong role, another branch, or module off are refused.

## Build
- Use the existing export approach; add no package.
- Page follows existing admin styles; one report at a time in a tab list, filters above the table.
- State in the PR how organisations without a subscription behave today (modules default to enabled until plan-based entitlement exists).

## Acceptance
- [ ] New and existing tests green; build green.
- [ ] Existing 119 tests still green; no file outside the Touch list changed (or the reason is stated in the PR).

## Finish
Targeted tests green, then the whole suite once, then push branch `r3/report-centre-v1`, open the PR (do not merge), update `docs/PROGRESS.md`, and give the 12-line final answer from `AGENTS.md`.
