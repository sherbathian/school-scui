# R2 · More role widgets (non-finance)

Effort: **medium** · Depends on: R1 · Frontend build: **yes** · Decision needed: none

## Goal
Add the next set of non-finance widgets for every role, using the R1 framework.

## Read ONLY these
- `AGENTS.md`
- `docs/specs/spec-dashboards-reports.md` (Role layouts)
- One finished widget class from R1 and `config/dashboard.php`
- `grep -n -i 'status' backend/database/migrations/*inquir*` (admissions statuses)
- `grep -rn 'grace_ends_at\|ends_at' backend/database/migrations | head` (subscription fields)

## Touch ONLY these
- `backend/app/Services/Dashboard/Widgets/*.php` (new classes)
- `backend/config/dashboard.php`
- `backend/resources/js/components/dashboard/`: add `TrendWidget` only
- `backend/tests/Feature/DashboardWidgetTest.php` and `DashboardWidgetRolesTest.php`

## Out of scope (do not do)
- Finance widgets of any kind
- Organisation owner widgets
- Exports and reports (R3)

## Tests first (write, run red, then build)
1. Branch admin: attendance trend for 14 days; admissions funnel by inquiry status; new students this month; classes with no attendance yet today.
2. Teacher: classes with attendance still to mark today (use a teacher-to-class link only if one exists; otherwise branch-wide, and say which in the PR).
3. Student: attendance trend for 30 days and recent absences, own record only.
4. Platform admin: verification queue age, listings by city, new demo requests (only if that table exists; otherwise skip and say so), subscriptions in grace or ending within 14 days, module adoption (count of branches with each module on).
5. Every widget: module off gives 403 and no layout entry; wrong role gives 403; another branch gives 403; numbers correct on synthetic fixtures; student never sees another student's data.

## Build
- Reuse the R1 contract and components; at most 3 queries per widget.
- If a widget needs data that does not exist, skip it and list it in the PR instead of inventing tables.

## Acceptance
- [ ] New and existing tests green; build green.
- [ ] Existing 119 tests still green; no file outside the Touch list changed (or the reason is stated in the PR).

## Finish
Targeted tests green, then the whole suite once, then push branch `r2/non-finance-widgets`, open the PR (do not merge), update `docs/PROGRESS.md`, and give the 12-line final answer from `AGENTS.md`.
