# T00 · Baseline and pack check

Effort: **low** · Depends on: none · Frontend build: **yes (once)** · Decision needed: none

## Goal
Confirm the project installs, builds and passes its tests before any change, and that the manager pack is in place.

## Read ONLY these
- `AGENTS.md`
- `.github/workflows/ci.yml` (first 60 lines)

## Touch ONLY these
- `docs/PROGRESS.md` only

## Out of scope (do not do)
- Any code change
- Any migration

## Tests first (write, run red, then build)
1. No new tests. This task only verifies.

## Build
- `git pull`; report `git log -1 --oneline`.
- Check that `docs/specs/`, `docs/tasks/` and `docs/REPO-MAP.md` exist. If the old `files/` folder or a version-2 workbook is still in the repository, STOP and report.
- Run the whole suite once on SQLite.
- Run `npm ci && npm run build` once.
- Check the latest CI run (`gh run list --limit 3` if `gh` exists; otherwise say you could not).
- Write the results in `docs/PROGRESS.md` (T00 row).

## Acceptance
- [ ] Report: tests passed/total (expected 74), build ok or not, CI status, and the HEAD commit.
- [ ] Branch `t00/baseline-check` pushed with the PROGRESS update only.

## Finish
Targeted tests green, then the whole suite once, then push branch `t00/baseline-check`, open the PR, update `docs/PROGRESS.md`, and give the 12-line final answer from `AGENTS.md`.
