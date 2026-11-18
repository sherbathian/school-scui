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

The local API exposes `GET /api/health`, `POST /api/login`, and the authenticated `GET /api/me`. For browser sessions, use the same local hostname throughout the sign-in flow.

Run the platform-admin and health checks with:

```sh
cd backend
vendor/bin/phpunit --filter 'AuthenticationTest|CreateSuperAdminCommandTest|HealthCheckTest'
```

Tests use an in-memory database. The local setup creates a fresh database without importing existing Taleemfinder listings.
