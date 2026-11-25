# R5 follow-up · Audit Log navigation

## Behaviour

Show Audit Log in the shared Schoolhouse sidebar for active platform administrators and users with an explicit audit.view grant on at least one accessible branch. Use the same authorisation rule for the sidebar and the Audit Log route. Keep the existing entry query scoped to authorised branches. Mark the sidebar link active on the Audit Log page.

## Read

- Existing files under Touch.
- `backend/app/Models/User.php`
- Relevant Routes, Middleware and Frontend sections of `docs/REPO-MAP.md`
- `.github/pull_request_template.md`

## Touch (proposed for approval)

- `backend/resources/js/components/admin-layout.jsx`
- `backend/app/Http/Controllers/AdminAuditLogController.php`
- `backend/app/Services/BranchAuditLog.php`
- `backend/app/Http/Middleware/ShareAdminAuditAccess.php` (new)
- `backend/bootstrap/app.php` (register shared admin access middleware)
- `backend/tests/Feature/AdminAuditLogTest.php`
- `docs/PROGRESS.md`
- `docs/DECISIONS.md`

## Acceptance checks

- Platform administrators and users with a valid branch grant receive a true sidebar visibility flag on admin pages.
- Guests and users without a valid grant receive no visible link. An inactive membership does not qualify.
- Direct requests and forged branch filters remain subject to the Audit Log page's server-side checks.
- Check the sidebar link, active state, and Audit Log page on desktop and mobile.

Frontend build: yes
