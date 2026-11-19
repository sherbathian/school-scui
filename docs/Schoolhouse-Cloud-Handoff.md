# Schoolhouse and TaleemFinder development handoff

- **Snapshot:** 19 November 2026
- **Repository:** [sherbathian/school-scui](https://github.com/sherbathian/school-scui)
- **Verified code baseline:** [`main` at `1f8d2d1`](https://github.com/sherbathian/school-scui/commit/1f8d2d1)
- **Local environment:** Laravel Herd, PHP 8.4, and MySQL. The application lives in `backend/`.

This historical handoff is for a cloud coding session taking over development. Read [PROGRESS.md](PROGRESS.md) and [DECISIONS.md](DECISIONS.md) for changes since this snapshot. Start with the repository README and compare the current checkout with the baseline above. The work described below has been verified locally. Staging deployment and acceptance are still pending.

## Product and design decisions

- **TaleemFinder** is the public education directory. **Schoolhouse** is the platform for directory administration and branch-based institute management. They share one Laravel application and database.
- Build the directory fresh. Use original admin-created and institute-submitted listings. Use synthetic records for development and testing. The existing WordPress site is a reference for page inventory and user journeys.
- Each listing and its managed branch share one `schools` record. Schools, colleges, universities, and training institutes are supported directory categories.
- A verified institute can have a permanent free listing. Public access requires active, verified, and published status. School-management subscriptions and module access are separate from this listing entitlement.
- Students, fees, expenses, and other operational records are scoped to the branch. Authorised owners can access their own branches. Only platform admins can enable or disable branch modules. Enforce permissions and module access on the server.
- Use Inertia, React 19, Tailwind CSS, and shadcn/ui components for the Listing Administration, School Management, and CMS workspaces. Give them a shared layout and role-appropriate pages and navigation. The public directory currently uses server-rendered Blade.
- The approved database target is MySQL. SQLite is useful for fast automated tests. Make changes locally in small, tested increments and use GitHub to track the work.

## What has been implemented

| Area | Delivered |
| --- | --- |
| Laravel foundation | Bootstrapped from `school-gpt`. PHP and JavaScript dependencies are locked. Herd serves the app locally, and the local MySQL schema was migrated. |
| Platform admin | Platform-admin-only sign-in, Remember me, collapsible sidebar, breadcrumbs, and full-page create/edit forms. |
| Listing moderation | Search by name or slug, filters and review totals, draft creation and editing, verification, publication, and suspension. Publication requires an active, verified record. |
| Locations | Separate Cities and Areas pages accessible from the sidebar. Both support listing, creation, editing, and deletion of unused records. Areas can be filtered by city. Renames preserve existing slugs. |
| Public directory | Homepage, category directories, city and neighbourhood pages, keyword search, sorting and filters, list/grid results, public profiles, browser-session saved listings, and comparison for up to three institutions. About, contact, and sitemap are available. |
| School management foundation | Reused the existing school membership, enrollment, attendance, and fee APIs. Fixed default branch role provisioning and enrollment persistence. Updated test fixtures for active academic years and fee billing months. |
| Verification | The latest full run passed **74 tests on SQLite and MySQL**. The frontend production build passed. Herd routes and the public sitemap were checked locally. |

Public results, direct profile URLs, saved lists, comparison, and the sitemap share the same visibility conditions. A suspended listing drops out of those surfaces. This behaviour is covered by feature tests. Saved lists and comparisons currently last for the browser session.

### Entry points

- Public homepage `/`, directory `/directory`, and categories `/schools`, `/colleges`, `/universities`, and `/institutes`
- Public profile `/institutions/{slug}`, saved listings `/saved`, comparison `/compare`, and sitemap `/sitemap.xml`
- Admin sign-in `/admin/login`, directory listings `/admin/listings`, cities `/admin/cities`, and areas `/admin/areas`
- Health check `/api/health`
- Public controller `backend/app/Http/Controllers/PublicDirectoryController.php` and Blade views `backend/resources/views/directory/`
- Admin React pages `backend/resources/js/pages/Admin/` and shared layout `backend/resources/js/components/admin-layout.jsx`

## Where to resume

1. **Close the remaining Milestone 0 and 1 checks.** Compare the current MySQL schema against Database Plan v4. Verify clean and representative upgrade migrations, set up reproducible CI if it is still pending, and run local acceptance with synthetic data. Bring up staging once the target and credentials are available. Check public rendering without JavaScript, sitemap coverage, and withdrawal after suspension. Rehearse backup and restore.
2. **Start Milestone 2 with institute registration and Add or Claim an Institution.** Design the screens on the existing admin/public foundation. Use the v4 claim lifecycle and approval rules. Confirm open identity, verification, and organisation-provisioning decisions before they affect implementation. Test duplicate claims, expiry, and approval races.
3. **Build organisation and branch entitlements.** Implement the agreed plans, add-ons, trials, dependencies, and branch switches. Keep switch writes restricted to platform admins. Test role and branch access through the UI and direct requests, quota boundaries, and retention of records during downgrade or expiry.

## Full milestone plan

| Milestone | Scope and acceptance |
| --- | --- |
| **0 · Database and test baseline** | Finish reconciling the inherited schema with v4 and settle open membership rules. Verify clean and upgrade installs, branch boundaries, and fee history. Record reproducible CI results. |
| **1 · Foundation and directory** | The local admin and public directory described above are implemented. Finish staging acceptance, recovery checks, and directory refinements where data supports them. Review media storage and indicative public fee sources before adding them. Introduce Laravel Scout and Meilisearch when the directory search requirements justify them. |
| **2 · Registration, claims, and entitlement** | Add institute registration, claim verification and review, organisation/branch access, plans and add-ons, module dependencies, limits, and audit history. Confirm that the free listing survives subscription expiry. |
| **3 · School workflows and pilot** | Build the role-based School Management dashboard around the existing APIs. Complete admissions, students, guardians, staff, academics, attendance, fees, expenses, reports, and onboarding. Implement the v4 fee flow and test an end-to-end pilot on staging. |
| **4 · Results, timetable, notices, and portal** | Add exams, marks and results, timetable, notices, and scoped parent/student access. Test release rules, audience targeting, and access across branches and families. |
| **5 · Institute websites, marketing, and billing** | Add eligible branch sites on platform subdomains, controlled templates, demo intake, platform subscription invoices and payments, and entitlement enforcement. Confirm the marketing hostname and billing rules first. |
| **6 · Shared mobile access** | Confirm the PWA and native rollout. Add versioned APIs, device-scoped access and revocation, and notifications as approved. |
| **7 · Additional institute types and community** | Extend institute management for college programmes, courses and batches, and university workflows as approved. Deliver community features such as events, reviews, or book exchange after moderation decisions are agreed. |

### CMS and remaining public pages

Build the CMS as a role-based workspace in the shared React admin. Use structured content with Tiptap when editorial work begins, including drafts, preview, and publishing. Institute details such as city and type belong in structured listing fields.

Prioritise Add or Claim an Institution and account sign-in/registration for the next public release. Prepare privacy, terms, and copyright/takedown pages from approved copy. Parent Guides, Courses, Admissions, Events, Book Exchange, and the parent dashboard remain planned. Connect each page to its approved workflow and data source.

### Decisions still to confirm

Consult the workbook's **Open Decisions** sheet before changing dependent migrations or access rules. In particular, confirm organisation membership and provisioning, phone-only sign-up, media storage, the public fee source, free-plan modules and quotas, grace period, pricing, and the marketing domain. The choice of one `schools` record per branch, MySQL, and platform-admin-only switches has already been made.

## Files to provide to the cloud session

1. **Schoolhouse Database Plan v4** · `docs/Schoolhouse-Database-Plan-v4.xlsx`. Use it as the schema and business-rules authority when references conflict.
2. **Project Planning Document** and **Package Selection Guide** · the current copies are in `docs/`. Use them for compatible workflows and package rationale.
3. **Development Roadmap** and **Project Handover** · in `docs/`. Use them for the original milestone sequence and detailed requirements.
4. **Public design references** · `docs/finial html/homepage.html` and `docs/finial html/taleem-finder-schools-islamabad-v2 copy.html`. The [Inertia demo](https://github.com/inertiajs/demo-v3) is the admin design reference.

These references were outside commit `1f8d2d1`. The documentation sync branch includes the current workbook and planning documents. Attach the public HTML references separately if the cloud session only has the GitHub checkout. Configure cloud secrets through the environment. Use a disposable database for tests since the test suite resets the selected schema.
