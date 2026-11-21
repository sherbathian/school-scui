# Spec: admin interface (Inertia demo pattern, React)

Reference: https://demo-v3.inertiajs.com (source https://github.com/inertiajs/demo-v3, MIT). The demo is **Vue**; this project is **React 19 + shadcn/ui**. Copy the structure and look, not the code. Official React equivalent for components: https://github.com/laravel/react-starter-kit. Keep the MIT notice if any code is adapted.

| Pattern | Behaviour |
|---|---|
| Shell | `AppLayout`: collapsible sidebar (groups, user menu at the bottom), content area, breadcrumbs on top |
| Breadcrumbs | Section / list page / current page, e.g. Directory / Listings / Create listing |
| Form page | Full page, heading, `Card` (max width about 2xl); two-column grid on wide screens; label above input; error text under each field |
| Form behaviour | Submit shows "Creating..." and is disabled while processing; "Unsaved changes" hint when dirty; per-field server errors; success toast after redirect |
| List page | Heading with primary action, search and filters, table, pagination, empty state, delete with confirmation dialog |
| Sign-in | Separate `AuthLayout`: centred card with title and description; email, password, Remember me; autofocus on email; password cleared after a failed attempt; generic error; **page starts empty (never pre-filled)** |
| Components | `Button`, `Card`, `Input`, `Label`, `Checkbox`, `Select`, `Dialog`, toast (`sonner`), `InputError`, `Spinner` |
| Theme | Light and dark, switchable from the user menu |
| Test hooks | `data-test` on sidebar menu button, logout button, form submit buttons, table rows |

Approved packages for the UI tasks (MIT/ISC, checked): `radix-ui`, `lucide-react`, `sonner`. Existing: `class-variance-authority`, `clsx`, `tailwind-merge`.
Server rules for every page: Inertia controllers return page props; validation errors come from Form Requests; flash messages via the shared Inertia props; platform-admin-only routes stay behind the existing guard.
