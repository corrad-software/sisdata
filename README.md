# MYDS Laravel Starter

A fast-prototyping foundation for Malaysian government-style applications, built with Laravel 12, Livewire, Blade, and Tailwind CSS, styled against the [Malaysian Government Design System (MYDS)](https://github.com/govtechmy/myds).

It ships with authentication, a MYDS-styled public/dashboard shell (top navbar) and a separate admin shell (left sidebar), five reusable UI components, a sample User Management CRUD module, and a set of navbar utilities common to gov sites — dark mode, a WCAG text-size control, an EN/BM language switcher, and a real notification bell — enough scaffolding to start a real project without re-solving the same setup problems.

## Technology

- Laravel 12 / PHP 8.3+
- SQLite
- Blade + Livewire 3
- Tailwind CSS 3.4 (pinned, see [MYDS integration](#myds-integration) below)
- [`@govtechmy/myds-style`](https://www.npmjs.com/package/@govtechmy/myds-style) — MYDS's design tokens as a Tailwind preset

## Installation

```bash
git clone <this-repo> myds-laravel
cd myds-laravel

composer install
npm install

cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate

npm run build     # or: npm run dev
php artisan serve
```

Visit `http://localhost:8000`, register an account, and you'll land on the dashboard. From there, "Admin" in the nav bar opens the sidebar-based admin area, where "Users" is the sample CRUD module.

Run the test suite with:

```bash
php artisan test
```

## Folder Structure

```
app/
├── Http/
│   ├── Middleware/SetLocale.php   # applies session-stored locale (en/ms) on every request
│   └── Requests/
│       ├── StoreUserRequest.php   # validation rules for creating a user
│       └── UpdateUserRequest.php  # validation rules for updating a user
├── Livewire/
│   ├── Auth/, Profile/            # Breeze-generated auth & profile components
│   ├── Layout/
│   │   ├── AdminNavigation.php    # admin topbar: user dropdown + logout
│   │   ├── NotificationBell.php   # notification dropdown (both navbars)
│   │   └── ...                    # public navbar Livewire logic lives in the Volt-style view itself
│   └── UserManagement.php         # sample CRUD module (list/search/create/edit/delete)
├── Models/
│   └── User.php
├── Notifications/
│   └── UserCreated.php            # database notification sent when Users CRUD creates a user
└── View/Components/
    ├── AppLayout.php              # aliases <x-app-layout> to layouts.app
    ├── GuestLayout.php            # aliases <x-guest-layout> to layouts.guest
    └── AdminLayout.php            # aliases <x-admin-layout> to layouts.admin

database/
├── database.sqlite
└── migrations/                    # includes add_role_and_is_active_to_users_table, notifications table

lang/
├── en/                            # Laravel's default validation/auth/pagination strings (published)
└── ms.json                        # Bahasa Malaysia translations for every __('...') string in the app

resources/
├── css/app.css                    # imports MYDS tokens (incl. dark theme) + Tailwind directives
├── views/
│   ├── admin/dashboard.blade.php  # admin area landing page
│   ├── components/                # reusable Blade components (see below)
│   ├── partials/theme-init.blade.php  # blocking <head> script: applies stored dark-mode/font-size before paint
│   ├── layouts/
│   │   ├── app.blade.php          # public/dashboard shell: masthead, top navbar, content, footer
│   │   ├── guest.blade.php        # auth-page shell
│   │   └── admin.blade.php        # admin shell: masthead, left sidebar, topbar, content, footer
│   ├── livewire/
│   │   ├── layout/
│   │   │   ├── navigation.blade.php        # public top navbar (Volt)
│   │   │   ├── admin-navigation.blade.php  # admin topbar dropdown
│   │   │   └── notification-bell.blade.php # shared by both navbars
│   │   ├── pages/auth/            # Breeze Livewire auth screens
│   │   ├── profile/
│   │   └── user-management.blade.php
│   └── dashboard.blade.php

routes/
├── web.php                        # public routes, locale.switch, and the admin.* route group
└── auth.php                       # Breeze auth routes

tailwind.config.js                 # presets: [MYDS tailwind preset], darkMode: 'class'
```

### Reusable components (`resources/views/components/`)

| Component | File | Notes |
|---|---|---|
| Button | `button.blade.php` | `<x-button variant="primary\|secondary\|danger\|outline" size="sm\|md\|lg">`. Renders `<a>` when given an `href`. Breeze's `primary-button`/`secondary-button`/`danger-button` are thin wrappers around it, so every button in the app shares one style source. |
| Form Input | `input.blade.php` | `<x-input label="..." name="..." error="..." help="...">` — label, input, help text, and validation error in one call. Breeze's lower-level `text-input`/`input-label`/`input-error` are restyled to match but kept separate, since Breeze's auth views wire them up individually. |
| Table | `table.blade.php` | `<x-table><x-slot name="head">...</x-slot>...rows...</x-table>` — MYDS-styled wrapper around a standard HTML table. |
| Modal | `modal.blade.php` | Breeze's Alpine-based modal (focus trap, ESC to close, backdrop), restyled to MYDS. Opened/closed via the `open-modal` / `close-modal` browser events (`$dispatch('open-modal', 'name')`), used by both the CRUD module and Breeze's "delete account" flow. |
| Alert | `alert.blade.php` | `<x-alert type="success\|error\|warning\|info" dismissible>`. `auth-session-status.blade.php` delegates to it, so login/registration status messages use the same component. |

`sidebar-link.blade.php` (the admin sidebar's nav-link equivalent of `nav-link.blade.php`) isn't in the "5 required" list above but follows the same pattern, for the same reason `nav-link` exists for the top navbar. The navbar utility controls — `theme-toggle.blade.php`, `font-size-toggle.blade.php`, `language-switcher.blade.php` — are documented in [Navbar Utilities](#navbar-utilities) below.

## Database Design

**`users`** (Laravel's default table, extended)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint, PK | |
| `name` | string | |
| `email` | string, unique | |
| `email_verified_at` | timestamp, nullable | |
| `password` | string, hashed | |
| `role` | string, default `user` | `admin` or `user` — a plain string column rather than a separate `roles` table, to keep the CRUD demo simple |
| `is_active` | boolean, default `true` | drives the Active/Inactive badge in the Users table |
| `remember_token` | string, nullable | |
| `created_at` / `updated_at` | timestamps | |

**`sessions`, `cache`, `jobs`** — Laravel 12 defaults, unmodified (needed since `SESSION_DRIVER`/`CACHE_STORE` default to `database`).

No other tables. A single-entity CRUD is enough to demonstrate the pattern (Model + Migration + Form Requests + Livewire component + Blade components) without adding structure the POC doesn't need.

## Architecture Notes

**Auth** is Laravel Breeze's Livewire stack (`php artisan breeze:install livewire`) — Login, Register, Forgot/Reset Password, Email Verification, and Profile management, all Livewire + Blade out of the box.

**Two layouts, two shells.** `layouts.app` (top navbar) is the general/public-facing shell — Dashboard, Profile. `layouts.admin` (left sidebar + topbar) is a separate shell for the admin section under `/admin/*`, both sitting under the same masthead/footer. They're independent Blade layouts, not a shared component with a "sidebar mode" flag — keeping them separate means each stays simple and neither has to branch internally for the other's shape. The public navbar has a single "Admin" link as the entry point into `/admin`; there's no role restriction on it in this starter (any logged-in user can enter), since gating routes by `role` is a one-line `->middleware('can:...')`/custom middleware addition you can bolt on when you actually need it, rather than something worth scaffolding speculatively.

**The User Management CRUD is Livewire-powered**, not a classic resource controller, and lives under the admin shell at `/admin/users`:

- The route (`routes/web.php`, inside the `admin.` route group) points directly at the Livewire component: `Route::get('users', UserManagement::class)`. The full-page Livewire component *is* the controller layer here — that's the standard Livewire convention, and adding a near-empty `UserController` on top of it would just be redundant indirection.
- `StoreUserRequest` / `UpdateUserRequest` still exist as the single source of truth for validation rules, and `UserManagement::save()` calls into them (`(new StoreUserRequest)->rules()`) instead of duplicating validation logic — Form Requests reused outside the HTTP lifecycle.
- Model + Migration are standard Eloquent.
- Modals for create/edit/delete are the same reusable `x-modal` component described above.
- `#[Layout('layouts.admin')]` on the `UserManagement` class is what places it inside the sidebar shell instead of the top-navbar one.

If you need a second CRUD module that *should* go through classic Controllers (e.g. because it's consumed by an API too), that pattern isn't used here but fits naturally alongside this one — a resource controller + Form Request + Blade views work the same way this app already validates and models data.

## Navbar Utilities

Both navbars (public top nav and admin topbar) share the same four controls, each a small reusable Blade/Livewire piece rather than baked into the nav markup:

- **Dark mode** (`x-theme-toggle`) — toggles a `dark` class on `<html>`, which is all that's needed: this app's colors are built on MYDS's CSS-variable tokens (`bg-bg-white`, `text-txt-black-900`, `border-otl-divider`, etc.), and `@govtechmy/myds-style/dark.css` redefines those same variables under `.dark`, so most of the app re-themes automatically. `tailwind.config.js` sets `darkMode: 'class'` to match. The one thing to watch: **plain Tailwind color utilities don't flip** — `bg-white` is a static primitive (`rgb(var(--_white))`, never overridden), while the semantic `bg-bg-white` (`rgb(var(--bg-white))`) is what `dark.css` actually redefines. Every "white card surface" in this app intentionally uses `bg-bg-white`, not `bg-white` — reach for the same semantic tokens (`bg-bg-*`, `text-txt-*`, `border-otl-*`) for anything new that should support dark mode. The preference is stored in `localStorage.theme` and applied before first paint by `partials/theme-init.blade.php` (a blocking inline `<script>` in `<head>`) to avoid a flash of the wrong theme; since `wire:navigate` only swaps `<body>`, the class on `<html>` naturally survives Livewire page navigations.
- **Text size** (`x-font-size-toggle`) — a 3-step "A / A / A" control (WCAG 1.4.4, resize text) that adds `font-size-large` / `font-size-xlarge` to `<html>`, which scale the root `font-size` via plain CSS in `app.css`. Because this app's whole type scale is rem-based (MYDS's `body-sm`, `heading-2xs`, etc.), scaling the root font-size scales everything proportionally — no per-component work needed. Stored in `localStorage.fontSize`, also applied pre-paint by `theme-init.blade.php`.
- **Language** (`x-language-switcher`) — EN/BM links hitting `Route::get('locale/{locale}', ...)`, which stores the choice in the session; `SetLocale` middleware (registered globally in `bootstrap/app.php`) applies it on every request via `App::setLocale()` and syncs `Carbon::setLocale()` too, so relative timestamps ("2 minutes ago") translate along with everything else. These are plain links (not `wire:navigate`) — switching locale needs a full page render since Blade strings outside the clicked component (e.g. the page's own `__('Dashboard')` heading) only re-translate on a fresh server render, not a same-component Livewire update. Translations for every `__('...')` string in the app live in `lang/ms.json`; Laravel falls back to English for anything untranslated (namespaced strings like `auth.password` use `lang/en/*.php`, published via `php artisan lang:publish`, with no `lang/ms/*.php` counterpart — validation error text stays in English in BM mode, which is an acceptable gap for a POC).
- **Notifications** (`livewire:layout.notification-bell`) — real Laravel [database notifications](https://laravel.com/docs/notifications#database-notifications), not placeholder UI. Creating a user in the CRUD module (`UserManagement::save()`) sends the acting admin an `App\Notifications\UserCreated`; the bell shows an unread-count badge and a dropdown (built on the existing `x-dropdown` component) listing recent notifications with `->diffForHumans()` timestamps, click-to-mark-as-read, and "mark all as read". It's a *separate* Livewire component from `UserManagement`, so it wouldn't otherwise know a notification just arrived — it polls every 15s (`wire:poll.15s`) as a baseline, but `UserManagement::save()` also dispatches a `notification-created` browser event that `NotificationBell` listens for (`#[On('notification-created')]`) to refresh immediately instead of waiting on the poll.

## MYDS Integration

MYDS's component library (`packages/react` in the [govtechmy/myds](https://github.com/govtechmy/myds) repo) is **React** — it can't be imported into a Blade/Livewire app. What *is* directly reusable is `packages/style`, published as [`@govtechmy/myds-style`](https://www.npmjs.com/package/@govtechmy/myds-style): MYDS's Tailwind CSS preset and design tokens (color scales, `bg-*`/`txt-*`/`otl-*`/`fr-*` semantic utilities, Inter/Poppins/Roboto Mono type scale, radius, shadows) independent of React.

That package targets **Tailwind CSS v3.4**, so this project is pinned to Tailwind v3 (classic `tailwind.config.js` + PostCSS) rather than Laravel 12's newer v4 default — Breeze's `breeze:install livewire` scaffolds v3 by default, which is what makes this integration possible without extra downgrade work.

Setup, if you're wiring this into a fresh project:

```bash
npm install @govtechmy/myds-style
```

```js
// tailwind.config.js
import mydsPreset from './node_modules/@govtechmy/myds-style/tailwind.config.js';
// note: imported by relative path, not the package specifier — the package's
// package.json "exports" map doesn't expose ./tailwind.config.js as a subpath,
// so a bare `@govtechmy/myds-style/tailwind.config.js` import is rejected by Node.

export default {
    presets: [mydsPreset],
    content: [/* ... */],
};
```

```css
/* resources/css/app.css */
@import '@govtechmy/myds-style/color.css';
@import '@govtechmy/myds-style/light.css';
@import '@govtechmy/myds-style/dark.css';
@import url('https://fonts.googleapis.com/css2?family=Inter:...&family=Poppins:...&display=swap');

@tailwind base;
@tailwind components;
@tailwind utilities;
```

(The package's `full.css` export point is an empty build artifact in the published `1.0.0` version — importing `color.css` + `light.css` + `dark.css` directly, as done here, is the working path. See [Navbar Utilities](#navbar-utilities) for how `dark.css` is actually toggled.)

With the tokens in place, the app's Blade components (Button, Input, Table, Modal, Alert) and layout (Masthead, Navbar, Footer) are hand-built to match MYDS's visual language using those utility classes — MYDS's actual component markup was used as a visual reference, not copied in directly, since it's React JSX.

## Notes

- The repo targets PHP `^8.3` (set in `composer.json`); it also runs fine on newer PHP 8.x.
- The Malaysian government masthead bar at the top of every page is a simplified static version — real MYDS masthead components include an expandable "how to identify an official government site" panel, omitted here to keep the layout simple.
