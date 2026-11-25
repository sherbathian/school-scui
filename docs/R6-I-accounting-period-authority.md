# R6-I · Accounting-period and business-date authority

This design proposes a branch-owned calendar for the v4 receipt ledger. The existing posting service accepts a received date and records a server timestamp. The reversal service accepts a caller-verified period. Before an authenticated entry point is enabled, both services should obtain the same period and business date from the server inside their write transaction.

## Proposed authority

| Decision | Proposed rule |
| --- | --- |
| Calendar | One monthly period row per branch and local calendar month with explicit inclusive start and end dates. At most one period can be open per branch. The system does not silently create or close a month. |
| State | An explicit, branch-authorised operator opens a period. Only an open period containing the current branch business date permits new writes. Allow closing on the final local date or later. After an early-in-the-day close on the final date, further writes fail for that date. Periods may be opened in advance after the prior one is closed; writes wait until the new period starts. |
| Closed periods | Closing is final in the first release. Corrections post in the next open period, with original reference and controlled reason. Gaps remain visible. Opening an older period after a newer one has been opened is rejected. Reopening and backdating require a separate policy. |
| Business timezone | Store an explicitly approved IANA timezone in branch finance settings. Use Asia/Karachi for the synthetic IFS scenarios. Missing or invalid timezone blocks new writes. Freeze timezone changes after the first period in this slice so an existing period or posted business date cannot be reinterpreted. |
| Permissions | Require active access to the specific branch and an explicit `fees.period.manage` grant for period administration. Posting and reversal use separate grants and the Fees module write gate. Finance auditors receive a branch-scoped read capability. No default grants. Grant delegation and any emergency override require separate approval. |

## Posting date and received date

- Capture one server clock instant after obtaining the branch calendar lock. Convert that instant to the configured timezone, derive its calendar date, and hold the result for the transaction. Record the accounting date, period ID, timezone snapshot and UTC server timestamp on the posted operation or period audit record. The server determines this context independently of request fields.
- Store the date funds were received separately. Reject a future received date. An older received date may be allowed with verified documentary evidence and a controlled late-entry reason. Prepare the late-entry evidence policy before accepting this case through an API. Book the entry against the open period containing the actual accounting date.
- A full reversal of an older receipt offsets it in the current open period, preserves the original receipt and accounting date, and cannot predate the original posting. A request cannot direct a reversal into the original closed period. The reversal carries the original and current period references for independent reconciliation.
- A successful retry returns the original result, including the original date and period, even after period closure. Server-derived dates and period IDs do not take part in the request fingerprint. Recheck current authentication, branch access, module status and operation grants before returning any result. A changed request under the same key conflicts. A failed attempt creates no replay result.

## Transaction model

1. Use the existing branch row as the shared lock, with branch ID as the lock key. Opening, closing, posting and reversing acquire that lock within the transaction, before reading calendar state or taking operation, source, original receipt or charge locks. Read the period with a current locking read on MySQL. The branch lock serializes writers even if a period row is missing.
2. For posting and reversal, after branch authorisation and shared lock acquisition, resolve the replay key before checking the current period. For a new request, derive the business date, verify exactly one applicable open period, and proceed with the existing operation, source or original receipt and ascending charge locks. Keep retries bounded.
3. For period close, acquire the same branch lock before changing status and before capturing its audit event. If posting acquires the lock first, it can commit under the old open period and the close follows. If close acquires it first, a new posting fails and leaves no receipt, audit event or consumed key. Apply the same rule to reversal. Never fall back to an inferred period.
4. A branch period row uses a branch/month unique key. A nullable open marker with branch uniqueness provides a database guard against simultaneous open periods. Use restrictive branch and period references for fiscal records. Historical rows without period authority need explicit reconciliation or compatibility treatment before any cutover.

## Period audit

Opening and closing each write an append-only audit event in the same transaction as the state change. Capture branch, actor, period ID, inclusive dates, previous and new state, business timezone, UTC time, and a controlled reason code where applicable. Identical retries and rejected or rolled-back actions create no duplicate event. Keep period events visible only to actors allowed to view finance history for that branch. Presentation and grants belong to a later authenticated entry-point task.

## Manual finance acceptance plan

Use two synthetic branches, an authorised period manager, a poster, a reverser and a branch finance auditor. Freeze the server clock for time-sensitive tests.

| Scenario | Expected result |
| --- | --- |
| Midnight in Asia/Karachi | At 18:59:59 UTC on 30 November, accounting date is 30 November locally. At 19:00:00 UTC, date is 1 December. A November-only period rejects the second request. |
| Month end | Close November on its last local day. New entries fail immediately after close. Open December and confirm it becomes eligible at the local start date. Attempting to open another concurrent period fails. |
| Missing or invalid settings | Missing timezone, invalid timezone, missing period, and a period that does not cover today each block new posting and reversal with no financial writes. |
| Earlier receipt | In December, use approved evidence for funds received in November. Store the November received date and book the entry in December. Reject a future received date. |
| Prior-period reversal | Reverse a November receipt in an open December period. Preserve the original November entry and record the offset in December. Verify the balance restores once. |
| Concurrent close | On disposable MySQL, control lock acquisition so posting wins once and closing wins once. Verify the resulting rows, audit counts and replay keys in both orders. Repeat for reversal. |
| Replay after closure | Re-send the original key and payload after closing the period. Verify the original result under current permissions and no new event. A changed payload conflicts. |
| Permission and audit | Try direct requests as the wrong role and as an authorised user from another branch. Verify denial without disclosing the target period. Confirm the finance auditor sees only authorised period events. |
| Audit rollback | Simulate an event-write failure during close. Verify the period remains open and that an authorised alternate request can close it once. |

## Proposed first implementation slice · R6-J

Add an internal branch period authority with branch timezone settings, open/close state and transactional period events. Verify period status and dates on synthetic SQLite and MySQL data. Defer posting/reversal integration and public entry points to separate tasks so their migration and compatibility requirements can be reviewed together.

**Proposed Read:** This design, the R6-F posting rules, `backend/app/Services/{PostFamilyReceipt,ReverseFamilyReceipt}.php`, and the schools and role entries in `docs/REPO-MAP.md`.

**Proposed Touch:** New `backend/app/Services/BranchAccountingPeriods.php`, one new migration ending in `_create_branch_accounting_period_tables.php`, new `backend/tests/Feature/BranchAccountingPeriodsTest.php`, `docs/PROGRESS.md`, and `docs/DECISIONS.md` for approved decisions.

**Approval needed before implementation:** Monthly periods, one open period per branch, final closure, and branch-specific IANA timezone. The proposed IFS test timezone is Asia/Karachi. Production timezone settings and permission grants require explicit operational approval.
