# Schoolhouse progress and Claude handoff

**Snapshot: 20 November 2026.** This section reflects the local checkout. The historical milestone notes below record earlier checks and decisions at the time they were written.

## How to use this handoff

Schoolhouse combines the TaleemFinder public education directory with a role-based school management workspace. Laravel and MySQL back the application. The admin uses Inertia, React 19, Tailwind CSS, and shadcn/ui. The public directory uses Blade and shared CSS.

Tahir approved updating this Markdown handoff. Ask for his approval before changing application code, schema, project configuration, dependencies, other documents, or GitHub state. Treat the instructions and defaults in [the manager pack](files.zip) as proposed workflow and task briefs. Reconcile them with [recorded decisions](DECISIONS.md) and current code before issuing a build brief.

## Repository and verification status

- The previous published baseline is GitHub main commit `c6ebc42` (*Complete role dashboards, CSV workflows and public directory polish*). This increment contains the follow-up platform and Branch Operations fixes documented below.
- The [CI workflow](../.github/workflows/ci.yml) runs PHPUnit on SQLite and MySQL 8.4 and builds the frontend. This increment passed **119 tests and 1,396 assertions locally**, and the production frontend build succeeded. Hosted CI results for this increment still need confirmation.
- The student-account linkage migration was previously applied to local Herd MySQL. See [schema gaps](SCHEMA-GAP-v4.md) and [the MySQL audit](Schoolhouse-MySQL-Schema-Audit.md) for earlier database evidence. This increment adds no migration.
- The manager-pack ZIP and extracted reference folder remain local. Treat them as planning inputs if Tahir shares them separately.

## Latest increment · Platform administration and IFS Branch Operations

| Area | Shipped behaviour |
| --- | --- |
| Platform relationship lists | Module dependencies shows module and prerequisite names. Plan modules shows plan and module names. School subscriptions shows organisation, plan, and status. Subscription modules shows subscription, module, and grant source. Labels use the existing options API. |
| Branch Operations layout | Each selected operation has its own page heading. Tables use smaller text and tighter rows. Guardian IDs stay on one line. Shared CSV toolbars now identify the dataset, including the separate Students and Enrollments imports. |
| Student roster and enrollment | Fixed roster column alignment and added a Student list caption. Grade, Section, and the academic year have dedicated space in the inline form. Save requires both Grade and Section. After saving, the roster refreshes in place and retains search and pagination. |
| Expenses | Expense heads combines search, add, CSV and the table in one card. Add expenses opens a blank entry form directly and hides the Branch modules shortcut. Saving clears the form. Expenses list owns filtering, CSV, edit and delete. |

### Local IFS demo

- IFS is branch ID 1 in the local environment. The prior subscription was cancelled. A separate local trial enables all eight Branch Operations modules through 20 December 2026. The cancelled record and shared Silver plan were preserved. This is local database state and is not provisioned by the GitHub commit.
- The [IFS demo CSV pack](../outputs/ifs-branch-operations-demo.zip) contains 28 synthetic CSVs and an import-order guide. It uses academic year 2026–2027. The pack includes 12 students, eight guardian households and five staff records. Fee masters, staff leave balances and student fee discounts require IFS-specific numeric IDs.
- Local screenshots confirmed sample grades, guardians, students and expenses in IFS. The complete pack was not replayed end to end during this increment.

## What exists now

| Area | Implementation and boundaries |
| --- | --- |
| Public TaleemFinder | Homepage, category and locality directories, search and filters, institution profiles, shortlist and comparison, registration and sign-in, admissions enquiries, events, guides, books, courses and study resources. Public visibility requires an active, verified, published listing. The homepage uses published campus data and displays available school logos with initials as fallback. |
| Platform administration | Listings, Cities and Areas, directory taxonomies and assignments, media, programmes and courses, claims, user and permission management, subscription records, community records and school-site settings. Laravel infrastructure tables are application-managed. |
| Role-based Schoolhouse | Super Admin, Branch Admin, Teacher, Accountant, and Student use the shared admin sign-in. Branch roles operate within authorised branches. Dashboard summaries cover role-relevant activity and student summaries use an explicit same-branch admission-record link. Branch Roles shows assigned users' names, sign-in emails and access status within the permitted scope. |
| Branch operations | Academics, students, guardians, admissions, attendance, staff, fees and expenses use the existing branch endpoints. Student transfers and student fee discounts have platform workflows within Branch Operations. A populated local demo campus exists for validation. |
| CSV tools | Existing branch tools were reused and extended to standalone enrollments, listings, locations, catalogues, courses, programmes and student discounts. Tools provide templates, preview and row-level import errors, with CSV, Excel, JSON and print/PDF export. Attendance imports use one loaded class/year/date. Enrollment imports use the current year. Historical finance imports use configured years, existing student enrollments, and duplicate receipt-reference checks. |
| Organisation and claims | One `schools` record represents a branch and its public listing. Verification provisions an organisation. Super Admin claim approval is transactional and grants organisation-owner access across its branches. Current claim verification is manual. |
| Entitlements | Plans, grants, dependencies, branch overrides, and subscription grace behaviour are implemented through `BranchModules` and middleware. Platform admins control branch switches. The free listing is independent of management switches. Plan and subscription limit records exist. |
| Websites and billing | Admin records exist for site pages, themes, settings, subscription invoices and payments. A local IFS site preview exists. Live website rollout and subscription purchase/collection flows need separate confirmation. School fees still use legacy billing components alongside v4 fee setup. |

## Manager pack task reconciliation

| Task | Working-tree status | Remaining acceptance work |
| --- | --- | --- |
| T00 Baseline | CI configuration and historical test evidence exist | Agree which local changes form the baseline, then verify current SQLite, MySQL and frontend results on that exact revision. |
| T01 Schema-gap command | Open | Reuse the existing audit and the pack's schema JSON. Confirm that the JSON matches the canonical workbook and intended schema exceptions before automating the comparison. |
| T02 Admin shell and sign-in | Implemented in substance | Check pack-specific UI acceptance items individually, including theme switching, field feedback and shell details. Preserve existing role entry points. |
| T03 Listings pages | Implemented in substance | Index, create, edit and details screens exist. Verify against the pack's UI contract before proposing structural changes. |
| T04 Cities and Areas | Implemented in substance | Separate CRUD screens and CSV tools exist. Check remaining UI acceptance details. |
| T05 Organisations and branches | Implemented in substance | Verify owner access across new branches and the remaining database-level relationship guarantees. |
| T06 Entitlement data | Implemented in substance | Tables and admin workflows exist. Confirm starter bundle and limits before treating pack defaults as product policy. |
| T07 Module access | Implemented in substance | Extend or verify `BranchModules` against pack edge cases using focused contract tests. |
| T08 Route enforcement | Implemented in substance | Audit route coverage, direct API access, disabled modules, and grace-period writes. |
| T09 Module switches | Implemented in substance | Verify platform-only access, resets and dependency behaviour. |
| T10 Claims data | Partially complete | Claim and OTP tables exist. Confirm outstanding state transitions and expiry rules. |
| T11 Public accounts | Implemented in substance | Email/password registration and sign-in exist. Phone-only registration remains a product decision. |
| T12 Claim submission and OTP | Partially complete | Claim submission exists. End-to-end OTP delivery and confirmation, rate limits, expiry, and the pending-claim rule require review. |
| T13 Claim review and approval | Implemented in substance | Review queue and transactional approval exist. Verify concurrency and align any new OTP prerequisite with the approved claim policy. |
| T14 Organisation plan limits | Open for enforcement | Limit records exist. Verify organisation-wide usage counting and enforcement at creation and import entry points. |
| T15 Sensitive action audit | Open for dedicated coverage | Review existing claim history and establish what still needs actor/time/action logging for listings, claims and module switches. |

## Recommended next brief

1. **Reconcile the pack with this checkout.** Keep confirmed decisions, mark task status from evidence, and agree which uncommitted changes belong in the next baseline. Review any pack installation, file replacement, GitHub settings or branch action separately with Tahir.
2. **Propose T01 as the first implementation task.** Keep the scope to comparing the migrated schema with the agreed v4 target, reporting discrepancies reproducibly, and verifying that the command leaves data untouched. Reuse the prior audit instead of repeating a manual inventory.
3. **Prioritise verified gaps after T01.** Agree the next single brief for claim verification, organisation limits, or sensitive-action audit. Update the pack based on what the current code already provides.
4. **Keep billing consolidation separate.** Approve the money representation, migration and reconciliation plan before replacing cents-based invoices or retiring legacy fee items, family receipts, and snapshots. Existing CSV and historical finance workflows need compatibility checks.

Preserve the recorded decisions on branch identity, MySQL, organisation provisioning for verified institutes, Super Admin claim approval, organisation-owner access, and platform-only module switching. Confirm starter-plan limits, OTP channel, grace-period policy, pricing, production deployment details, and billing retirement only when the corresponding brief needs them.

## Reporting and dashboards

| Task | Status | Evidence and remaining work |
| --- | --- | --- |
| R0 · Install the manager pack | done | Reporting spec and briefs added. Product decisions recorded. |
| R1 · Dashboard framework | done | The framework, four platform and branch widgets, and the student's monthly attendance are on main. |
| R2 · Non-finance widgets | done | All role widgets are on main, including effective module adoption. |
| R3 · Report centre v1 | done | All planned reports and the browser page registration fix are on main. |
| R4 · Organisation student limits | merged | Enforces configured active-student caps on creation, activation and rejoin across an organisation. Commercial plan values remain unset. |
| R5 · Administrative audit log | merged | Records membership, role and branch module changes. Adds a read-only page and explicit branch permission. |
| R5 follow-up · Audit Log navigation | merged | Shows the Audit Log sidebar link for users who can open the page. |
| R6 · Billing consolidation assessment | merged | Maps finance write paths and defines reconciliation, audit events and manual acceptance scenarios. |
| R6 follow-up · Billing source inventory | merged | Verifies finance writers, report sources and schema constraints, and proposes a read-only reconciliation preview. |
| R6-A · Billing reconciliation preview | merged | Read-only branch and cutoff preview with separate source totals and discrepancy counts. |
| R6-B · MySQL reconciliation verification | merged | Independent SQL matched all preview totals and exceptions on synthetic data across two branches and cutoff dates. |
| R6-C · Historical billing reconciliation | verified locally | Independent SQL matched all six preview sources on the approved sanitised IFS copy. |
| R6-D · Billing obligation mapping preview | merged | Optional operator-supplied evidence assesses invoice-to-charge matches and possible receipt allocations without posting. |
| R6-E · Cumulative receipt allocations | in review | Flags all receipts contributing to a charge over-allocation in the read-only preview. |

---

## Historical progress notes

Updated 19 November 2026. The latest source documents are the Database Plan v4 workbook, Planning Document, and Package Selection Guide in this directory. The prompt pack describes the development workflow. This file records the current implementation where the older handoff and prompt describe an earlier snapshot.

| Milestone | Status | Evidence and remaining work |
| --- | --- | --- |
| 0 · Database and test baseline | In progress | GitHub Actions workflow at [`c152ef1`](https://github.com/sherbathian/school-scui/commit/c152ef1). [MySQL audit](Schoolhouse-MySQL-Schema-Audit.md) and the earlier-schema upgrade rehearsal are complete against the prior v4 workbook. See the [v4 schema gap report](SCHEMA-GAP-v4.md). The first branch-integrity increment hardens lifecycle-event and expense references. Disposable MySQL verified preflight, upgrade, rollback and reapply. The next increment is same-branch admissions links and their nullable deletion behaviour. Hosted CI still needs confirmation. |
| 1 · Foundation and directory | In progress | Public directory and admin listing/location workflows are on [`main`](https://github.com/sherbathian/school-scui/commit/1f8d2d1). Staging acceptance and backup/restore rehearsal remain. |
| 2 · Registration, claims, and entitlement | In progress | Institute registration, new listing intake, manual claim review, organisation ownership, and platform-admin branch switches work locally. OTP, plans, add-ons, trials, quotas, and expiry remain. Current management modules default to enabled until plan-based entitlement is implemented. |
| 3 · School workflows and pilot | Not started | The imported backend provides a starting point. The branch dashboard, v4 fee transition, and pilot acceptance remain. |
| 4 · Results, timetable, notices, and portal | Not started | Confirm grading, release, and family visibility rules when this milestone starts. |
| 5 · Websites, marketing, and billing | Not started | Confirm the marketing hostname and billing decisions before implementation. |
| 6 · Shared mobile access | Not started | Confirm the PWA and native rollout. |
| 7 · Additional institute types and community | Not started | Confirm programme sequencing and moderation rules. |

## Local Milestone 2 entry points

- Public institute registration and submissions at `/institute/register` and `/institute/new`.
- Claim an existing active, verified, published listing from its profile.
- Super Admin reviews requests at `/admin/claims`.
- A branch's **Modules** action opens its switches. Fees depend on Students. The free listing remains independent of management switches.

Before starting another increment, read [DECISIONS.md](DECISIONS.md) and check the confirmed and open items in the current workbook.

## Database Plan v4 · School access

Implemented the added columns for the three **School access** rows in the Table Plan. Branch codes are unique within an organisation. Memberships can record a primary contact through the authorised school-user API. Account phone and verification metadata are private, and successful sign-in updates the last-login timestamp. The admin listing form exposes the new branch and profile fields. The media, fee, rating, and featured fields await their respective workflows.

The full suite passed 84 tests on SQLite and MySQL. The disposable MySQL upgrade, rollback, and reapply preserved the existing branch, account, and membership records. The migration was applied to local Herd MySQL and the health endpoint passed.

## Platform admin · School access and permissions

Added sidebar pages for platform user accounts, branch memberships, branch roles, and the permissions catalogue. Platform admins can create and edit accounts, deactivate accounts, and delete eligible accounts. Branch membership screens manage role assignments, primary contacts, and direct permissions. Branch roles can be created, edited, and deleted subject to built-in role and assignment protections. The listings table links to branch access screens and supports archiving a branch.

The permissions catalogue shows application-defined abilities. Changes to access go through branch roles and memberships. Laravel's cache, locks, queue storage, migration history, reset tokens, and sessions require no admin CRUD for these workflows.

## Platform admin · Branch operations and requested v4 tables

Open **Branch operations** in the platform admin sidebar or use **Operations** on a branch in Directory listings. Select the branch and a section. The workspace uses the existing branch endpoints and respects module switches.

| Tables | Admin workflow | v4 status |
| --- | --- | --- |
| `school_user`, `schools`, `users` | Memberships, listing and branch details, and platform accounts. Removal uses archive or deactivation where the workflow requires it. | v4 column names are present. The directory catalogue migration aligned the school area and organisation delete rules with v4. |
| `grades`, `sections`, `subjects`, `school_sessions` | Academic setup lists and maintains grades, sections, subjects and academic years. | v4 column names and relationship endpoints are present. |
| `grade_section`, `grade_subject` | Assign or unassign sections and subjects through academic setup. | v4 column names and relationship endpoints are present. |
| `students`, `guardians`, `enrollments`, `student_lifecycle_events` | Student and guardian records, enrollment, withdrawal, rejoin and history. | v4 column names and relationship endpoints are present. |
| `attendance_records` | Daily register, recording and correction by date and class. | v4 column names and relationship endpoints are present. |
| `student_inquiries` | List, create, edit, delete and start admission. | Missing `listing_lead_id` and its parent relationship. Grade and student are validated per branch by the API, while their database keys still reference IDs individually. |
| `departments`, `designations`, `leave_types`, `staff`, `staff_leave_balances` | Staff directory, lookup forms and per-staff leave balances. | v4 column names and relationship endpoints are present. Direct database writes can still bypass branch agreement on leave balances. |
| `fee_types`, `fee_discounts`, `fee_groups`, `fee_group_grade`, `fee_masters` | Maintain fee setup, linked grades and fee master pricing. | v4 column names and relationship endpoints are present. The grade assignment pivot now requires the group and grade to belong to its branch. |
| `student_fee_charges` | Assign and list student charges through fee masters. | Master, session and student references require matching branches. The v4 invoice link and discount amount remain pending. |
| `fee_invoices`, `fee_payments` | Legacy invoice issue, list and eligible void, plus payment posting and history. | The v4 monthly invoice, decimal totals, payment method and session relationship remain pending. |
| `expense_heads`, `expenses` | Category and expense management. | v4 column names and relationship endpoints are present. |
| `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | Branch roles, membership assignments, direct permissions and a read-only capability catalogue. | v4 column names and relationship endpoints are present. Database-level role and team agreement remains a separate hardening item. |
| `cache`, `cache_locks`, `failed_jobs`, `job_batches`, `jobs`, `migrations`, `password_reset_tokens`, `sessions` | Laravel-managed infrastructure. | v4 column names are present. |

The schema comparison used the workbook's **Columns** and **Relationships** sheets and a clean migrated SQLite database. It checked column names, foreign-key endpoints and delete actions. Type, default and index parity have not been exhaustively verified. The change passed 92 tests on SQLite and disposable MySQL and the production frontend build.

## Platform admin · Directory management

The left panel provides Cities and Areas under **Locations**. **Directory management** provides CRUD for Boards and Curricula, Education Levels, Facilities, School Groups, Listing Fees, School Media, Course Categories, Courses, and Programs. **Listing Assignments** manages the three branch taxonomy pivots. School Groups can attach an unassigned branch. Media accepts uploads and external links. Admin deletion checks protect locations and organisations in use.

The schema comparison matched the workbook's 119 columns and 19 foreign keys across the 14 requested tables and the existing school links. The directory catalogue migration ran on local MySQL.

## Platform admin · Plans, claims, and subscriptions

**Platform management** provides full-page record views and forms for organisation members, modules and dependencies, plans and their module bundles and limits, subscriptions, grants and limits, and demo requests. It also provides an OTP activity screen with invalidation and a notification inbox with sending, read state, and removal. Listing claims have a review workflow and history. **Branch modules** supports switching and resetting a branch override. Platform-admin-only module switching remains the access rule.

Subscribed organisations use the plan and active grants together with branch switches and dependency checks. During grace, enabled management modules are read-only. Organisations without a subscription retain the previous module behaviour. Newly verified organisations receive the default plan when one is configured. Limit records can be maintained in admin. Cross-branch usage counting and quota enforcement are pending. Claims currently use manual verification. OTP delivery and marketing-site demo request intake remain pending.

The workbook comparison matched 124 columns and 22 foreign keys across the 14 requested tables. All 100 tests passed on SQLite and disposable MySQL. The frontend build passed and the migration ran on local MySQL.
