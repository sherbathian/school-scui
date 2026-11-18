# Schoolhouse

## Local admin backend

Use PHP 8.4 with PDO SQLite and Composer. From the repository root, run:

```sh
cd backend
composer install
cp -n .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan school:create-super-admin
php artisan serve
```

Generate the application key once for a new local setup. Keep your existing key when restarting the app.

Open `http://localhost:8000/admin/listings` and sign in with the platform administrator you created. Add cities and areas under **Manage locations**, then create a listing. New listings start as drafts. Verify the listing before publishing it. The workspace can filter listings by status, institute type, city, and area.

The local API exposes `GET /api/health`, `POST /api/admin/login`, and the authenticated `GET /api/admin/listings`. For browser sessions, use the same local hostname throughout the sign-in flow.

Run the platform-admin and health checks with:

```sh
cd backend
vendor/bin/phpunit --filter 'AdminListingTest|AuthenticationTest|CreateSuperAdminCommandTest|HealthCheckTest'
```

Tests default to in-memory SQLite. MySQL is the target for staging, production, and database integration tests. To run the focused suite on MySQL, point the test runner at a disposable test database with `DB_CONNECTION=mysql` and the corresponding `DB_DATABASE`, `DB_HOST`, `DB_PORT`, `DB_USERNAME`, and `DB_PASSWORD` environment variables. The test suite resets the configured database.

The local setup creates a fresh database without importing existing Taleemfinder listings.
