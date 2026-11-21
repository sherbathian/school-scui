# AGENTS.md: rules for coding agents (short on purpose; read it fully)

Project: **Schoolhouse** (branch-based institute management) and **TaleemFinder** (public directory). One Laravel 13 app in `backend/` (PHP 8.4). Admin: Inertia + React 19. Public directory: Blade. Target database MySQL; tests default to SQLite in memory.

## How you receive work
You get ONE task file: `docs/tasks/Txx-name.md`. Do that task only. Its "Read", "Touch" and "Out of scope" lists are binding.

## Token and credit rules (important)
1. **Do not explore the repository.** Read only this file, the files listed under "Read" in your task, and files you must edit. Find symbols with `grep -n`; read ranges with `sed -n 'a,bp'`.
2. **Never open:** `*.xlsx`, `*.docx`, `docs/specs/schema-v4.json`, `vendor/`, `node_modules/`, `public/build/`, lock files, or other task files.
3. `docs/REPO-MAP.md` is large. Read only the section you need (`grep -n '^## ' docs/REPO-MAP.md`).
4. While working, run **targeted tests only**: `cd backend && vendor/bin/phpunit --filter Name`. Run the whole suite **once**, right before pushing. CI runs SQLite and MySQL.
5. Run `npm run build` only when the task says `Frontend build: yes`.
6. Do not paste file contents or long logs into answers. Quote only the failing lines.
7. Do not refactor, rename or reformat code you were not asked to change. Add no packages unless the task lists them as approved.
8. Stuck after 3 attempts at the same failure: stop and report. Do not loop.
9. If the task will need more than about 500 changed lines, stop after the first coherent part, push it, and report.

## Product rules (never break)
- A verified institute has a permanent free public listing. Public access = active + verified + published. A subscription never controls the listing.
- One `schools` row per branch. Students, fees, expenses and other operational records belong to one branch; links include `school_id` so one branch cannot point at another's records.
- Everything beyond the listing is a module (plan, add-on, trial or grant) and is switched per branch by a **platform admin only**. Data is never deleted when a module is off, downgraded or expired.
- Enforce permissions, branch access and module access **on the server**, including direct requests.
- Money is `decimal(12,2)` rupees. No cents. No raw HTML from users (structured content only).
- No import of old taleemfinder.pk data. Tests and development use synthetic data. No own domains for institutes.
- Secrets only in environment variables. Never in code, tests or logs.

## Test-first loop (every behaviour)
1. Write the failing test. Run it. Confirm it fails for the right reason.
2. Write the smallest code to pass. 3. Refactor. 4. Targeted tests green. 5. Commit.
Bug fix = failing regression test first. Never delete, skip or weaken a test to get green. New tests are PHPUnit, named after the behaviour (`test_owner_sees_branches_added_later`).

## Git
- Branch from current `main`: `tNN/short-name`. Never commit to `main`.
- One commit per behaviour: `type(scope): behaviour` (`feat(claims): refuse a second pending claim`).
- Before the final push: full suite once, then `git push -u origin <branch>` and open a PR using `.github/pull_request_template.md`.
- Update `docs/PROGRESS.md` (your task's row) and, if you made a decision, `docs/DECISIONS.md`.

## Final answer (max 12 lines, this format)
```
Task:      Txx
Branch/PR: <name> / <link>
Commits:   <n> (<one line each>)
Tests:     <added> new, <total> total, full suite <pass/fail> (SQLite). Not run: <anything>
Changed:   <files or folders, one line>
Decisions: <made / needed>
Risks:     <one line or none>
Next:      <suggested next task>
```
