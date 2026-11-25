# R6-D · Preview mapping legacy invoices to v4 monthly charges

## Outcome

Extend the read-only reconciliation preview with a mapping assessment. Identify records that may represent the same obligation, show why a match is or is not supported, and leave uncertain balances separate. IFS is a PKR demo dataset. It has no family receipt or legacy snapshot rows, so cover both with synthetic fixtures.

## Mapping rules

- Require the same branch, student and academic year or session. For a monthly obligation, also require an independently established billing month and a verified legacy-to-v4 fee type crosswalk. A title, due date or equal amount alone is insufficient.
- Keep annual or multi-month legacy invoices separate unless an approved source explicitly identifies their component monthly charges and amounts. Never infer a month from an invoice's due date.
- Accept a confirmed match only when an approved source identifies both records and the money reconciles at two-decimal scale. Check that the invoice and charge are eligible at the cutoff and that the link is one-to-one for this first slice. More than one candidate, missing evidence, incompatible currency, a void invoice or different amounts remain visible as unresolved. A legacy partial payment changes its outstanding amount, not the original obligation amount used for matching.
- Emit counts and minor-unit totals for confirmed, unresolved and excluded records, with reason codes. Keep the original source totals and avoid a combined receivables or collections total. Preserve record IDs for tracing inside the approved environment. Store no matching decisions or financial changes in this preview.
- Report family receipts separately. A matching reference and equal amount identify a review candidate. A receipt becomes allocatable only with evidence of the beneficiary charges and amounts. Monthly snapshots remain observations for their recorded month and must not be posted as cash or summed as cumulative balances.

## Read (proposed)

- `docs/R6-billing-source-inventory.md` and the local `docs/R6-C-historical-billing-reconciliation.md`
- `backend/app/Console/Commands/PreviewBillingReconciliation.php`
- `backend/app/Services/BillingReconciliationPreview.php`
- `backend/tests/Feature/BillingReconciliationPreviewTest.php`
- Finance migration definitions for legacy invoices, payments, v4 charges, fee types and sessions referenced by the inventory. Read only matching definitions.

## Touch (proposed)

- `backend/app/Console/Commands/PreviewBillingReconciliation.php`
- `backend/app/Services/BillingReconciliationPreview.php`
- `backend/app/Services/BillingObligationMappingPreview.php` (new)
- `backend/tests/Feature/BillingReconciliationPreviewTest.php`
- `docs/PROGRESS.md`
- `docs/DECISIONS.md` (approved decisions only)

## Test scenarios

- Synthetic one-to-one match with a verified source link. An invoice for PKR 1,000 with a PKR 300 payment still matches a PKR 1,000 charge. The legacy collectible amount remains PKR 700.
- Reject fuzzy, ambiguous, cross-branch, cross-student, mismatched-period and mismatched-amount matches. Keep unpaid voids and annual invoices with no period evidence out of the confirmed totals.
- Synthetic family receipt for PKR 750 for two students. With independently supported allocations of PKR 450 and PKR 300, verify that they sum to one receipt and remain separate from existing legacy collections. An equal reference and amount without student-level evidence stays unresolved. The preview does not post allocations.
- Synthetic monthly snapshots with balances of PKR 1,000 and PKR 700 in consecutive months. Verify that the report shows each month independently and never reports a PKR 1,700 balance. Distinguish a missing amount from a recorded zero.
- Verify unchanged source totals, stable output across repeated runs, select-only queries, and existing cutoff behavior on SQLite and MySQL. Use synthetic matches only until the input format and a real crosswalk are separately approved.

No schema changes, backfill, payment posting, audit event writes, or finance report changes. Frontend build: no.


## Evidence format for synthetic verification

Run the existing command with `--mapping-evidence=/path/to/evidence.json`. The optional JSON object has two lists. Use positive integer IDs and minor units.

- `invoice_links`: one object per proposed link with `invoice_id`, `charge_id`, `billing_month` (YYYY-MM), `legacy_fee_type_id`, `v4_fee_type_id`, `invoice_currency` and `charge_currency` (three uppercase letters). The operator attests that the source establishes the month, crosswalk and currencies.
- `receipt_allocations`: one object per receipt with `receipt_id` and an `allocations` list of `charge_id` and `amount_minor` pairs. The operator attests that each charge is a beneficiary. The preview checks availability and amount consistency for each receipt. It does not establish cumulative charge balances across receipts.

Review only synthetic evidence in this task. A real crosswalk needs separate approval.
