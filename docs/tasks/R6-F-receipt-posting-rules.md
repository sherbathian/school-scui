# R6-F · Define receipt posting and reversal rules

## Outcome

Produce a design brief and manual acceptance plan for posting a family receipt to v4 monthly charges. The design must address current balances, concurrency, retries, reversals, and finance audit events. The finance freeze remains in place pending approval of a separate implementation task.

## Read (proposed for approval)

- `docs/R6-billing-source-inventory.md`
- `docs/R6-billing-consolidation-assessment.md`
- `backend/app/Services/BillingObligationMappingPreview.php`
- `backend/app/Services/BranchAuditLog.php`
- `backend/app/Http/Controllers/Api/FamilyFeeReceiptController.php`
- `backend/app/Models/FamilyFeeReceipt.php`
- `backend/app/Models/StudentFeeCharge.php`
- Relevant finance schema definitions cited in the source inventory.

## Touch (proposed for approval)

- `docs/R6-F-receipt-posting-rules.md` (new design brief)
- `docs/PROGRESS.md`

## Design questions and acceptance cases

- Define the posted receipt and allocation records, amount precision, currency source, branch ownership, and the difference between imported evidence and a posted receipt. Use PKR in synthetic examples.
- Define how remaining charge balances account for existing postings and reversals. A charge of PKR 1,000 with PKR 600 already allocated must reject a new PKR 500 allocation while accepting PKR 400.
- Make posting atomic across all charges in one receipt. Define a consistent locking order and test two simultaneous receipts competing for the remaining balance. A failed allocation must leave the entire receipt and audit history unchanged.
- Define a branch-scoped idempotency key. The same key and payload return the original result. A conflicting payload is rejected. Cover simultaneous retries and a retry after the original response is lost.
- Define a reversal that preserves the original receipt and restores the affected balances once. Specify how repeated reversal requests, closed periods, and partial reversals are handled or deferred.
- Specify finance permissions and the audit event fields. Include actor, branch, timestamp, receipt and charge references, currency, amounts, reason, and the original posting reference for reversals. Cover unauthorised and cross-branch requests.
- Document a manual finance test matrix, rollout and recovery rules, and the proposed Read and Touch lists for the first implementation slice. Require reconciliation before switching any balances or reports to new records.

Documentation only. No schema, posting, UI, or live-data changes. Frontend build: no.
