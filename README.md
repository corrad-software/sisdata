# MYDS Laravel Starter

A fast-prototyping foundation for Malaysian government-style applications, built with Laravel 12, Livewire, Blade, and Tailwind CSS, styled against the [Malaysian Government Design System (MYDS)](https://github.com/govtechmy/myds).

It ships with authentication, a MYDS-styled dashboard shell, five reusable UI components, and a sample User Management CRUD module — enough scaffolding to start a real project without re-solving the same setup problems.

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

Visit `http://localhost:8000`, register an account, and you'll land on the dashboard. From there, "User Management" in the nav bar opens the sample CRUD module.

Run the test suite with:

```bash
php artisan test
```

## Folder Structure

```
app/
├── Http/Requests/
│   ├── StoreUserRequest.php       # validation rules for creating a user
│   └── UpdateUserRequest.php      # validation rules for updating a user
├── Livewire/
│   ├── Auth/, Layout/, Profile/   # Breeze-generated auth & profile components
│   └── UserManagement.php         # sample CRUD module (list/search/create/edit/delete)
└── Models/
    └── User.php

database/
├── database.sqlite
└── migrations/                    # includes add_role_and_is_active_to_users_table

resources/
├── css/app.css                    # imports MYDS tokens + Tailwind directives
├── views/
│   ├── components/                # reusable Blade components (see below)
│   ├── layouts/
│   │   ├── app.blade.php          # dashboard shell: masthead, navbar, content, footer
│   │   └── guest.blade.php        # auth-page shell
│   ├── livewire/
│   │   ├── layout/navigation.blade.php
│   │   ├── pages/auth/            # Breeze Livewire auth screens
│   │   ├── profile/
│   │   └── user-management.blade.php
│   └── dashboard.blade.php

routes/
├── web.php                        # includes the /users route
└── auth.php                       # Breeze auth routes

tailwind.config.js                 # presets: [MYDS tailwind preset]
```

### Reusable components (`resources/views/components/`)

| Component | File | Notes |
|---|---|---|
| Button | `button.blade.php` | `<x-button variant="primary\|secondary\|danger\|outline" size="sm\|md\|lg">`. Renders `<a>` when given an `href`. Breeze's `primary-button`/`secondary-button`/`danger-button` are thin wrappers around it, so every button in the app shares one style source. |
| Form Input | `input.blade.php` | `<x-input label="..." name="..." error="..." help="...">` — label, input, help text, and validation error in one call. Breeze's lower-level `text-input`/`input-label`/`input-error` are restyled to match but kept separate, since Breeze's auth views wire them up individually. |
| Table | `table.blade.php` | `<x-table><x-slot name="head">...</x-slot>...rows...</x-table>` — MYDS-styled wrapper around a standard HTML table. |
| Modal | `modal.blade.php` | Breeze's Alpine-based modal (focus trap, ESC to close, backdrop), restyled to MYDS. Opened/closed via the `open-modal` / `close-modal` browser events (`$dispatch('open-modal', 'name')`), used by both the CRUD module and Breeze's "delete account" flow. |
| Alert | `alert.blade.php` | `<x-alert type="success\|error\|warning\|info" dismissible>`. `auth-session-status.blade.php` delegates to it, so login/registration status messages use the same component. |

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

**The User Management CRUD is Livewire-powered**, not a classic resource controller:

- The route (`routes/web.php`) points directly at the Livewire component: `Route::get('users', UserManagement::class)`. The full-page Livewire component *is* the controller layer here — that's the standard Livewire convention, and adding a near-empty `UserController` on top of it would just be redundant indirection.
- `StoreUserRequest` / `UpdateUserRequest` still exist as the single source of truth for validation rules, and `UserManagement::save()` calls into them (`(new StoreUserRequest)->rules()`) instead of duplicating validation logic — Form Requests reused outside the HTTP lifecycle.
- Model + Migration are standard Eloquent.
- Modals for create/edit/delete are the same reusable `x-modal` component described above.

If you need a second CRUD module that *should* go through classic Controllers (e.g. because it's consumed by an API too), that pattern isn't used here but fits naturally alongside this one — a resource controller + Form Request + Blade views work the same way this app already validates and models data.

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
@import url('https://fonts.googleapis.com/css2?family=Inter:...&family=Poppins:...&display=swap');

@tailwind base;
@tailwind components;
@tailwind utilities;
```

(The package's `full.css` export point is an empty build artifact in the published `1.0.0` version — importing `color.css` + `light.css` directly, as done here, is the working path. Dark mode's `dark.css` theme file exists in the package but isn't wired up here, to keep things simple.)

With the tokens in place, the app's Blade components (Button, Input, Table, Modal, Alert) and layout (Masthead, Navbar, Footer) are hand-built to match MYDS's visual language using those utility classes — MYDS's actual component markup was used as a visual reference, not copied in directly, since it's React JSX.

## Notes

- The repo targets PHP `^8.3` (set in `composer.json`); it also runs fine on newer PHP 8.x.
- The Malaysian government masthead bar at the top of every page is a simplified static version — real MYDS masthead components include an expandable "how to identify an official government site" panel, omitted here to keep the layout simple.
