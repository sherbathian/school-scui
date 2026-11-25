# R9-B · Public school-site publishing

## Outcome

Serve published school websites on the existing application domain at
`/school-sites/{school}/{page?}`. Use the existing school and page slugs.
The root displays the published page with slug `home` or returns 404 if none
exists. Website publication is separate from the free directory listing.

## Read

- `docs/PROGRESS.md`, website status
- `backend/routes/web.php`, public directory and local preview routes
- `backend/app/Http/Controllers/SchoolPagePreviewController.php`
- `backend/app/Http/Controllers/Api/AdminServicesController.php`, website resource validation
- `backend/database/migrations/2026_11_19_133657_create_v4_billing_websites_and_devices.php`, website tables
- The existing Blade view referenced by the preview controller
- `backend/tests/Feature/AdminPlatformTest.php`
- `.github/pull_request_template.md`

## Touch

- `backend/routes/web.php`, one public school-site route
- `backend/app/Http/Controllers/PublicSchoolSiteController.php` (new)
- `backend/app/Http/Controllers/Api/AdminServicesController.php`, site enablement validation if needed
- `backend/resources/views/directory/school-site.blade.php` (new)
- `backend/tests/Feature/PublicSchoolSiteTest.php` (new)
- `docs/PROGRESS.md` and `docs/DECISIONS.md`

## Acceptance

- Serve a site only for an active, verified, published branch with an enabled site and a valid active theme. Render published pages belonging to that branch. Use the existing homepage convention. Use only published, same-branch pages marked for menu display, ordered by the stored sort order.
- Keep draft pages available through the existing authorised local preview. Anonymous requests for draft, disabled, unknown, or foreign-branch pages return 404.
- Reuse the existing structured content format. Escape user-controlled text and allow only safe link protocols. Preserve the free directory listing and its visibility when a site is disabled.
- Test enablement through the existing platform admin API, visibility boundaries, same-slug isolation across branches, menu filtering, and safe rendering. Use synthetic fixtures.
- Custom subdomains, DNS, deployment and production activation are outside this task.

Frontend build: no.
