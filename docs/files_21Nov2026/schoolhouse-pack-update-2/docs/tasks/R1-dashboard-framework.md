# R1 · Dashboard widget framework and first role widgets

Effort: **high** · Depends on: R0 · Frontend build: **yes** · Decision needed: none (package pre-approved: recharts, MIT)

## Goal
Add a server-driven widget framework beside the existing dashboard, and prove it with five widgets. The existing dashboard and its tests do not change.

## Read ONLY these
- `AGENTS.md`
- `docs/specs/spec-dashboards-reports.md` (all of it)
- `backend/app/Http/Controllers/AdminDashboardController.php` (all, about 130 lines)
- `backend/resources/js/pages/Admin/Dashboard.jsx`
- `backend/tests/Feature/AdminDashboardTest.php`
- `backend/app/Services/BranchModules.php`: method signatures only (`grep -n 'function'`)
- `backend/routes/web.php`: the admin dashboard route (`grep -n dashboard`)

## Touch ONLY these
- `backend/app/Services/Dashboard/` new: `Widget.php` (interface), `WidgetRegistry.php`, `DashboardContext.php`, `Widgets/` five classes
- `backend/config/dashboard.php` (new, role layouts)
- `backend/app/Http/Controllers/AdminDashboardWidgetController.php` (new)
- `backend/app/Http/Controllers/AdminDashboardController.php`: add one `widgets` prop; keep `cards` exactly as it is
- `backend/routes/web.php`: one new route `admin.dashboard.widget`
- `backend/resources/js/components/dashboard/*.jsx` (new)
- `backend/resources/js/pages/Admin/Dashboard.jsx`: render the widget grid under the existing cards
- `backend/resources/css/admin-dashboard.css`: append only
- `backend/package.json` and lockfile: add `recharts`
- `backend/tests/Feature/DashboardWidgetTest.php` (new)

## Out of scope (do not do)
- Finance widgets of any kind (frozen)
- Moving existing cards into widgets
- Organisation overview widgets, exports, reports (later tasks)
- Caching, summary tables, user-arranged layouts
- Installing any UI library other than `recharts`

## Tests first (write, run red, then build)
1. The layout for a platform admin contains `platform.listings_by_status` and `platform.claims_queue`, and no branch widgets.
2. The layout for a branch admin contains `branch.attendance_today` and `branch.class_strength`, and no platform widgets.
3. The layout for a student contains `student.attendance_month` and nothing about other students.
4. A widget whose module is off for the branch is absent from the layout and its endpoint returns 403 (module `attendance` for attendance widgets, `students` for class strength).
5. A widget the role may not see returns 403, and an unknown key returns the same 403.
6. A branch the user may not access returns 403 or 422 (same rule as the existing dashboard), and another branch's numbers never appear.
7. Each widget returns the contract in the spec (key, title, type, asOf, summary, data) with correct numbers on small synthetic fixtures (attendance today present, absent, late; class strength per grade; listings per status; pending claims with age in days; student's own attendance percent for the month).
8. Guests are redirected; users who may not enter Schoolhouse are refused (same as the existing dashboard).
9. The existing three `AdminDashboardTest` tests pass unchanged.

## Build
- Follow the contract and role layout table in the spec; layouts live in `config/dashboard.php` as a map from role to ordered widget keys.
- Widgets use existing tables only; at most 3 queries each; no N+1.
- Frontend: `WidgetGrid`, `WidgetCard` (title, as-of, skeleton, empty, error), and renderers `KpiWidget`, `BarWidget`, `DonutWidget`, `ListWidget` (recharts for bar and donut). Each widget fetches its endpoint on mount.
- Add `data-test` attributes: `widget-card` and `widget-<key>`.
- Accessibility: render the `summary` text for every chart as visible or screen-reader text.
- Use the existing admin CSS conventions; no shadcn, radix, lucide or TanStack.

## Acceptance
- [ ] New and existing tests green; `npm run build` green.
- [ ] Existing 119 tests still green; no file outside the Touch list changed (or the reason is stated in the PR).
- [ ] PR notes list each widget and its query count.

## Notes
Existing finance cards in `cards` stay untouched. If the task grows beyond about 500 lines, stop after the framework plus the platform and branch widgets, and report; student widget becomes part of R2.

## Finish
Targeted tests green, then the whole suite once, then push branch `r1/dashboard-framework`, open the PR (do not merge), update `docs/PROGRESS.md`, and give the 12-line final answer from `AGENTS.md`.
