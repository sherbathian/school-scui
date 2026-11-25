# R6-L · Authenticated receipt posting and reversal endpoints

## Outcome

Expose the existing v4 receipt posting and full reversal services through authenticated branch APIs for a synthetic pilot. Enforce branch access, the Fees module write gate and explicit action permissions on every request, including retries.

## Read

- `docs/R6-F-receipt-posting-rules.md`, `docs/R6-I-accounting-period-authority.md`
- `backend/app/Services/{PostFamilyReceipt,ReverseFamilyReceipt,BranchAccountingPeriods,BranchModules,SchoolRoles}.php`
- `backend/app/Models/User.php`
- `backend/app/Http/Middleware/{RequireSchoolPermission,RequireBranchModule,ResolveSchoolPermissionTeam,RestrictPersonalAccessToken}.php`
- `backend/routes/api.php`, relevant finance and authentication groups only
- `backend/tests/Feature/PostFamilyReceiptTest.php`
- `.github/pull_request_template.md`

## Touch (proposed for approval)

- `backend/routes/api.php`
- `backend/app/Http/Controllers/Api/PostedFamilyReceiptController.php` (new)
- `backend/app/Services/SchoolRoles.php` (keep new grants out of default branch roles)
- One new migration ending in `_register_receipt_operation_permissions.php`
- `backend/tests/Feature/PostedFamilyReceiptApiTest.php` (new)
- `docs/PROGRESS.md` and `docs/DECISIONS.md` for approved decisions

## Behaviour and verification

- Provide branch-scoped posting and full reversal routes. Require explicit `fees.receipt.post` or `fees.receipt.reverse` on the requested branch. A general finance role or `fees.manage` alone does not qualify. Use the existing branch and module access mechanisms. Grant no new permissions to existing users or roles.
- Derive the actor, branch and accounting context on the server. Use the selected pilot branch's verified PKR context. Approve the pilot branch and the source of its currency before allowing real use. Fail closed when pilot eligibility or currency cannot be established.
- Validate the idempotency key, amounts and allocations. Reject duplicate charge IDs before normalizing the payload. Full reversal accepts only the original receipt ID and a controlled reason. The API cannot override period, timezone or accounting date.
- Verify unauthenticated, ungranted, foreign branch and disabled Fees module requests leave no writes. Verify successful posts, reversals, identical replay after period closure, and changed-payload conflicts. Check that revoked access blocks replay and that errors do not disclose records in other branches.
- Keep finance audit viewing, period administration endpoints, UI, production configuration and report cutover for separately approved tasks. Use synthetic data only.

Frontend build: no
