# R5 · Administrative audit log

Tahir approved including a read-only Audit Log page and a dedicated View audit log permission. The file scope below is proposed for approval before implementation.

## Behaviour

- Record branch membership additions, removals and role changes, branch role creation, rename, permission changes and deletion, and branch module enable, disable and override reset when persisted configuration changes.
- Persist the actor ID, branch ID, timestamp, action, target identifier and allowlisted before/after values. Store role identifiers, permission keys and module state where relevant. Exclude credentials, personal details and raw payloads.
- Write the event inside the same database transaction as the mutation. Failed, rejected, no-op and rolled-back changes must produce no entry.
- Add a read-only Audit Log page. Show newest entries first with stable ordering and pagination. Allow branch, action and date filters.
- Platform administrators can view all entries. Other users require audit.view on each branch whose entries they can view. Apply scope to the query and available branch filters, including direct requests. Do not grant the permission automatically to existing branch roles.
- Finance, exports and retention tooling are outside this task.

## Read

- All paths under Touch where they already exist.
- `backend/app/Models/User.php`
- `backend/app/Services/BranchModules.php`
- `backend/app/Http/Middleware/RequireSchoolPermission.php`
- Relevant Routes and Models sections of `docs/REPO-MAP.md`
- `.github/pull_request_template.md`

## Touch

- `backend/app/Http/Controllers/SchoolUserController.php`
- `backend/app/Http/Controllers/Api/UserController.php` (only if it writes branch memberships or branch role assignments)
- `backend/app/Http/Controllers/Api/SchoolRoleController.php`
- `backend/app/Http/Controllers/Api/AdminBranchModuleController.php`
- `backend/app/Http/Controllers/AdminAuditLogController.php` (new)
- `backend/app/Services/SchoolRoles.php` (permission catalogue)
- `backend/app/Services/BranchModules.php` (only if it owns the mutation transaction)
- `backend/app/Services/BranchAuditLog.php` (new)
- `backend/app/Models/BranchAuditEntry.php` (new)
- One new timestamped migration under `backend/database/migrations/` ending in `_create_branch_audit_entries_table.php`
- `backend/routes/web.php`
- `backend/resources/js/admin.jsx`
- `backend/resources/js/components/admin-navigation.jsx`
- `backend/resources/js/pages/Admin/AuditLog.jsx` (new)
- `backend/tests/Feature/AdminAuditLogTest.php` (new)
- `docs/PROGRESS.md`
- `docs/DECISIONS.md`

## Acceptance tests

- Each covered mutation produces exactly one correctly scoped entry with the expected before and after values.
- Unchanged, rejected and failed mutations produce no entries. An audit write failure rolls back the associated mutation.
- Platform administrators can view all branches. Branch users can view entries only for branches with audit.view. Direct requests and forged branch filters cannot widen scope.
- Filters and pagination preserve scope and deterministic ordering. Entries omit sensitive fields and have no edit or delete actions.
- Use synthetic data. Run targeted PHPUnit tests during development and the full SQLite suite before pushing.

Frontend build: yes
