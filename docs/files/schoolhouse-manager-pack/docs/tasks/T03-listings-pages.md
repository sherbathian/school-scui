# T03 · Listings pages: Index, Create, Edit

Effort: **high** · Depends on: T02 · Frontend build: **yes** · Decision needed: none

## Goal
Replace the single Listings page with `Listings/Index`, `Listings/Create` and `Listings/Edit`, served by Inertia controllers that use the same rules as the JSON API. The JSON API keeps working.

## Read ONLY these
- `AGENTS.md`
- `docs/specs/admin-ui.md`
- `backend/app/Http/Controllers/Api/AdminListingController.php`
- `backend/app/Http/Resources/AdminListingResource.php`
- `backend/app/Models/School.php`
- `backend/tests/Feature/AdminListingTest.php` (11 tests: read them, they define the rules)
- `backend/resources/js/pages/Admin/Listings.jsx`

## Touch ONLY these
- `backend/app/Http/Controllers/Admin/ListingController.php` (new)
- `backend/app/Actions/Listings/*.php` (new, only to share rules with the API controller)
- `backend/app/Http/Requests/Admin/ListingRequest.php` (new)
- `backend/routes/web.php` (admin listings routes)
- `backend/resources/js/pages/Listings/*.jsx` (new) and `components/listings/*`
- `backend/tests/Feature/AdminListingPagesTest.php` (new)

## Out of scope (do not do)
- Cities and areas (T04)
- Changing publication rules or the JSON API behaviour
- New listing fields (not in this task)

## Tests first (write, run red, then build)
1. Index renders `Listings/Index` with filters, review totals and pagination props.
2. Search by name or slug returns the right rows.
3. Create page renders; store reports a validation error for each required field.
4. Store creates a draft, redirects, and sets a flash message.
5. Edit renders with saved values; update saves and keeps the slug rule.
6. Verify, publish and suspend follow the existing rules (publish needs active + verified; suspension keeps history).
7. A guest is redirected; a user who is not a platform admin is refused.
8. All JSON API listing tests still pass.

## Build
- Move shared rules into action classes used by BOTH controllers; do not duplicate logic.
- Pages follow `docs/specs/admin-ui.md` (card form, two-column grid, toasts, delete/suspend confirmation dialog).
- Use `data-test` hooks on table rows and action buttons.

## Acceptance
- [ ] New and existing tests green; build green.
- [ ] Existing tests still green; no file outside the Touch list changed (or the reason is stated in the PR).

## Notes
If the change grows beyond about 500 lines, stop after Index + the action classes, push, and report; Create/Edit becomes T03b.

## Finish
Targeted tests green, then the whole suite once, then push branch `t03/listings-pages`, open the PR, update `docs/PROGRESS.md`, and give the 12-line final answer from `AGENTS.md`.
