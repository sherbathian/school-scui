# R9-A · School website admin acceptance

## Outcome

Verify that the existing theme, site settings and school page admin workflows are ready for staging. Capture any missing preview or publishing behavior as a separate implementation brief with an explicit file list.

## Read

- `docs/PROGRESS.md`, websites and billing status
- `backend/app/Http/Controllers/Api/AdminPlatformController.php`, website resources and shared CRUD handlers
- `backend/resources/js/pages/Admin/platform-fields.js`, website fields
- `backend/tests/Feature/AdminPlatformTest.php`
- `backend/database/migrations/2026_11_19_133657_create_v4_billing_websites_and_devices.php`, website tables only
- `.github/pull_request_template.md`

## Touch

- `backend/tests/Feature/AdminPlatformTest.php`, focused website regression coverage
- `docs/PROGRESS.md`, record verified and outstanding behavior

## Acceptance

- On synthetic data, verify platform admin create, list, edit and permitted removal for themes, site settings and school pages through their existing API entry points.
- Check anonymous and unauthorised requests, branch association, supported page states and invalid relationship input. Test deletion only where allowed by the existing product workflow.
- Document whether draft preview and public publishing each have a supported route. Record any missing visibility, product-policy or hosting decisions required for the next brief.
- Preserve the existing free public listing eligibility rules. Keep all checks on disposable fixtures and leave existing sites and production data untouched.

Frontend build: no.

## Findings

- The website resources use the existing `/api/admin/services` endpoints. The admin CRUD supports themes, settings, and pages. Saving a page as published records a database flag. The verified public directory listing remains published when the website records are removed.
- Authenticated platform admin preview renders draft and published pages at `/local/school-preview/{school}/{page?}` in the local environment. The registered preview route returns 404 in production. No public school-site rendering route was identified in the route inventory.
- On the synthetic fixture, theme linkage and settings editing worked. The site-enable request returned 422. A separate publishing brief must establish enablement requirements, public rendering, and hostname policy before rollout.
