# R6 · Billing consolidation assessment

## Finding

The legacy invoice and payment path calculates student invoice balances. The v4 fee setup creates monthly student charges. The reviewed charge assignment endpoint does not post an invoice, and the family receipt import does not allocate a payment to either path. Before consolidation, reconcile each source independently and establish links that prove which records describe the same obligation or receipt.

## What the reviewed code does today

| Source | Writer and amount units | How it affects balances |
| --- | --- | --- |
| Legacy invoices | The branch invoice endpoint uses an active legacy fee type for the selected academic year and requires an enrollment in that year. The stored amount is integer minor units in `amount_cents`, from the fee type or an imported two-decimal amount. | The displayed balance is the invoice amount minus its payments, clamped to zero. Status distinguishes void, paid, partial, overdue and open. Exclude voids from collectible balances. |
| Legacy payments | The branch invoice payment endpoint writes a payment against one invoice with the branch, date, optional reference and recording user. It locks the invoice inside the payment transaction, rejects void invoices and overpayments, and stores integer minor units. CSV imports require a case-insensitive unique reference per invoice in that path. | A payment changes its invoice balance. Ordinary payment requests have no verified replay key. Repeating a partial payment can post twice while enough balance remains. |
| V4 fee setup and charges | Fee masters are scoped to branch, group, type, session and month. Assignment selects active students with eligible enrollment in the active academic year and creates an open student charge with a two-decimal amount copied from the master. A repeated assignment uses master, student and billing month as its lookup. | A charge is a separate record. This endpoint has no verified link to legacy invoices or payments. Discount configuration is separate CRUD with fixed or percentage values. The reviewed assignment code copies the master amount without applying a discount. |
| Family receipts | The branch receipt endpoint stores the submitted receipt number, parent source ID, dates, amounts, balance and source metadata. It can link a guardian in the same branch. Receipt number and optional source key are checked for uniqueness within the branch. | The submitted amounts and balance are stored without arithmetic reconciliation in this path. The handler creates no student allocations and writes no invoice payment. Treat these receipts as source evidence until matched. |
| Expenses | Branch expense endpoints create, edit and delete expense records. Amounts are represented as two-decimal values on the model. An attachment can be replaced or removed. | Expense totals are separate from fee receivables and collections. The reviewed endpoints do not expose a posting approval or reversal record. |

The reviewed amount writers have no per-record currency input. The legacy API interprets one displayed unit as 100 stored minor units. Confirm the branch currency and source denomination before converting any records. Review the actual column precision, index constraints, snapshot readers, import jobs, student discounts, dashboard totals and report queries in a separately approved source inventory. This assessment did not inspect historical records or establish their counts or monetary totals.

## Reconciliation and cutover proposal

1. **Inventory at a fixed cutoff.** In a disposable copy, capture record counts and monetary totals by organisation, branch, currency and period. For legacy invoices capture gross, voids, recorded payments and outstanding balances. Keep v4 charges, imported family receipts and expenses in separate control totals. Roll up only branches in the organisation and only combine amounts denominated in the same currency.
2. **Match and review exceptions.** Compare original references, branch, student or guardian, academic year, billing month, and amount. A match requires evidence of the same obligation or cash receipt. Flag ambiguous matches, missing allocations, orphan references, contradictory imported balance arithmetic, voids with payments, and negative raw balances hidden by the display clamp. Preserve original IDs and import provenance.
3. **Design posting before migration.** Approve one balance calculation, explicit allocation records, a precision and rounding rule, and reversal semantics. Use integer minor units where currency scale is confirmed and deterministic decimal conversion at ingestion. Reconcile rounding per allocation and confirm allocations sum exactly to the payment. Require a branch-scoped operation key with a unique constraint and store the original result. Reuse the result on an identical retry and reject conflicting payloads under the same key.
4. **Dry-run and reconcile.** Run the mapping twice on a disposable copy. Compare counts and totals before and after by branch, currency and period. Check individual student balances and the link from each payment to its allocation and receipt. Explain every difference and require zero unexplained monetary variance before switching readers or writers.
5. **Cut over with recovery.** Take a verified backup and agree on a write cutoff or a reconciled replay of changes after the snapshot. Keep legacy read access during verification. If reconciliation fails, stop the switch, retain the old write path and investigate exceptions. After new writes begin, reconcile and preserve those writes before reverting routing. A rollback must not discard receipts or reverse a payment twice.

## Finance audit trail proposal

- Record fee type, fee master, fee group and discount changes, charge and invoice posting or voids, payment and allocation posting, receipt issue, refund or reversal, and expense creation, approval, amendment or cancellation where these workflows exist.
- Store actor, timestamp, branch, action, affected record IDs, operation ID, currency, and relevant before-and-after amounts or statuses. Link reversals to original records. Exclude bank and card details, personal contact data, raw import payloads and attachments.
- Write the audit event in the same transaction as the financial change. Identical retries, rejected writes and rollbacks must produce zero additional events. Give finance auditors explicit read permission scoped to the branches they may access. Keep the posted financial records and audit history separately queryable.

## Manual acceptance scenarios

Use two synthetic branches and distinct accounts for a finance operator, a read-only auditor and a user from the other branch. Example amounts use PKR as the test currency.

| Scenario | Steps and expected result | Availability |
| --- | --- | --- |
| Partial payment | Create a 1,000.00 invoice. Post 300.00. Verify paid 300.00, balance 700.00 and partial status. Try 700.01 and verify the payment count and balance stay unchanged. | Existing legacy workflow. |
| Family receipt across two students | Prepare charges of 600.00 and 400.00. Record one family receipt for 750.00 and check its source record. For the future posting flow allocate 450.00 and 300.00, verify balances of 150.00 and 100.00, and verify collection totals count 750.00 once. | Receipt storage exists. Allocation and reconciled cash totals require implementation. |
| Discount | Create a 10% discount and verify the saved rule. Test a percentage over 100 and verify rejection. In the future posting flow apply 10% to 1,000.00 and verify gross 1,000.00, discount 100.00 and net 900.00. | Rule CRUD exists. Posting a discount against a charge requires implementation. |
| Retry | Import the same partial payment reference twice on one invoice and verify the second is rejected and only one payment exists. In the future flow repeat the same ordinary request and operation key, including concurrently, and verify one posting, one audit entry and the same result. | CSV duplicate screening exists. General idempotency requires implementation. |
| Reversal | Post 300.00 on a 1,000.00 invoice. In the future flow reverse it, verify the original and reversal remain linked, the net payment is zero and the balance is 1,000.00. Retry the reversal and verify no second posting. | Payment reversal requires implementation. A paid legacy invoice cannot currently be voided in the reviewed path. |
| Cross-branch access | Sign in as the operator for branch B. Request a branch A invoice, payment action and family receipt list directly, including an invoice ID paired with the wrong branch. Verify denial and no writes. Repeat against the future audit view with a branch A filter. | Existing finance scoping can be checked now. The finance audit view requires implementation. |
| Expense | Record a synthetic 125.00 expense and verify branch scope and two-decimal display. In the future workflow approve and cancel it, then reconcile its final status and audit history without changing fee balances. | Expense CRUD exists. Approval and cancellation accounting require a separate design. |

## Verification

`APP_DEBUG=false vendor/bin/phpunit --filter 'FeeManagementTest|FeeStructureTest'` passed 10 tests with 106 assertions on SQLite. These tests cover the existing invoice, payment, setup and branch validation cases. The manual scenarios above are procedures, and no manual scenarios were executed against live data.
