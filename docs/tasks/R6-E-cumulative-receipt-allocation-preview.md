# R6-E · Check cumulative receipt allocations in the billing preview

## Outcome

Extend the merged R6-D read-only preview to detect when multiple otherwise valid family receipts allocate more than a monthly charge's original amount. Keep the current JSON evidence format and preserve the original billing source totals.

## Read

- `docs/tasks/R6-D-billing-mapping-preview.md` (evidence format and existing rules only)
- `backend/app/Services/BillingObligationMappingPreview.php`
- `backend/tests/Feature/BillingReconciliationPreviewTest.php`

## Touch (proposed for approval)

- `backend/app/Services/BillingObligationMappingPreview.php`
- `backend/tests/Feature/BillingReconciliationPreviewTest.php`
- `docs/PROGRESS.md`

## Behaviour

- Validate each receipt using R6-D rules first. Only individually valid receipts contribute to cumulative proposed allocations.
- Sum those allocations per eligible branch charge in exact minor units. A sum equal to the charge amount is valid. An excess marks every contributing receipt unresolved with a cumulative over-allocation reason. Keep each receipt intact, including when it splits across multiple charges. Do not pick a winner based on input order.
- Report the affected charge IDs, proposed totals, charge amounts and excess amounts in stable order. Preserve the existing invoice mapping and all source totals.
- Use synthetic data only. This preview compares proposed allocations with original charge amounts. Checking previously posted allocations or remaining balances belongs to a later posting task. No writes, migrations, live evidence or finance UI changes.

## Acceptance tests

- Two valid receipts allocate 600.00 and 500.00 to a 1,000.00 charge. Both are unresolved and the excess is 100.00.
- Two receipts that total exactly 1,000.00 stay allocatable. A separate valid receipt and charge remain unaffected.
- A receipt with invalid evidence contributes zero to cumulative proposals. A split receipt touching an over-allocated charge stays unresolved as a whole.
- Reordering evidence yields the same classifications and diagnostic totals. Existing branch, cutoff, source-total and SELECT-only checks still pass.

Frontend build: no
