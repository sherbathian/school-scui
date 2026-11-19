# Schoolhouse progress

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
