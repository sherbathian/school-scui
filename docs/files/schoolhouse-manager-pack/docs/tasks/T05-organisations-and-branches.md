# T05 · Organisations, branches and owner access

Effort: **medium** · Depends on: T01 · Frontend build: **no** · Decision needed: none (provisioning is deferred to T13)

## Goal
Add the organisation and branch structure and the rule that lets an owner open every branch of their organisation.

## Read ONLY these
- `AGENTS.md`
- `docs/specs/spec-organisations.md`
- `backend/app/Models/School.php`, `User.php`
- `backend/app/Policies/SchoolPolicy.php`
- `backend/app/Services/SchoolRoles.php`
- `backend/database/migrations/2026_11_10_000003_create_schools_and_school_memberships.php` (style only)

## Touch ONLY these
- `backend/database/migrations/` three new files: `create_school_groups_table`, `create_school_group_user_table`, `add_branch_columns_to_schools_table`
- `backend/app/Models/SchoolGroup.php` (new); relationships in `School.php`, `User.php`
- `backend/app/Services/AccessibleSchools.php` (new)
- `backend/app/Policies/SchoolPolicy.php` (extend only)
- `backend/database/factories/SchoolGroupFactory.php` (new)
- `backend/tests/Feature/OrganisationAccessTest.php`, `OrganisationSchemaTest.php` (new)

## Out of scope (do not do)
- Creating an organisation automatically (T13)
- Owner reports and dashboards
- Student transfers

## Tests first (write, run red, then build)
1. An owner of the organisation sees both branches (DC1).
2. A branch accountant sees only their own branch (DC2).
3. A person with no link sees nothing (DC3).
4. A teacher linked to two branches sees both but is not an owner (DC4).
5. An owner sees a branch added later without any extra step.
6. An inactive `school_group_user` link grants nothing.
7. `branch_code` is unique inside one organisation but may repeat across organisations.
8. A school group that still has branches cannot be deleted.

## Build
- Columns exactly as in the spec card; composite keys not needed here.
- `AccessibleSchools::for(User)` returns branch ids by the access rule in the spec; use it from the policy, not from controllers directly.

## Acceptance
- [ ] Migration runs up and down on SQLite; new tests green; existing tests green.
- [ ] Existing tests still green; no file outside the Touch list changed (or the reason is stated in the PR).

## Finish
Targeted tests green, then the whole suite once, then push branch `t05/organisations-and-branches`, open the PR, update `docs/PROGRESS.md`, and give the 12-line final answer from `AGENTS.md`.
