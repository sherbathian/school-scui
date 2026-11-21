# Decisions

## Decided
| Date | Decision | By |
|---|---|---|
| 2026-11-19 | One `schools` record per branch; MySQL is the target; platform admins only switch branch modules | Tahir (handoff) |
| 2026-11-18 | Free listing for every verified institute; modules by plan, add-on, trial; common domain; no own domains; no import of old site data | Tahir |
| 2026-11-18 | Dropped: redirects, family_fee_receipts, legacy_fee_types, legacy_student_fee_snapshots | Tahir |

## Pending (the default is used by the builder if the manager has not said otherwise)
| # | Question | Default | Needed by |
|---|---|---|---|
| D1 | Create the organisation automatically when a claim is approved? | Yes | T13 |
| D3 | Starter plan contents and limits | students, staff, attendance; 80 students, 15 staff, 1 branch | T06 |
| D4 | One-time code channel | Email first; SMS through the same interface later | T12 |
| D5 | Phone-only sign-up | Not now; email and password | T11 |
| D6 | Browser-test tool (Pest browser plugin or Playwright) | None for now; Inertia response assertions in PHPUnit | before UI-heavy tasks |
| D7 | TypeScript for new frontend code | No; keep JSX | before T02 if you want it |
| D8 | Grace period after a subscription ends | 14 days read-only | billing task |
| D9 | Pricing model | Plan price per organisation plus add-ons per branch | billing task |
| D10 | Production database engine (MySQL or MariaDB) | Confirm before staging | staging |
