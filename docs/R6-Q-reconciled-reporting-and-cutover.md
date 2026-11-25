# R6-Q · Reconciled reporting and cutover review

## Result

The existing dashboard matched its independent legacy controls. The v4 ledger matched a second calculation reconstructed from finance audit events. The two systems report different activity. A confirmed obligation link allows review of overlapping balances. It does not establish that a legacy payment and a v4 receipt are the same cash event.

## Verification setup

Used a newly migrated disposable SQLite database and synthetic PKR fixtures for two branches. The primary branch had two monthly charges of PKR 1,000 each. The fixture posted a PKR 750 split receipt in November (PKR 450 to A and PKR 300 to B), reversed it in December, then posted a new PKR 100 receipt to B. The outside branch posted PKR 999 and had a separate legacy payment. Neither entered the primary branch's results.

The primary legacy source had a PKR 1,000 invoice explicitly mapped to charge A using the synthetic evidence file and a separate PKR 200 invoice with no v4 match. The November legacy payments were PKR 450 and PKR 50. An imported family receipt and a monthly snapshot each recorded PKR 750 as evidence. Neither entered either set of posted collections.

The comparison invoked the actual dashboard controller and the existing reconciliation command. Independent SQL computed the dashboard controls from legacy tables. Separate ledger SQL grouped v4 headers by branch, currency and accounting period and calculated the outstanding balances using only postings and reversals effective by the cutoff. A separate calculation replayed ordered finance audit balance transitions and compared every balance with ledger SQL. There is currently no v4 finance report in the report centre to compare against these controls.

## Expected versus actual

Each cell shows the independent expected value and the identical observed value. Monetary amounts are PKR.

| Measure | 30 November 2026 | 1 December 2026 |
| --- | ---: | ---: |
| Legacy dashboard unpaid invoices | 2 | 2 |
| Legacy dashboard fees received in the displayed month | 500.00 | 0.00 |
| Legacy collectible balance at cutoff, reconciliation preview | 700.00 | 700.00 |
| v4 gross posted in period through cutoff | 750.00 | 100.00 |
| v4 reversal offsets in period through cutoff | 0.00 | 750.00 |
| v4 net movement in period through cutoff | 750.00 | -650.00 |
| v4 charge A remaining at cutoff | 550.00 | 1,000.00 |
| v4 charge B remaining at cutoff | 700.00 | 900.00 |
| v4 total remaining at cutoff | 1,250.00 | 1,900.00 |

The dashboard selects legacy collections by the payment date in the application calendar month. Its unpaid count is a current open-invoice count with payments deducted, without an as-of filter. The legacy preview uses its explicit cutoff for invoice creation and payment dates and the stored invoice status. The v4 comparison uses the branch accounting date and reversal month. December has negative net movement because its reversal exceeds its new receipts. November's balance and posted receipts remain as they were at the November cutoff. No unexplained variance was found within either source.

The mapping confirmed PKR 1,000 of original obligation and left PKR 200 legacy-only. Cash-link evidence, currency authority for production branches, legacy balances held outside an approved snapshot, and production period ownership remain separate dependencies. Do not add or subtract the two sources to infer consolidated receivables.

## Reporting implementation and rollout proposal

1. **Approve source ownership.** The finance owner signs a branch, currency and period inventory and an evidence-backed opening balance map. Legacy owns legacy-only items and historical collections. v4 owns its posted receipts, reversals and charge balances. Imported evidence and snapshots remain reference sources. Assign a single report owner to each overlapping obligation at a signed cutoff. Reconcile payment identity separately.
2. **Build a parallel read-only report.** Require explicit branch finance report permissions, branch access and applicable Fees and Reports module access. Show legacy and v4 controls separately, expose source, time basis, currency and cutoff, and keep imported evidence out of cash totals. Include detail traceability without exposing unnecessary personal data. Test the independent controls, denied access, cross-branch scope and month boundaries on SQLite and MySQL.
3. **Reconcile before enabling the new reader.** Run it beside the current reports against an approved stable copy. Compare counts and integer minor-unit totals per branch, currency and period, including monthly and cutoff balances and reversal offsets. Require zero unexplained monetary or record-count variance and named finance and technical sign-off. Give known and approved timing differences a documented explanation and owner.
4. **Approve and execute a controlled switch.** Record the branch, reader version, cutoff, allowlist, evidence and rollback owner. Use a documented write boundary or replay since snapshot. Capture a verified backup and immutable ledger high-water marks. Keep reconciliation and audit access available. Switch the approved branch reader only after explicit approval and run post-switch controls immediately.

## Recovery drill to run before cutover

On a disposable copy, capture the old-reader output and record the receipt, reversal and audit high-water marks. Switch the pilot reader and enter a synthetic posting and reversal. Inject a reporting mismatch. Stop further pilot entry and revert the reader configuration without reverting ledger rows. Compare both readers and account for every post-switch operation by ID and period. Check original and reversal links, audit event counts, idempotency keys and balances. Re-enable the candidate reader only after the variance is resolved and signed off. A passing drill preserves post-switch transactions and audit history and creates zero duplicate operations. This drill requires a future candidate reader and was documented, not executed.

No application code, reporting source, schema, or live data changed. The release decision remains pending the parallel report, production reconciliation, recovery drill and explicit authorisation.
