# R0 · Install the manager pack update and record decisions

Effort: **low** · Depends on: none · Frontend build: **no** · Decision needed: none

## Goal
Put the rules, map, specs and briefs into the repository and record the three new decisions. No application code changes.

## Read ONLY these
- The file `schoolhouse-pack-update-2.zip` in the repository root (list its contents with `unzip -l`; do not read the files inside yet)
- `docs/DECISIONS.md` and `docs/PROGRESS.md` (headings only: `grep -n '^#' ...`)

## Touch ONLY these
- Unzip into the repository root: `AGENTS.md`, `.github/pull_request_template.md`, `docs/REPO-MAP.md`, `docs/MANAGER-CYCLE.md`, `docs/ORDER.md`, `docs/specs/spec-dashboards-reports.md`, `docs/tasks/R0` to `R3`
- `docs/DECISIONS.md` (three new table rows only)
- `docs/PROGRESS.md` (add a section only)

## Out of scope (do not do)
- Any application code, schema, config or dependency
- Committing the ZIP itself
- Overwriting an existing file

## Tests first (write, run red, then build)
1. No new tests. Check the environment with `cd backend && vendor/bin/phpunit --filter AdminDashboardTest` (expect 3 tests green).

## Build
- Before unzipping, check none of the target files exist; if any does, STOP and report.
- Add three rows to the table in `docs/DECISIONS.md`, keeping its four columns (Date recorded, Decision, Options considered, Decided by), all dated 20 Nov 2026 and decided by Tahir: (1) Finance widgets and finance reports wait until billing consolidation is done | Build now on the old invoices and port later, or wait | ; (2) The basic dashboard is part of every plan; Reports (report centre, exports, multi-branch overview) is a paid module with slug `reports` | Reports free for all, or a paid module | ; (3) Letting users rearrange or hide dashboard widgets is later | Build it now or later | .
- Add a 'Reporting and dashboards' table to `docs/PROGRESS.md` with R0 done and R1, R2, R3 todo (columns as in the existing tables).
- Two commits: `docs: add manager pack update (rules, map, specs, briefs)` and `docs: record dashboard and reports decisions`.

## Acceptance
- [ ] `AGENTS.md` at the root and the other listed files exist and are committed; the ZIP is not committed.
- [ ] `docs/DECISIONS.md` has the three new rows in its existing table; `docs/PROGRESS.md` has the new section.
- [ ] PR opened, not merged. Final answer states the PR link and the test result.
- [ ] Existing 119 tests still green; no file outside the Touch list changed (or the reason is stated in the PR).

## Finish
Targeted tests green, then the whole suite once, then push branch `r0/install-pack`, open the PR (do not merge), update `docs/PROGRESS.md`, and give the 12-line final answer from `AGENTS.md`.
