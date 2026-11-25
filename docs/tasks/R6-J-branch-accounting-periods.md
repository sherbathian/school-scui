# R6-J · Internal branch accounting periods

## Decisions for approval

- Use calendar months in each branch's explicitly configured IANA timezone. Permit at most one open accounting period per branch.
- Allow closing on the last local day or later. Closure is final in this slice. A future period can be opened once the previous period is closed, but becomes eligible for posting only when its month starts.
- Lock the timezone after the first period opens. Use Asia/Karachi for synthetic IFS scenarios.

## Read

- `docs/R6-I-accounting-period-authority.md` and `docs/R6-F-receipt-posting-rules.md`
- `backend/app/Services/{PostFamilyReceipt,ReverseFamilyReceipt}.php`
- Branch and role definitions in `docs/REPO-MAP.md`
- `.github/pull_request_template.md`

## Touch (proposed for approval)

- `backend/app/Services/BranchAccountingPeriods.php` (new)
- One new timestamped migration under `backend/database/migrations/` ending in `_create_branch_accounting_period_tables.php`
- `backend/tests/Feature/BranchAccountingPeriodsTest.php` (new)
- `docs/PROGRESS.md` and `docs/DECISIONS.md`
- Temporary synthetic concurrency harness outside the repository

## Behaviour and verification

- Expose internal operations to configure timezone, open a month, close it, and resolve the branch's current accounting date and eligible open period. Require a trusted caller with branch authorisation. Acquire the branch row lock before reading or writing calendar state. Capture the server instant under that lock.
- Enforce month uniqueness and one open period per branch in the database. Reject reopening, opening an older month, early closure, timezone changes after first use, and missing or invalid timezone or period state.
- Write append-only open and close audit events atomically with the period transition. Preserve actor, branch, month, state change, timezone and UTC timestamp. Repeating an identical transition must produce no duplicate event.
- Verify Asia/Karachi midnight and month-end, another branch's isolation, calendar gaps, future openings, missing state, audit rollback and concurrent open attempts on disposable MySQL. Verify migration up and down on SQLite and MySQL.

This slice leaves posting and reversal integration, production settings, permissions and public entry points for separate approval. Frontend build: no.
