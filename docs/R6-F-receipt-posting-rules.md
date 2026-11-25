# R6-F · Family receipt posting and reversal design

**Status:** Proposed implementation rules. The approved R6-F scope is documentation. The current application still imports family receipt evidence and runs read-only previews.

## Financial records and balance

- **Imported evidence.** Keep `family_fee_receipts` and its provenance intact. A new posted receipt may reference one approved import from the same branch. A unique branch/source-receipt key prevents a second posting from that import, including after reversal. Imported totals never enter posted cash by themselves. An operator must verify which charges belong to the receipt.
- **Posted receipt.** Create an append-only header with branch, server-issued receipt ID, approved currency, received date, server posting timestamp, actor, amount in integer minor units, and optional same-branch source receipt ID. Create one positive allocation per charge. The receipt amount must equal the allocation sum. Exclude unapplied credits and cross-currency receipts from the first implementation.
- **Authoritative amount.** Obtain currency from an approved branch finance setting. Reject a mismatch or missing setting. The first pilot may use an explicitly approved PKR-only branch allowlist if the application has no suitable setting. Convert decimal charge amounts once at scale 2 without floating-point arithmetic. Persist snapshots of currency and posted allocation amounts. The existing v4 charge amount remains the obligation amount.
- **Remaining balance.** For a charge, compute original amount less posted allocations plus applied reversals. Include only same-branch, same-currency entries. A charge of PKR 1,000.00 with PKR 600.00 net allocated has PKR 400.00 available. Reject any proposal above that amount. Legacy invoice payments, imported receipts and monthly snapshots stay outside this formula until a separately approved reconciliation maps them to the same obligations.
- **Integrity.** Use composite branch foreign keys and restrictive delete rules for posted records and charge links. Record keys, timestamps and amounts separately from display labels. Enforce positive amounts and unique charge allocation per receipt. Enforce header-to-lines equality in the transaction. Reject unavailable, cancelled, fully allocated or foreign charges. Preserve the existing charge status fields until a separate read cutover.

## Posting and retry contract

1. Authorise the actor at the server entry point using active branch access, the Fees module's write gate, and an explicit posting permission. Confirm branch/currency eligibility and operator evidence. Normalize the accepted fields and sort allocations by charge ID. Include currency, received date, amount, source ID and allocations in the payload fingerprint.
2. Inside one transaction, reserve a unique branch + operation + idempotency key. Persist the fingerprint and successful response in the same transaction as posting. Equivalent requests return that result. The same key with a different fingerprint returns 409. Resolve a race on the unique key by rolling back the competing transaction and reading the committed row. Recheck current access before returning any result. Failed transactions leave the key reusable.
3. Use a consistent database lock order. Acquire the operation key first, then an existing original receipt for reversals or the source receipt for imported postings, then every target charge by ascending ID. Every writer of these allocations must use the same charge locks. Read the latest committed allocations and reversals **after** acquiring the locks. On MySQL, use current locking reads for the balance rows so an earlier consistent snapshot cannot supply stale totals.
4. Validate every charge and its balance before writing the receipt, allocations and finance audit event. Any failure rolls back all of them. An audited branch user must never learn whether an out-of-scope receipt or charge ID exists. Use a generic unavailable response for foreign IDs.
5. Retry transient deadlocks within a bounded transaction retry budget. Preserve the original key and payload across a lost response or timeout. Keep the import-source uniqueness constraint separate from the client key so replaying the same imported record under a different key cannot post twice.

## Reversals and periods

- Offer a full reversal only, with an explicit reason code and separate permission. Do not delete or edit the original receipt. Insert linked reversal records that offset the original allocations exactly. A unique original receipt reference allows only one full reversal. An identical idempotent retry returns the existing result. A second key against an already reversed receipt returns a conflict and creates no entries.
- A reversal restores the open balances once. Store the original receipt and each allocation reference for traceability. Preserve the original posting date. Record the reversal in the current permitted period. Do not backdate or rewrite a closed period. Before any production posting, define the branch business timezone, open-period authority and posting-date rules. A pilot without reliable period state must fail closed for dates it cannot verify. Partial reversals, refunds and charge edits are separate work.
- Include a distinct reversal event in the same transaction. Audit totals should show the original posting and offset separately so gross collections and net movement can each be reconciled.

## Finance permissions and audit events

Proposed capabilities are `fees.receipt.post`, `fees.receipt.reverse`, and `fees.audit.view`. Keep them explicit per branch. Preserve organisation boundaries and module checks. Ordinary fee management and the administrative Audit Log permission do not automatically grant access to the finance audit history. Define finance-specific presentation and permission assignment in the entry-point task.

For each successful posting or reversal, capture actor ID, branch ID, server timestamp, operation and receipt IDs, original receipt reference when reversing, verified currency, amount, affected charge and allocation IDs, and before/after remaining amounts. Record a controlled reversal reason code. The finance audit event is append-only and commits with the posting. Exclude bank or card details, personal contact data, raw imports, private attachments and free-form notes. Rejected, replayed and rolled-back operations produce no additional events.

## Manual finance acceptance matrix

Use a disposable database with two synthetic branches, a finance poster, a separate reverser and a read-only finance auditor. Use PKR and compare UI or API responses with the underlying records and audit events.

| Scenario | Procedure and expected result |
| --- | --- |
| Partial posting | Post 600.00 to a 1,000.00 charge. Try 500.00 and verify rejection and zero new rows. Post 400.00 and verify zero remaining. |
| Split receipt | Post 750.00 as 450.00 and 300.00 against two students' charges with verified beneficiary evidence. Confirm one receipt, two allocations and one event. Repeat with an insufficient second balance and verify neither charge changes. |
| Concurrent competition | On MySQL, start two different 300.00 receipts against a 400.00 remaining balance. Verify exactly one succeeds, 100.00 remains and only one event exists. Repeat with split receipts listing charges in opposite orders. |
| Retry and conflict | Send the same key and payload concurrently and again after simulating a lost response. Verify one receipt and event, and the same success result. Alter the amount or source with the same key and expect conflict. Try the same source under a new key and verify no second posting. |
| Reversal | Reverse a posted 600.00 receipt in an allowed period. Confirm charge balance increases by exactly 600.00. Repeat with the same key and with a new key. Verify one offset and one reversal event. |
| Closed period and permission | Try backdating a reversal into a closed period, reversing as a poster without permission, and operating on another branch's charge or receipt. Verify denial and no financial writes or events. |
| Audit and rollback | As the branch auditor, reconcile receipt ID, allocation list, original/reversal linkage and amounts. Try a foreign-branch audit request. Simulate audit write failure and confirm no partial posting remains. |

## Rollout and recovery

Start with synthetic SQLite and MySQL tests, including an independent concurrency check on MySQL. Do not run new posting against branch data until the currency, period source and beneficiary evidence are approved. Use an additive schema and keep legacy and new finance readers separate during pilot verification. Reconcile per branch and period against independent control queries. Track imported evidence, posted totals, reversals and net amounts independently. Obtain zero unexplained variance and explicit cutover approval before switching any balances or reports.

If reconciliation fails, disable entry into new posting and preserve the ledger and audit trail for review and replay. Keep existing readers on their previous source until a switch is approved. Once a pilot accepts postings, recovery must account for those postings before switching back. Avoid deleting finance records or silently repeating a posting as part of recovery.

## Proposed first implementation slice · R6-G

Build the posting transaction, append-only records, idempotency and audited writes as an internal service tested on synthetic data. Accept a validated actor, branch and currency context. Keep the service unexposed to HTTP and console entry points in this slice. Add reversal, permission assignment and user-facing entry points in subsequent separately approved tasks. Treat the posted ledger as isolated until read cutover.

**Proposed Read:** This design, `docs/R6-billing-source-inventory.md`, `backend/app/Models/{FamilyFeeReceipt,StudentFeeCharge,User}.php`, `backend/app/Services/BillingObligationMappingPreview.php`, and the migration sections for family receipts and student charges listed in the inventory. Approve any additional file before changing it.

**Proposed Touch:** New `backend/app/Services/PostFamilyReceipt.php`, one new timestamped migration ending in `_create_family_receipt_posting_tables.php`, new `backend/tests/Feature/PostFamilyReceiptTest.php`, and `docs/PROGRESS.md`. Store records through the query builder for the first slice. Record product decisions in `docs/DECISIONS.md` only after approval.

**Implementation acceptance:** Verify exact minor units, same-branch references, one posting per imported source, identical replay, conflicting payloads, remaining-balance enforcement, complete rollback on failure, and one audit event per successful transaction. Verify same-key and competing-balance concurrency on disposable MySQL. No UI, legacy writer or report changes in this slice.
