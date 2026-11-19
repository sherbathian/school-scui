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
