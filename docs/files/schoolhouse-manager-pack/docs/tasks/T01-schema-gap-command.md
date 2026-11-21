# T01 · Schema gap command (live database vs Database Plan v4)

Effort: **medium** · Depends on: T00 · Frontend build: **no** · Decision needed: none

## Goal
Build `php artisan schoolhouse:schema-gap`, which compares the live database with `docs/specs/schema-v4.json` and writes `docs/SCHEMA-GAP.md`. This replaces reading 90 tables by hand and gives a deterministic, repeatable gap report.

## Read ONLY these
- `AGENTS.md`
- `docs/specs/README.md` (format notes only)
- `docs/specs/schema-v4.json`: **read only its first 60 lines** to learn the structure
- `backend/app/Console/Commands/CreateSuperAdmin.php` (style of an existing command)

## Touch ONLY these
- `backend/app/Console/Commands/SchemaGap.php` (new)
- `backend/app/Services/SchemaGap/*.php` (new)
- `backend/tests/Feature/SchemaGapTest.php` (new)
- `backend/tests/Fixtures/schema-mini.json` (new, tiny)
- `docs/SCHEMA-GAP.md` (generated output)

## Out of scope (do not do)
- Any migration
- Fixing any gap (the report only lists them)

## Tests first (write, run red, then build)
1. Reports a table that is in the spec but missing in the database.
2. Reports a column that is missing.
3. Reports a nullable mismatch and a type-family mismatch (integer, text, decimal, datetime, json; never compare lengths).
4. Reports an extra table that is in the database but not in the spec (candidates to drop).
5. Reports a missing foreign key, including a composite key that should contain `school_id`.
6. Prints a clean result when the database matches a tiny fixture.
7. With `--strict`, the command exits non-zero when any gap exists.

## Build
- Read the live schema with Laravel's `Schema` facade (tables, columns, foreign keys). Do not add packages.
- Group the report by area using the spec's `area` field, and mark each gap with the table's `status` (Existing / New).
- Add `--json` output for later tooling.
- Run the command once against the local MySQL database and commit the generated `docs/SCHEMA-GAP.md`.

## Acceptance
- [ ] All new tests green; command runs on SQLite (tests) and prints a readable report on MySQL.
- [ ] `docs/SCHEMA-GAP.md` committed. Final answer states how many tables are missing, extra and different, with no further analysis.
- [ ] Existing tests still green; no file outside the Touch list changed (or the reason is stated in the PR).

## Finish
Targeted tests green, then the whole suite once, then push branch `t01/schema-gap-command`, open the PR, update `docs/PROGRESS.md`, and give the 12-line final answer from `AGENTS.md`.
