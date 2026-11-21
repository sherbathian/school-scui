# Spec: role-based dashboards with widgets, and the report centre

## Decisions (Tahir, 20 Nov 2026)
- Finance widgets and finance reports **wait** for billing consolidation. Do not build them now.
- The **basic dashboard is part of every plan**. **Reports** (report centre, exports, multi-branch overview) is a **paid module** with slug `reports`.
- Letting users rearrange or hide widgets is **later**. Layouts are fixed per role for now.

## Principles
1. **Strangler, not rewrite.** The existing dashboard (`AdminDashboardController`, `Admin/Dashboard.jsx`, `cards`) keeps working and its 3 tests stay untouched. Add the widget framework beside it. Non-finance cards migrate into widgets later; finance cards stay as they are until billing consolidation.
2. **The server decides** which widgets a user gets: by role, by module (`BranchModules`), by permission, by branch scope. The browser only draws what it is given.
3. **Each widget loads by itself** (lazy), so a slow widget never blocks the page, and has loading, empty, error and "as of" states.
4. **Every widget endpoint re-checks** authentication, role permission, module and branch access. Another branch's data never appears.
5. Widgets are cheap: at most 3 queries each, no N+1, no caching yet (add summary tables only when measured slow).
6. Charts have a text alternative (`summary`) and colour is never the only signal.

## Widget contract
Layout descriptor (sent with the page): `{ key, title, type, size, endpoint }`
- `type`: `kpi` | `bar` | `donut` | `trend` | `list`
- `size`: `sm` | `md` | `lg`
Data response (`GET /admin/dashboard/widgets/{key}?branch={id}`):
`{ key, title, type, asOf, summary, data }` where `data` is: kpi `{value, unit?, delta?}`; bar/donut `{items:[{label,value}]}`; trend `{points:[{x,y}]}`; list `{rows:[{title, meta, href?}], empty: string}`.
Errors: 403 for a widget the user may not see (same code for unknown keys), 422 for a bad branch.

Server pieces: interface `Widget` (key, title, type, size, roles, requiredModule, requiredPermission, scope: platform|branch, data(DashboardContext)), a `WidgetRegistry`, role layouts in `config/dashboard.php`.

## Role layouts (fixed for now)
| Role | Widgets | Phase |
|---|---|---|
| Platform admin | listings by status, claims queue (with age) | R1 |
| Platform admin | verification queue age, listings by city, new demo requests, subscriptions in grace or ending within 14 days, module adoption | R2 |
| Branch admin | attendance today, class strength | R1 |
| Branch admin | attendance trend (14 days), admissions funnel, new students this month, classes with no attendance yet today | R2 |
| Teacher | classes with attendance still to mark today (branch-wide unless a teacher-to-class link exists; report which) | R2 |
| Student | my attendance this month (percent) | R1 |
| Student | my attendance trend (30 days), recent absences | R2 |
| Organisation owner and group roles | branch comparison, plan usage against limits | later (needs limits and billing) |
| Accountant and all money widgets | collection, outstanding by age, expenses, discounts | **after billing consolidation** |

## Report centre v1 (non-finance), module `reports`
Page `/admin/reports/{branch?}`. Reports: attendance by day and class; monthly attendance summary per class; low-attendance students (threshold, default 75 percent); student strength by grade and section; new and withdrawn students in a date range; admissions funnel; staff directory and leave balances. Filters: branch (or "all my branches" for organisation roles), session, date range, class. Output: on-screen table, CSV and print (reuse the existing export approach; no new package). Multi-branch comparison table for organisation roles. Each report also requires the module of its data (attendance, students, staff, inquiries) and the matching `*.view` permission, plus `reports.view`.
Out of scope: finance reports, scheduled emails, PDF engine.

## Tests (pattern)
Authorisation matrix per widget and per report: guest, wrong role, right role, other branch, other organisation, module off, direct request. Data correctness on small synthetic fixtures. Existing dashboard tests unchanged.
