# R6-P · Synthetic finance pilot acceptance

The pilot exercised the merged finance workspace and APIs with synthetic data only. An isolated, loopback-only server used a fresh SQLite database, a synthetic branch and an outside branch. No changes were made to the standard local application database or production settings.

## Repeatable setup

1. From the backend directory of the merged checkout, run `npm run build`.
2. Create a fresh private temporary directory and an empty SQLite file. Use the local synthetic harness files `seed.php`, `router.php` and `scenarios.py` in `/private/tmp/schoolhouse-r6p` as the fixture, loopback and verification scripts. For a repeat run, copy the scripts and replace their absolute pilot paths with the new directory. Use a new retry log and database for each run.
3. Set `APP_ENV=testing`, `APP_DEBUG=false`, `DB_CONNECTION=sqlite`, `DB_DATABASE` to the fresh absolute path, `CACHE_STORE=array`, and `SESSION_DRIVER=database`. The harness checks the expected database and testing environment before migrating or seeding. Run `php /path/to/seed.php`. The fixture creates separate poster, reverser, period manager and auditor accounts in the synthetic branch, and an outside-branch account.
4. Set `APP_URL=http://127.0.0.1:18732` and `SANCTUM_STATEFUL_DOMAINS=127.0.0.1:18732`. Start `php -S 127.0.0.1:18732 -t public /path/to/router.php`. Keep this router on loopback. It selects an actor and time for synthetic testing only. It must never be used in the regular application or deployed. The selected branch is injected into the testing-only PKR pilot configuration.
5. Run `python3 /path/to/scenarios.py` once on the fresh fixture. It verifies the HTTP statuses, Inertia navigation grants, payloads and independent SQL controls and writes `results.json`. In Chrome, open the same local Branch Operations URL to inspect the poster workspace. Stop the server by interrupting the terminal process that started it. Retain only synthetic evidence needed for review.

## Acceptance results

| Scenario | Expected | Observed |
| --- | --- | --- |
| Separate role navigation | Poster, reverser, manager and auditor each get only their respective finance action. | Matched in four rendered Inertia page responses. Outside branch got 404. |
| Direct access | Guest gets 401, missing grants get 403, outside branch gets 404. | Matched. Disabled Fees blocked audit and receipt replay. |
| Period configuration | Open is rejected without timezone. Setting Asia/Karachi allows November to open once. | Matched. A repeat returned the same period. |
| Partial and split receipt | Post PKR 600 to charge A. Post PKR 450 split between A and B. A has PKR 250 left and rejects PKR 250.01. | Matched. A foreign charge was rejected. |
| Lost response | A simulated 503 after commit retains the key and the same request creates one split receipt and event. | Matched. The two requests carried the same key fingerprint. A changed amount under that key returned 409. |
| Full reversal | Reverse the split once. Preserve the original. Restore A to PKR 400 and B to PKR 1,000 remaining. | Matched. Identical retry returned the same result. A new key returned 409. |
| Period closure and replay | On the last local day, close November. New entries fail while committed identical requests replay. | Matched for posting and reversal replay. The rejected new key was reusable. |
| Karachi midnight and audit filter | At 19:00 UTC on 30 November, require December to open. A December posting belongs to 1 December locally and is still included in the 30 November UTC filter. | Matched. Seven branch audit events contained both local accounting dates. |

## Independent controls

All 36 HTTP checks matched. Independent SQL verified 3 posted receipts, 4 allocations, 1 reversal, 4 receipt audit events, 3 period events, 3 posting keys, and 1 reversal key. The outside branch had no posted receipts.

| Control | Expected | Actual |
| --- | ---: | ---: |
| Gross receipts | PKR 1,150.00 | PKR 1,150.00 |
| Reversal offsets | PKR 450.00 | PKR 450.00 |
| Net receipts | PKR 700.00 | PKR 700.00 |
| Charge A remaining | PKR 400.00 | PKR 400.00 |
| Charge B remaining | PKR 900.00 | PKR 900.00 |

## Remaining verification

- This run covers distinct synthetic actors through the actual server-side branch guards. Interactive credential sign-in for each role and a full transaction walkthrough on a physical phone remain untested. R6-P also verified the poster-only workspace in desktop Chrome and at a 388 px viewport. R6-O previously verified Chrome posting, reversal, retry and responsive rendering on separate synthetic data.
- This pilot does not establish reconciliation for production or reconcile a live report. The next step is a read-only comparison of the current reporting sources and v4 ledger, followed by an approved cutover decision.
