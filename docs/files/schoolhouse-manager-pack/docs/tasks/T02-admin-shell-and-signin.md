# T02 · Admin UI kit, shell and sign-in page

Effort: **medium** · Depends on: T00 · Frontend build: **yes** · Decision needed: none (packages pre-approved: radix-ui, lucide-react, sonner)

## Goal
Give the admin the demo-style shell (sidebar, breadcrumbs, user menu, theme toggle, toasts) and move sign-in to its own page with its own layout. Behaviour does not change.

## Read ONLY these
- `AGENTS.md`
- `docs/specs/admin-ui.md`
- `backend/resources/js/admin.jsx`
- `backend/resources/js/components/admin-layout.jsx`
- `backend/routes/web.php` (admin block only; `grep -n admin`)
- `backend/resources/js/pages/Admin/Listings.jsx`: only the sign-in part (`grep -n -i 'login\|password'`)
- `backend/tests/Feature/AuthenticationTest.php` and `AdminListingTest.php`: test names only (`grep -n 'function test_'`)

## Touch ONLY these
- `backend/resources/js/layouts/AppLayout.jsx`, `AuthLayout.jsx` (new)
- `backend/resources/js/components/ui/` card, label, checkbox, select, dialog, sonner, spinner (new)
- `backend/resources/js/components/` breadcrumbs, input-error, nav-user (new)
- `backend/resources/js/pages/Auth/Login.jsx` (new)
- `backend/resources/js/admin.jsx`, `admin-layout.jsx` (adapt)
- `backend/routes/web.php` (admin sign-in route only)
- `backend/app/Http/Controllers/Admin/LoginPageController.php` (new)
- `backend/app/Http/Middleware/HandleInertiaRequests.php` (new, shared page props) and `backend/bootstrap/app.php` (register it for web routes)
- `backend/tests/Feature/AdminShellTest.php` (new)
- `backend/package.json`

## Out of scope (do not do)
- The listings list and forms (T03)
- Cities and areas (T04)
- Changing the sign-in API or its rules
- TypeScript, Wayfinder

## Tests first (write, run red, then build)
1. A guest opening `/admin/login` receives the Inertia component `Auth/Login`.
2. The page props contain no default email or password (never pre-filled).
3. A signed-in platform admin opening `/admin/login` is redirected to `/admin/listings`.
4. A signed-in user who is not a platform admin still cannot open `/admin/listings` (same result as today).
5. After a redirect with a success message, the shared page props contain `flash.success`.
6. Existing sign-in, remember-me and sign-out tests stay green.

## Build
- `AppLayout`: collapsible sidebar with groups (Directory: Listings, Cities, Areas), user menu at the bottom with theme toggle and sign out, breadcrumbs, toast area.
- `AuthLayout` + `Auth/Login`: centred card, title and description, email, password, Remember me, autofocus on email, per-field errors, processing state, password cleared after failure.
- UI primitives listed under Touch, in the shadcn/ui style.
- `data-test` attributes: `sidebar-menu-button`, `logout-button`, `login-button`.
- The repository has no Inertia shared-props middleware yet: add one that shares the signed-in user (id, name, platform-admin flag only) and `flash.success` / `flash.error`.

## Acceptance
- [ ] New tests green and the existing 74 untouched and green; `npm run build` green.
- [ ] Sign-in page and an existing admin page render inside the new shell (state this was checked in the build, and attach one screenshot to the PR if you can).

## Finish
Targeted tests green, then the whole suite once, then push branch `t02/admin-shell-and-signin`, open the PR, update `docs/PROGRESS.md`, and give the 12-line final answer from `AGENTS.md`.
