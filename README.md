# Schoolhouse and TaleemFinder

Schoolhouse is the Laravel 13 application that powers the TaleemFinder public education directory and its administrative workspace. The admin uses Inertia, React 19, Tailwind CSS, and shadcn/ui components. The public directory uses server-rendered Blade. MySQL is the target database.

## What works today

| Area | Current functionality |
| --- | --- |
| Public directory | Homepage, school, college, university, and training institute directories, city and neighbourhood pages, keyword search, filters, sorting, and list/grid layouts. |
| Institution profiles | Public profile and contact details, browser-session saved listings, side-by-side comparison for up to three institutions, and an XML sitemap. |
| Listing administration | Platform-admin sign-in, sidebar and breadcrumbs, listing search and review totals, full-page creation and editing, and draft, verify, publish, and suspend actions. |
| Location administration | Separate **Cities** and **Areas** screens with create, list, edit, and delete. Areas can be filtered by city. Renaming preserves URL slugs, and the database blocks deletion of locations in use. |
| School-management backend | Reused school membership, student enrollment, attendance, and fee APIs. Added default branch role provisioning and fixed enrollment persistence. |

Only active, verified, published listings are eligible for public search, profiles, saved lists, comparisons, and the sitemap. Existing TaleemFinder data has never been imported by this setup.

## Local setup with Herd and MySQL

Use PHP 8.4 with PDO MySQL, Composer, Node.js 22.12 or newer, npm, and a running local MySQL server. From the repository root:

```sh
cd backend
composer install
npm ci
npm run build
cp -n .env.example .env
herd link --isolate=8.4 school-scui
```

In the local `backend/.env`, set `APP_URL=http://school-scui.test`, add `school-scui.test` to `SANCTUM_STATEFUL_DOMAINS`, and set `DB_CONNECTION=mysql` with your connection settings for a local `school_scui` database. Then run:

```sh
# Generate an application key once per new local environment.
php artisan key:generate
php artisan migrate
php artisan school:create-super-admin
```

Keep your application key when restarting the app. The admin command prompts for an email, name, and password.

- [Public homepage](http://school-scui.test/)
- [Platform admin sign-in](http://school-scui.test/admin/login)
- [Directory listings](http://school-scui.test/admin/listings)
- [Cities](http://school-scui.test/admin/cities) and [Areas](http://school-scui.test/admin/areas)
- [Database health](http://school-scui.test/api/health) and [XML sitemap](http://school-scui.test/sitemap.xml)

To publish a new listing, add its city and area as needed, create a draft, verify it, and publish it from the listings table.

## Tests

GitHub Actions runs the PHP suite on SQLite and MySQL 8.4 and builds the frontend on pushes to `main` and pull requests. Each MySQL run uses a disposable service database.

Run the full suite from `backend` on isolated SQLite:

```sh
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: SESSION_DRIVER=array CACHE_STORE=array QUEUE_CONNECTION=sync vendor/bin/phpunit
```

To test on MySQL, set `DB_CONNECTION=mysql` and point `DB_DATABASE` and the connection variables to a **disposable test database**. The suite migrates and resets the selected test database. The latest verified run passed all 74 tests on both SQLite and MySQL, and the frontend build passed.

## Still planned

- Institute registration and listing claims
- Branch management dashboards and module switches managed by platform admins
- CMS publishing, parent guides, and the remaining content and community pages
