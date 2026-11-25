# Schoolhouse decisions

| Date recorded | Decision | Options considered | Decided by |
| --- | --- | --- | --- |
| 19 Nov 2026 | The workbook supplied under `docs/updated` is the canonical Database Plan v4. The repository copy is `docs/Schoolhouse-Database-Plan-v4.xlsx`. | Earlier local v4 copy or updated v4 copy. | Tahir, confirmed during repository sync |
| 19 Nov 2026 | Each listing and managed branch share one `schools` row. | Shared row or separate directory and management rows. | Tahir |
| 19 Nov 2026 | MySQL is the database target for development and integration testing. Confirm the production engine before staging. | MySQL or PostgreSQL for implementation. | Tahir |
| 19 Nov 2026 | Each verified institute gets an organisation record. | Every verified institute or only multi-branch institutes. | Tahir |
| 19 Nov 2026 | Super Admin approves claims. An approved claimant gets organisation owner access across its branches. | Organisation owner access or access to the claimed branch only. | Tahir |
| 19 Nov 2026 | Only platform admins change branch module switches. | Platform admins only, or platform admins and authorised organisation owners. | Tahir |
| 19 Nov 2026 | Admin workspaces share Inertia, React 19, Tailwind CSS, and shadcn/ui. | Shared role-based admin or separate frontend stacks. | Tahir |
| 20 Nov 2026 | Finance widgets and finance reports wait until billing consolidation is done. | Build now and port later, or wait. | Tahir |
| 20 Nov 2026 | The basic dashboard is part of every plan. Reports, including the report centre, exports, and multi-branch overview, is a paid module with slug `reports`. | Reports free for all, or a paid module. | Tahir |
| 20 Nov 2026 | Letting users rearrange or hide dashboard widgets is deferred. | Now or later. | Tahir |
| 23 Nov 2026 | Deliver the dashboard framework and platform and branch widgets in R1-A. Deliver student attendance in R1-B after R1-A merges. Monthly student attendance shows the percentage and attended days out of distinct recorded days. Present and late count as attended. | One PR or separate framework and student PRs | Tahir |

Open decisions include how to count excused days in student attendance, parent phone sign-up, media storage, the public fee source, free-plan modules and quotas, grace period, pricing, the marketing hostname, and the production database engine. R1-B omits excused-only dates pending a decision. Confirm only the decisions needed for the next increment.

Tahir approved counting active students across non-archived branches under the organisation-wide `max_students` cap. A subscription override takes precedence over the plan cap. An explicit null is unlimited. Commercial values are decided separately.

Tahir approved including the read-only Audit Log page in R5. Platform administrators may view all branches. Other users need an explicit branch grant of audit.view. Financial events are reserved for the billing consolidation brief.

The Audit Log sidebar and page use the same explicit branch permission check. Sidebar visibility is supplied by the server for admin pages.
