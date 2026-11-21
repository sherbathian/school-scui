# AGENTS.md: rules for coding agents (short on purpose; read it fully)

Project: **Schoolhouse** (branch-based institute management) and **TaleemFinder** (public directory). One Laravel 13 app in `backend/` (PHP 8.4). Admin: Inertia + React 19. Public directory: Blade. Target database MySQL; tests default to SQLite in memory.

## How you receive work
You get ONE task file: `docs/tasks/Rn-name.md`. The task file is Tahir's approval for the files it lists under "Touch". Anything else (other code, schema, config, dependencies, GitHub settings) needs his approval first. Do that task only.

## Token and credit rules (important)
1. **Do not explore the repository.** Read only this file, the files listed under "Read", and files you must edit. Find symbols with `grep -n`; read ranges with `sed -n 'a,bp'`.
2. **Never open:** `*.xlsx`, `*.docx`, `*.zip`, `vendor/`, `node_modules/`, `public/build/`, lock files, or other task files.
3. `docs/REPO-MAP.md` is large. Read only the section you need (`grep -n '^## ' docs/REPO-MAP.md`).
4. While working run **targeted tests only**: `cd backend && vendor/bin/phpunit --filter Name`. Run the whole suite **once**, right before pushing. CI runs SQLite and MySQL.
5. Run `npm run build` only when the task says `Frontend build: yes`.
6. Do not paste file contents or long logs into answers. Quote only failing lines.
7. Do not refactor, rename or reformat code you were not asked to change. Add no packages unless the task approves them.
8. Stuck after 3 attempts at the same failure: stop and report. Do not loop.
9. If the task will need more than about 500 changed lines, stop after the first coherent part, push it, and report.

## Product rules (never break)
- A verified institute has a permanent free public listing. Public access = active + verified + published. A subscription never controls the listing.
- One `schools` row per branch. Operational records belong to one branch; every query is scoped to branches the user may access. Organisation owners (owner, group_admin, group_accountant, group_viewer) see only their own organisation's branches.
- Everything beyond the listing is a module, switched per branch (`BranchModules`, middleware `RequireBranchModule`) by platform admins only. Data is never deleted when a module is off.
- Enforce permissions, branch access and module access **on the server**, including direct requests. Hiding a menu item is not security.
- **Finance is frozen.** The old cents-based invoices and payments (`amount_cents`) still exist next to the v4 fee setup. Do not build new features on them and do not change them. Billing consolidation is a separate, later brief.
- No raw HTML from users. No secrets in code, tests or logs. Tests use synthetic data only.
- Dashboard and reports: the basic dashboard is part of every plan; **Reports** (report centre, exports, multi-branch overview) is a paid module.

## Test-first loop (every behaviour)
1. Write the failing test. Run it. Confirm it fails for the right reason.
2. Write the smallest code to pass. 3. Refactor. 4. Targeted tests green. 5. Commit.
Bug fix = failing regression test first. Never delete, skip or weaken a test to get green. New tests are PHPUnit, named after the behaviour. Baseline: 119 tests green.

## Git
- Branch from current `main`: `rN/short-name`. Never commit to `main` directly; open a PR and do not merge it.
- One commit per behaviour: `type(scope): behaviour`.
- Before the final push: full suite once, then push the branch and open a PR using `.github/pull_request_template.md`.
- Update your task's row in `docs/PROGRESS.md`; record any decision in `docs/DECISIONS.md`.

## Final answer (max 12 lines, this format)
```
Task:      Rn
Branch/PR: <name> / <link>
Commits:   <n> (<one line each>)
Tests:     <added> new, <total> total, full suite <pass/fail> (SQLite). Not run: <anything>
Changed:   <files or folders, one line>
Decisions: <made / needed>
Risks:     <one line or none>
Next:      <suggested next task>
```
