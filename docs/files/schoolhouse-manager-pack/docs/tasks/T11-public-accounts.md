# T11 · Public accounts: register, sign in, sign out

Effort: **medium** · Depends on: T02 · Frontend build: **yes** · Decision needed: D5 phone-only sign-up (default: not now, email + password)

## Goal
Visitors and institute owners need accounts before they can claim a listing. Add public registration and sign-in (the admin sign-in stays separate).

## Read ONLY these
- `AGENTS.md`
- `docs/specs/admin-ui.md` (Sign-in and Form rows)
- `backend/app/Http/Controllers/AuthController.php`
- `backend/tests/Feature/AuthenticationTest.php` (names only)
- `backend/routes/web.php` (public block)

## Touch ONLY these
- `backend/app/Http/Controllers/Account/RegisterController.php`, `LoginController.php`, `LogoutController.php` (new)
- `backend/app/Http/Requests/Account/*.php` (new)
- `backend/resources/js/pages/Auth/Register.jsx` (new); reuse `Auth/Login` with a `public` variant
- `backend/routes/web.php` (public account routes)
- `backend/tests/Feature/PublicAccountTest.php` (new)

## Out of scope (do not do)
- Phone sign-up, social sign-in
- Password reset email (separate task)
- Any claim logic

## Tests first (write, run red, then build)
1. Registering with a new email creates a user without any school access.
2. A duplicate email is refused with a field error.
3. Weak passwords are refused.
4. Sign-in works; wrong credentials give one generic message; repeated failures are rate-limited.
5. A signed-in public user cannot reach any `/admin` page or any branch API.
6. Sign-out ends the session.

## Build
- Rate limits on registration and sign-in. Sessions through the existing setup.

## Acceptance
- [ ] New and existing tests green; build green.
- [ ] Existing tests still green; no file outside the Touch list changed (or the reason is stated in the PR).

## Finish
Targeted tests green, then the whole suite once, then push branch `t11/public-accounts`, open the PR, update `docs/PROGRESS.md`, and give the 12-line final answer from `AGENTS.md`.
