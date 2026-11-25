# R4 · Organisation student limits

Status: Approved by Tahir. Depends on R3. Frontend build: no.

## Goal

Enforce a configured student cap across the branches of one organisation on the server. Give users a usable validation message in the existing student form and CSV row-error workflow.

## Approved policy

- Use `max_students` for this first enforced limit. Existing plan and subscription limit tables store arbitrary keys. Check existing records for a differently named student cap before deployment.
- Count student records with status `active` once each across non-archived branches of the organisation, including branches where Students is switched off. An enrollment, academic session change, or record of a transfer between existing students does not itself consume a seat.
- Resolve the latest started subscription by start time and ID, consistently with BranchModules. A subscription limit row overrides the plan row. A missing override inherits the plan. An explicit null means unlimited, and zero allows no additional active students.
- Preserve legacy behaviour for organisations without a subscription. Existing entitlement rules decide whether a branch may write, including during grace. Over-limit organisations retain their records and can make edits that do not increase usage.
- Leave starter-plan values unset until Tahir approves commercial limits. Other quota types, usage dashboard, billing, and per-branch caps are out of scope.

## Read

- `AGENTS.md`
- `backend/app/Services/BranchModules.php` for subscription selection and grace
- `backend/app/Http/Controllers/Api/StudentController.php` for create, update, withdrawal and rejoin
- `backend/app/Http/Controllers/Api/AdminCommunityController.php` for confirmation that the existing transfer records reference existing students
- `backend/resources/js/pages/Admin/branch/StudentRoster.jsx` for the student form and CSV import request and feedback
- `backend/database/migrations/2026_11_19_125247_create_v4_platform_entitlements.php` for limit storage

## Touch

- `backend/app/Services/OrganisationStudentLimit.php` (new)
- `backend/app/Http/Controllers/Api/StudentController.php` (only capacity-changing paths and their transaction boundaries)
- `backend/tests/Feature/OrganisationStudentLimitTest.php` (new)
- `docs/PROGRESS.md` and `docs/DECISIONS.md` as required by AGENTS.md

If another capacity-changing route or a CSV feedback change is discovered to require another file, identify it and get approval before editing it.

## Build

- Apply the limit to creation with an active status, updates that move a student into active status, and rejoin. Verify the CSV import and admission conversion use these guarded writes, including direct requests. Use the existing validation response shape with a clear organisation-limit message.
- Keep the count and capacity-changing write in the same transaction. Serialize admission decisions on the organisation row before checking capacity. Use a consistent locking order with student rows, and confirm concurrent branches cannot both take the last seat on MySQL.
- Check quota before committing related records or replacing media. A denied request must leave the student, guardian, enrollment, lifecycle history and files consistent. Existing permission, branch and module checks still apply.

## Tests first and acceptance

1. Two branches in the same organisation share one cap. Another organisation is excluded. The final available seat succeeds and the next active creation is denied.
2. A subscription override beats the plan; explicit null, zero, absent limits and future subscriptions follow the policy. Existing access and grace rules remain enforced.
3. Non-active creation and edits without an increase still work. Withdrawal frees a seat. Reactivation and rejoin at capacity are denied.
4. Verify student CSV rows and conversion inherit the limit. Enrollment-only writes and transfer records referencing existing students consume no additional seats.
5. Direct requests cannot bypass role, branch or module scope. Failed writes leave related data and media consistent. Verify the last-seat race with concurrent MySQL connections.
6. Run focused tests, then the full SQLite suite before pushing. Verify hosted SQLite and MySQL. Open a PR from `r4/organisation-student-limits` without merging.

## Commercial limits

Set actual commercial limits separately when ready.
