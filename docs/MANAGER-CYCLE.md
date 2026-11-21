# Manager cycle: how work flows, and how to keep it cheap

## Roles
| Who | Does |
|---|---|
| **You (Tahir)** | Decide, paste one line into Codex, merge pull requests |
| **Manager (Claude)** | Writes the next brief from the previous report, reviews each PR against a checklist, keeps docs and decisions in step |
| **Builder (ChatGPT/Codex)** | Builds one task test-first, runs tests, commits, pushes, opens the PR |
| **CI (GitHub Actions)** | Runs the full suite on SQLite and MySQL plus the frontend build |

## The cycle (one task, usually 1 to 3 hours of builder time)
1. **Brief.** The manager points to one file in `docs/tasks/`. It is already in the repository, so you only paste one line.
2. **Build.** In a new Codex session, paste: `Read AGENTS.md, then do docs/tasks/T05-organisations-and-branches.md. Follow it exactly.`
3. **Gate 1: builder.** Targeted tests while working, whole suite once before pushing, push branch, open PR, update `docs/PROGRESS.md`, 12-line answer.
4. **Gate 2: CI.** Full matrix. Red CI means the builder fixes it before review.
5. **Gate 3: manager review.** You send me the PR number (the repository is public). I read the diff against the checklist below and answer: merge, or a short numbered fix list.
6. **Merge.** You merge to `main`. Start the next task in a **new** session (a long chat costs more on every message).
7. **Next brief.** I update the next task using the previous report (what really exists, surprises, decisions).

## Manager review checklist
- [ ] Tests were written first (red evidence in the PR or the commit order) and each test is named after a behaviour.
- [ ] Only files in the task's Touch list changed; extras explained.
- [ ] Branch scoping: every query and link is limited to the branch; composite links where required.
- [ ] Authorisation: guest, wrong role, right role, other branch, other organisation, and direct requests.
- [ ] Free listing and the public visibility rule untouched or reused, not copied.
- [ ] Module and limit rules come from the services, not from controllers or the browser.
- [ ] Money is decimal rupees; no raw HTML; no secrets; no personal data in tests or logs.
- [ ] Migrations reversible; schema matches `docs/specs`; `docs/REPO-MAP.md` updated for new files.
- [ ] No test deleted or weakened; the previous test total did not drop.

## Where credits go, and the fix
| Cost driver | Fix already built in |
|---|---|
| Agent reads the whole repository to orient itself | `AGENTS.md` + `docs/REPO-MAP.md` + exact Read/Touch lists in each task |
| Agent opens the Excel and Word files | Short spec cards in `docs/specs/`; a rule never to open those files |
| Agent reads 90 tables to find gaps | `schoolhouse:schema-gap` command (T01) compares the database with `schema-v4.json` by code |
| Running the full suite after every edit | Targeted `--filter` loop; whole suite once; CI runs the matrix |
| Frontend build on every change | Build only when the task says so |
| Long conversations that re-send history | One task per session; state lives in `PROGRESS.md`, not in the chat |
| Large tasks that wander | Size cap (about 500 lines), Out of scope list, stop after 3 failed attempts |
| Long explanations in answers | 12-line fixed final answer; no pasted code or logs |
| Design discussion inside the coding session | Decisions settled before the brief (pending list with defaults in `DECISIONS.md`) |
| Mass test breakage when adding rules | `GrantsModules` test helper (T08) so old tests change by one line |

## Suggested improvements to this cycle (in order)
**Do now (cheap, high effect)**
1. Commit this pack, delete the old `files/` folder, and make `main` require the CI check before merging (GitHub branch protection).
2. Use the one-line prompt for every task, always in a fresh session.
3. Record the credits each task used in `PROGRESS.md`. After five tasks you will see which kinds of work are expensive and can split or simplify those.
4. Pick effort per task: use the lower or faster setting for mechanical tasks (T00, T04, T05, T06, T10, T14) and the stronger setting only for logic-heavy ones (T03, T07, T12, T13).

**Next**
5. Turn the 56 design checks into the permanent regression suite (T05, T07, T08, T14 port them). Mark them with a `#[Group('contract')]` attribute so the manager can say "all contract tests green".
6. Add Pint style checks, `composer audit` and `npm audit` to CI so reviews never spend effort on style or known vulnerabilities.
7. Enable Dependabot (security updates only) to avoid dependency drift costing a whole task later.
8. After T02 and T03, freeze a short `docs/UI-PATTERNS.md` listing the components and example page names, so later UI tasks copy a page instead of reading the demo.
9. Write briefs just in time (as now). Do not pre-write far ahead; code reality changes the next task.

**Later**
10. Add a browser-test tool (decision D6) once three or four admin pages exist, with one test per workflow rather than per page.
11. Nightly CI run of the full suite on MySQL with a fresh and an upgrade migration, so schema problems surface early.
12. Small staging site deployed automatically from `main`, so each merged task can be clicked through before the next begins.

## Rules for the manager (me)
- I read the repository only through the PR diff and the files named in the brief; I do not ask the builder to re-explain.
- I never mark something verified that I could not run. The builder's test output and CI are the evidence.
- If a task reveals a design problem, I stop the task, decide, update the spec card, and re-issue the brief.
