# R6-B · MySQL billing reconciliation verification

**Result:** All 24 source-section comparisons matched across two synthetic branches and two inclusive cutoff dates. Monetary variance was zero. Eight preview invocations returned byte-identical output within each repeated pair and issued only SELECT queries.

## Method

Used MySQL 8.4.5 on a newly created local database, verified the active database before migrating, and removed the disposable database after the run. No historical or live application records were used. Fixtures included cutoff-day and next-day writes, a null invoice creation date, a partially paid invoice, an overpaid void invoice, an unpaid void, a payment preceding invoice creation, equal receipt and payment amounts, missing receipt values, and out-of-period charges and snapshots.

The control queries use a next-day-exclusive timestamp boundary, correlated invoice payment sums, independent payment joins, and MySQL DECIMAL aggregations by source. Each control result was compared to the command JSON. The preview emitted no combined collection total.

## Expected and actual results

Each cell is both the independent SQL result and the matching preview value. Amounts are integer minor units with denomination unverified. Null means that no value was supplied for that field.

| Metric | Branch A, 25 Nov | Branch A, 26 Nov | Branch B, 25 Nov | Branch B, 26 Nov |
| --- | ---: | ---: | ---: | ---: |
| legacy invoices · count | 3 | 4 | 3 | 4 |
| legacy invoices · gross minor | 1301 | 1701 | 2301 | 2701 |
| legacy invoices · collectible minor | 700 | 800 | 700 | 800 |
| legacy invoices · void count | 2 | 2 | 2 | 2 |
| legacy invoices · void gross minor | 300 | 300 | 300 | 300 |
| legacy invoices · void balance minor | 100 | 100 | 100 | 100 |
| legacy invoices · overpaid invoice count | 1 | 1 | 1 | 1 |
| legacy payments · count | 2 | 4 | 2 | 4 |
| legacy payments · amount minor | 502 | 802 | 1502 | 1802 |
| legacy payments · on void count | 1 | 1 | 1 | 1 |
| legacy payments · on void minor | 201 | 201 | 201 | 201 |
| legacy payments · predates invoice count | 1 | 2 | 1 | 2 |
| monthly charges 2026-10 · count | 1 | 1 | 1 | 1 |
| monthly charges 2026-10 · amount minor | 11 | 11 | 11 | 11 |
| monthly charges 2026-11 · count | 1 | 1 | 1 | 1 |
| monthly charges 2026-11 · amount minor | 31 | 31 | 1031 | 1031 |
| family receipts · count | 2 | 3 | 2 | 3 |
| family receipts · unverified allocation count | 2 | 3 | 2 | 3 |
| family receipts · total payable minor | 1001 | 10901 | 1001 | 10901 |
| family receipts · received minor | 502 | 10402 | 1502 | 11402 |
| family receipts · balance minor | 499 | 499 | 499 | 499 |
| family receipts · missing total payable count | 1 | 1 | 1 | 1 |
| family receipts · missing received count | 1 | 1 | 1 | 1 |
| family receipts · missing balance count | 0 | 1 | 0 | 1 |
| legacy snapshots 2026-10 · count | 1 | 1 | 1 | 1 |
| legacy snapshots 2026-10 · balance minor | 1011 | 1011 | 1011 | 1011 |
| legacy snapshots 2026-10 · received minor | null | null | null | null |
| legacy snapshots 2026-10 · missing balance count | 0 | 0 | 0 | 0 |
| legacy snapshots 2026-10 · missing received count | 1 | 1 | 1 | 1 |
| legacy snapshots 2026-11 · count | 1 | 1 | 1 | 1 |
| legacy snapshots 2026-11 · balance minor | 901 | 901 | 901 | 901 |
| legacy snapshots 2026-11 · received minor | 0 | 0 | 0 | 0 |
| legacy snapshots 2026-11 · missing balance count | 0 | 0 | 0 | 0 |
| legacy snapshots 2026-11 · missing received count | 0 | 0 | 0 | 0 |
| expenses · count | 1 | 2 | 1 | 2 |
| expenses · amount minor | 29 | 10028 | 1029 | 11028 |

## Cutover prerequisites

- Approve a separate historical-data reconciliation on a disposable copy. Establish branch currency and the source denomination with evidence before interpreting balances.
- Compare historic imported receipts, invoices, payments, monthly charges and snapshots by branch, period and source reference. Require evidence to match an obligation or payment, and investigate ambiguous records and unallocated receipts.
- Invoice status is current. Void history and rows with unknown invoice creation dates need separate evidence to construct a historical balance. Review predated payments, voids with payments and negative raw balances.
- Require zero unexplained variance and a tested recovery plan before changing financial posting or report readers. This run establishes behaviour on synthetic inputs only.
