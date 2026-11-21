# Codex prompts (copy one block per new Codex session)

## 1. R0: install the pack update (paste this full text; the pack is not installed yet)

```
Tahir approves this task. Scope: documentation only, no application code, schema, config or dependencies.
Do not explore the repository. Open only the files named here.

1. From current main create branch r0/manager-pack.
2. The file schoolhouse-pack-update-2.zip is in the repository root. List it with `unzip -l`. If any file it would create already exists, STOP and tell me. Otherwise unzip it into the repository root. It adds AGENTS.md, .github/pull_request_template.md, docs/REPO-MAP.md, docs/MANAGER-CYCLE.md, docs/ORDER.md, docs/specs/spec-dashboards-reports.md and docs/tasks/R0..R3. Do not commit the ZIP.
3. Add three rows to the table in docs/DECISIONS.md, keeping its four columns, dated 20 Nov 2026, decided by Tahir:
   - Finance widgets and finance reports wait until billing consolidation is done (options: build now and port later, or wait).
   - The basic dashboard is part of every plan; Reports (report centre, exports, multi-branch overview) is a paid module with slug `reports` (options: Reports free for all, or paid module).
   - Letting users rearrange or hide dashboard widgets is later (options: now or later).
4. In docs/PROGRESS.md add a section "Reporting and dashboards" with a table: R0 done; R1, R2, R3 todo.
5. Run once: cd backend && vendor/bin/phpunit --filter AdminDashboardTest   (expect 3 passing).
6. Make two documentation-only commits, push the branch, open a pull request, do not merge.

Final answer, 12 lines at most: PR link, the test result, anything unexpected.
```

Merge the R0 pull request, then use one new session per task:

## 2. R1: dashboard widget framework
```
Read AGENTS.md, then do docs/tasks/R1-dashboard-framework.md. Follow it exactly.
```

## 3. R2: more role widgets (after R1 is merged)
```
Read AGENTS.md, then do docs/tasks/R2-non-finance-widgets.md. Follow it exactly.
```

## 4. R3: report centre v1 (after R1 is merged)
```
Read AGENTS.md, then do docs/tasks/R3-report-centre-v1.md. Follow it exactly.
```
