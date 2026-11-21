# Manager pack: start here

## Install (once, 5 minutes)
1. Unzip this folder into the **root** of the `school-scui` repository so that `AGENTS.md` sits next to `backend/`, and `docs/` and `.github/` merge with the existing folders.
2. Delete the old `files/` folder (it contains version-2 documents).
3. Copy the full documents you want to keep as references into `docs/` or `docs/old/` (Database Plan v4 workbook, Project Planning Document, Package Selection Guide). Builders are told never to open them; they exist for you and the manager.
4. Create branch `t00/manager-pack`, commit, push, merge. Turn on branch protection for `main` (require the CI check).

## Run a task (every time)
Open a **new** Codex session on the repository and paste exactly one line:

```
Read AGENTS.md, then do docs/tasks/T00-baseline-check.md. Follow it exactly.
```

Replace the task number each time. Order: T00, T01, T02, T03, T04, T05, T06, T07, T08, T09, T10, T11, T12, T13, T14, T15 (see `docs/PROGRESS.md` for dependencies; T02 to T04 can run before or after T05 to T08).

## After each task
1. Send the manager the PR number or link and the 12-line answer.
2. The manager reviews and replies "merge" or a short fix list.
3. Merge, then start the next task in a new session.

## Decisions
`docs/DECISIONS.md` lists pending questions D1 to D10 with defaults. The builder uses the default unless told otherwise, so you can answer them one at a time when a task needs them.

## What is where
| Path | Purpose |
|---|---|
| `AGENTS.md` | Short rules Codex reads automatically (token rules, product rules, test-first, git, final answer) |
| `docs/tasks/` | One brief per module (Read / Touch / Out of scope / Tests first / Acceptance) |
| `docs/specs/` | Short spec cards and `schema-v4.json` (used by the schema-gap command) |
| `docs/REPO-MAP.md` | Map of routes, controllers, models, migrations, tests (so no exploring) |
| `docs/PROGRESS.md` | Status of every task, with credits used |
| `docs/DECISIONS.md` | Decided and pending decisions |
| `docs/MANAGER-CYCLE.md` | The cycle, the review checklist, cost levers and improvements |
