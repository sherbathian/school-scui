# T04 · Cities and Areas pages

Effort: **medium** · Depends on: T03 · Frontend build: **yes** · Decision needed: none

## Goal
Rebuild the Cities and Areas admin pages in the same pattern as T03. Behaviour is unchanged.

## Read ONLY these
- `AGENTS.md`
- `docs/specs/admin-ui.md`
- `backend/app/Http/Controllers/Api/AdminLocationController.php`
- `backend/resources/js/pages/Admin/Locations.jsx`
- Existing location tests (`grep -rn 'function test_.*\(city\|cities\|area\)' backend/tests`)

## Touch ONLY these
- `backend/app/Http/Controllers/Admin/CityController.php`, `AreaController.php` (new)
- `backend/app/Http/Requests/Admin/CityRequest.php`, `AreaRequest.php` (new)
- `backend/routes/web.php` (admin locations routes)
- `backend/resources/js/pages/Locations/*.jsx` (new)
- `backend/tests/Feature/AdminLocationPagesTest.php` (new)

## Out of scope (do not do)
- New location fields
- Public directory pages

## Tests first (write, run red, then build)
1. List, create and edit work for cities and for areas.
2. Renaming keeps the existing slug.
3. Deleting a record that is in use is refused with a clear message; an unused record can be deleted.
4. The areas list filters by city.
5. Guests and non-platform-admins are refused.

## Build
- Pages follow `docs/specs/admin-ui.md`. Reuse components from T02/T03; add none unless needed.

## Acceptance
- [ ] New and existing tests green; build green.
- [ ] Existing tests still green; no file outside the Touch list changed (or the reason is stated in the PR).

## Finish
Targeted tests green, then the whole suite once, then push branch `t04/locations-pages`, open the PR, update `docs/PROGRESS.md`, and give the 12-line final answer from `AGENTS.md`.
