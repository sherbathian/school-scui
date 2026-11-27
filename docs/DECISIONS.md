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

Tahir approved branch-local monthly accounting periods with at most one open period per branch. Closure is final and allowed on the last local day or later. A future open period becomes eligible on its first day. The IANA timezone is fixed once the first period opens. Asia/Karachi is used in synthetic IFS scenarios.

Tahir approved separate branch-scoped receipt posting and reversal permissions for a synthetic pilot. Both require an explicit grant and the Fees write gate. Production activation and branch currency authority remain separate.

Tahir approved an explicit branch-scoped fees.period.manage grant for calendar reads and writes in the synthetic accounting-period pilot. The Fees gate applies to reads and the Fees write gate applies to changes. Default branch roles receive no grant.

Tahir approved an explicit branch-scoped fees.audit.view grant for the synthetic finance audit pilot. The Fees read gate applies and no role receives a default grant.

Tahir approved a dedicated fees.reconciliation.view permission for the parallel synthetic finance report. Fees and paid Reports read eligibility apply. The report keeps legacy and v4 sources separate.

The synthetic finance reader defaults to legacy and uses v4 only when explicitly selected in trusted configuration. The parallel comparison stays available during reader rollback, and finance write services remain independent of the switch.

Tahir confirmed a fresh start for IFS with zero opening balances. Operational v4 finance requires explicit production configuration for one PKR branch. A separate write toggle pauses receipt and period changes while preserving authorised period, audit and report access.

Listing claim approval now requires verification of the claimant’s current account email. Pending claims created before rollout also require verification. A Super Admin still reviews institutional affiliation and authority.

Listing verification, publication and suspension, and claim approvals and declines use the existing branch-scoped Audit Log. Audit payloads contain status flags and identifiers. Competing declines reference the originating approved claim.

Public school websites use the application-domain path /school-sites/{school}/{page?}. The root uses the published home slug. Public rendering requires an eligible directory listing, the Website module, enabled site settings and an active theme. A subdomain is optional for same-domain publishing. Website availability has no effect on the free directory listing.

The public domain uses the directory login and dashboard. The management hostname uses the existing Schoolhouse authorization for its short login and dashboard paths. Legacy management links on the production hosts redirect to the management portal. Directory accounts view their own submissions and claims through the existing review workflow. Production links use HTTPS because Cloudflare terminates TLS before passing requests to the laptop origin.

Schoolhouse browser pages use prefix-free paths on admin.taleemfinder.pk. Both the production and development routes load the same definitions. Old production bookmarks redirect, including report links with filters. The API retains its existing paths.

The School pages form edits headings, paragraphs, and bullet lists with plain-text controls. The editor retains the existing content format and preserves unsupported legacy blocks. Publication and menu visibility remain separate.

Active organisation owners can create, edit, publish, and delete pages and create or edit School Setting for their own branches where Website is enabled. Grace-period writes are blocked. Owners select active themes. Shared themes, module switching, and settings record deletion stay with platform admins.

School Pages uses self-hosted Tiptap for rich text. The server validates structured content and renders supported formatting from escaped text. Existing block data is preserved. Draft preview shares the public rendering template and enforces school website access.

Configured school subdomains serve the published home at / and published pages at /{page}. Hostname routing uses the same public website eligibility checks as the existing school-site path. School menu links stay on the requested school hostname.
