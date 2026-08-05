# MYDS Laravel Starter

A production-shaped foundation for Malaysian government-style applications: a Laravel 12 JSON API backend paired with a Next.js (React) frontend, styled against the [Malaysian Government Design System (MYDS)](https://github.com/govtechmy/myds).

It ships with cookie-based SPA authentication, an admin shell (collapsible sidebar, breadcrumbs, header), a Users CRUD module with role-based authorization, a Site Settings page (site title/footer/theme color/logo/favicon — the theme color regenerates MYDS's full color ramp from a single hex value), and a 30-page "Kitchen Sink" component reference library covering every MYDS foundation token and UI pattern (including 10 hand-rolled SVG chart types) — enough scaffolding to start a real project without re-solving the same setup problems.

## Technology

**Backend**
- Laravel 12 / PHP 8.3+
- SQLite
- Laravel Sanctum (SPA cookie auth)

**Frontend** (`frontend/`)
- Next.js 16 (App Router) / React 19 / TypeScript
- Tailwind CSS 3.4 (pinned, see [MYDS integration](#myds-integration) below)
- [`@govtechmy/myds-style`](https://www.npmjs.com/package/@govtechmy/myds-style) — MYDS's design tokens as a Tailwind preset
- TanStack Query (server state), Zustand (UI state), `sonner` (toasts)

## Installation

Backend:

```bash
git clone <this-repo> myds-laravel
cd myds-laravel

composer install
cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate

php artisan serve   # http://localhost:8000
```

Frontend (in a second terminal):

```bash
cd frontend
npm install
cp .env.example .env.local

npm run dev   # http://localhost:3000
```

Visit `http://localhost:3000`, register an account, and you'll land on the admin dashboard. "Users" is the sample CRUD module, "Settings" is the site configuration page, and "Kitchen Sink" is the component reference library.

Run the backend test suite with:

```bash
php artisan test
```

Run the frontend e2e suite (requires both `php artisan serve` and `npm run dev` reachable — Playwright starts the Next.js dev server for you if it isn't already running, but not the Laravel API):

```bash
cd frontend
npx playwright install chromium   # first run only
npm run test:e2e
```

## Architecture

The Laravel app is a **pure JSON API** — it renders no HTML of its own beyond the default Laravel error pages. `routes/web.php` is intentionally empty; every real route lives in `routes/api.php`. The Next.js app is the only frontend, and is the sole consumer of that API.

**Auth** is Sanctum's SPA (cookie-based) flow, not token auth: the frontend calls `GET /sanctum/csrf-cookie` once, then `POST /api/login`/`/api/register` with the `X-XSRF-TOKEN` header Laravel expects, and every subsequent request rides on the session cookie. In dev, `frontend/next.config.ts` rewrites `/api/*` and `/sanctum/*` to the Laravel server, so the browser only ever talks to `localhost:3000` — same-origin, no CORS configuration needed. In production, put a reverse proxy in front of both apps on the same top-level domain for the same effect (see `SANCTUM_STATEFUL_DOMAINS` / `SESSION_DOMAIN` in `.env`).

**Route protection** is two-layered: `frontend/proxy.ts` (Next.js's `middleware.ts` equivalent) does a cheap cookie-*presence* check to redirect obviously-unauthenticated requests before any rendering happens; the authoritative check is server-side in `app/(dashboard)/layout.tsx`, which forwards the request's cookies to `GET /api/user` and redirects to `/login` if that fails. Admin-only API routes (`users`, and the settings *write* endpoints) are additionally gated by the `admin` middleware alias (`App\Http\Middleware\EnsureUserIsAdmin`), checking `role === 'admin'` — the public settings `GET` stays unauthenticated since the guest/login layout needs it too.

**Site-wide theming**: the root layout (`frontend/app/layout.tsx`) fetches `GET /api/settings` on every request and injects the returned `theme_css` string — `"* { --_primary-50: r g b; ... }"` — into `<head>`, *after* the compiled Tailwind stylesheet so it wins by source order. This matters because MYDS defines its `--_primary-*` variables on a universal `*` selector, not `:root` — every element gets its own directly-assigned value rather than inheriting from `<html>`, so an override has to target the same selector or it silently has no effect except on `<html>` itself. The ramp itself is generated server-side (`app/Support/ThemeColor.php`) from a single hex color via hand-rolled HSL math against a fixed lightness table per shade — kept in Laravel rather than duplicated in TypeScript, so there's one source of truth for the color math.

## Folder Structure

```
app/
├── Http/
│   ├── Controllers/Api/
│   │   ├── AuthController.php     # login / register / logout / user
│   │   ├── UserController.php     # Users CRUD (admin-only)
│   │   └── SettingController.php  # site settings read (public) + write/upload (admin-only)
│   ├── Middleware/
│   │   └── EnsureUserIsAdmin.php  # the "admin" route middleware alias
│   ├── Requests/
│   │   ├── StoreUserRequest.php
│   │   ├── UpdateUserRequest.php
│   │   └── UpdateSettingsRequest.php
│   └── Resources/
│       └── UserResource.php
├── Models/
│   ├── User.php
│   └── Setting.php                # key/value store backing Site Settings, cached via Cache::rememberForever
├── Support/
│   └── ThemeColor.php             # hex -> MYDS color-ramp generation
└── Notifications/
    └── UserCreated.php            # database notification sent when Users CRUD creates a user

database/
├── database.sqlite
└── migrations/                    # users (+role/is_active), settings, notifications, sessions/cache/jobs

routes/
├── api.php                        # every real route lives here
└── web.php                        # intentionally empty — see Architecture above

frontend/
├── app/
│   ├── (auth)/login, register     # guest shell + pages
│   ├── (dashboard)/               # authenticated shell (sidebar, breadcrumbs, header)
│   │   ├── admin/                 # dashboard landing page
│   │   ├── admin/users/           # Users CRUD (TanStack Query)
│   │   ├── admin/settings/        # Site Settings page
│   │   └── admin/kitchen-sink/    # Foundation / Components / Data Visual / Sample Pages (30 pages)
│   ├── layout.tsx                 # root layout: fetches settings, injects theme CSS + title + favicon
│   └── page.tsx                   # auth-check redirect to /admin or /login
├── components/
│   ├── ui/                        # shared primitives: Button, Table, Modal, FieldInput, charts/, ...
│   └── ...                        # Sidebar, DashboardShell, Breadcrumb, Masthead, Footer, ...
├── lib/
│   ├── api.ts                     # client fetch wrapper: CSRF cookie + XSRF header + credentials
│   ├── auth-server.ts             # server-side session check (dashboard layout)
│   └── settings-server.ts         # server-side settings fetch (root layout)
├── store/sidebar.ts                # Zustand store for sidebar collapsed state (persisted)
├── proxy.ts                        # cookie-presence redirect (Next.js 16's middleware.ts equivalent)
└── tailwind.config.ts              # presets: [MYDS tailwind preset], darkMode: 'class'
```

### Reusable components (`frontend/components/ui/`)

The full set mirrors what the Kitchen Sink demonstrates — buttons, form fields, tables, modals, dropdowns, tabs, accordions, badges, toasts (via `sonner`), a calendar, file upload, and ten chart types (bar/line/donut/pie/multi-line/gauge/stacked-bar/horizontal-bar/heatmap/radar), all built directly against MYDS's Tailwind tokens with no external UI or charting library. Browse `/admin/kitchen-sink` in a running app for the full visual reference — every component there is used verbatim from `components/ui/`.

## Database Design

**`users`** (Laravel's default table, extended)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint, PK | |
| `name` | string | |
| `email` | string, unique | |
| `password` | string, hashed | |
| `role` | string, default `user` | `admin` or `user` — gates admin-only API routes via `EnsureUserIsAdmin` |
| `is_active` | boolean, default `true` | drives the Active/Inactive badge in the Users table |
| `remember_token` | string, nullable | |
| `created_at` / `updated_at` | timestamps | |

**`settings`** — plain key/value store (`key` unique, `value` text nullable) backing Site Settings: `site_title`, `footer_text`, `primary_color`, `site_logo`, `site_favicon`. Read through `Setting::get()`/cached via a single `Cache::rememberForever` key, invalidated on any write.

**`sessions`, `cache`, `jobs`** — Laravel 12 defaults, unmodified (needed since `SESSION_DRIVER`/`CACHE_STORE` default to `database`; sessions specifically back Sanctum's SPA auth).

## MYDS Integration

MYDS's component library (`packages/react` in the [govtechmy/myds](https://github.com/govtechmy/myds) repo) is React — directly usable in principle, but this starter hand-builds its own component set against `packages/style` (published as [`@govtechmy/myds-style`](https://www.npmjs.com/package/@govtechmy/myds-style)) instead, for full control over markup and behavior. That package is MYDS's Tailwind CSS preset and design tokens — color scales, `bg-*`/`txt-*`/`otl-*`/`fr-*` semantic utilities, Inter/Poppins/Roboto Mono type scale, radius, shadows — independent of the React components.

That package targets **Tailwind CSS v3.4**, so `frontend/` is pinned to Tailwind v3 (classic `tailwind.config.ts` + PostCSS) rather than `create-next-app`'s current Tailwind v4 default.

Setup, if you're wiring this into a fresh project:

```bash
npm install @govtechmy/myds-style
```

```ts
// tailwind.config.ts
import mydsPreset from './node_modules/@govtechmy/myds-style/tailwind.config.js';
// note: imported by relative path, not the package specifier — the package's
// package.json "exports" map doesn't expose ./tailwind.config.js as a subpath,
// so a bare `@govtechmy/myds-style/tailwind.config.js` import is rejected by Node.

export default {
    presets: [mydsPreset],
    darkMode: 'class',
    content: [/* ... */],
};
```

```css
/* app/globals.css */
@import '@govtechmy/myds-style/color.css';
@import '@govtechmy/myds-style/light.css';
@import '@govtechmy/myds-style/dark.css';
@import url('https://fonts.googleapis.com/css2?family=Inter:...&family=Poppins:...&display=swap');

@tailwind base;
@tailwind components;
@tailwind utilities;
```

(The package's `full.css` export point is an empty build artifact in the published `1.0.0` version — importing `color.css` + `light.css` + `dark.css` directly, as done here, is the working path.)

**A load-bearing detail if you ever touch theming**: MYDS defines `--_primary-*` and friends via a universal `*` selector in `color.css`, not `:root` — meaning every element gets its own directly-assigned value rather than inheriting one from `<html>`. Any runtime color override (like Site Settings' theme color) must match that same `*` selector and be placed *after* the compiled stylesheet in `<head>` to win by source order, or it will silently have no visible effect except on `<html>` itself. `app/Support/ThemeColor.php` (`css()` method) and `frontend/app/layout.tsx` both encode this — see [Architecture](#architecture) above.

## Notes

- The repo targets PHP `^8.3` (set in `composer.json`); it also runs fine on newer PHP 8.x.
- The Malaysian government masthead bar at the top of every page is a simplified static version — real MYDS masthead components include an expandable "how to identify an official government site" panel, omitted here to keep the layout simple.
- No role-based UI hiding exists yet on the frontend (a `role: 'user'` account will see the Users/Settings nav links even though the API correctly 403s their requests) — the authorization boundary is enforced server-side; client-side hiding is a straightforward follow-up if you need it.
