# MYDS-Laravel

Laravel API backend + Next.js admin frontend, styled with the Malaysian Government Design System (MYDS). Laravel is a **pure JSON API** — no Blade views render anything user-facing. Next.js is the sole UI. Full picture: [README.md](README.md).

**This repo is being built into SISDATA** (MAIS land/property system, 22 modules, 572 SRS requirements). The dev plan, phases and open SRS issues are in [docs/PLAN-SISDATA.md](docs/PLAN-SISDATA.md); F0 (groundwork) and G0 (platform modules RBC, AUD, ADM, NTF, DMS, WFE + Ref reference data) are done. Cross-module contracts are in `app/Modules/*/Contracts`; `app/Support/Fallbacks` stands in when a module is disabled. Parallel-developer brief: [docs/dev/developer-brief.md](docs/dev/developer-brief.md).

## Running it locally

`composer run dev` starts the backing services (`infra/bin/sisdata-services.sh start`), then API (:8000), Horizon, Pail and the UI (:3000) via `concurrently`.

Backing services are **native (Homebrew), no Docker** — the user's choice. `infra/bin/sisdata-services.sh start|stop|status` manages them:

| Service | Port | Notes |
| --- | --- | --- |
| PostgreSQL 17 + PostGIS 3.6 + pgvector + pg_trgm | 5432 | DBs `myds_laravel` (dev) and `myds_laravel_test` (PHPUnit). PostGIS isn't a trusted extension → `infra/bin/sisdata-db-setup.sh` creates extensions as superuser |
| Redis | 6379 | cache + queue (Horizon). PHP has no `redis` ext → `REDIS_CLIENT=predis` |
| Mailpit | 1025 / UI 8025 | all outgoing mail |
| MinIO | 9000 / console 9001 | `dokumen` disk, bucket via `php artisan sisdata:bucket` |
| ClamAV clamd | socket `/opt/homebrew/var/run/clamav/clamd.sock` | signatures: `freshclam` |
| GeoServer | 8085 | stand-in for SuperMap |
| Keycloak 26 ("SSO MAIS", realm `mais`) | 8180 | `infra/bin/keycloak-dev.sh`; realm in `infra/keycloak/realm-mais.json`; test users in that file |

`composer run setup` bootstraps a fresh clone.

**Demo data:** on an empty, migrated DB run `php artisan sisdata:demo-seed` (~3 min, `app/Console/Commands/SisdataDemoSeed.php`). It runs, in dependency order, `DatabaseSeeder` (RBC base roles, `admin@example.com` / `password`), `ref:seed`, `pro:seed-tadbir`, `WfeSeeder` (creates the Keycloak demo users), `ref:sample-lots` (500 synthetic lots) → `pro:import-staging` → `hkm:import-staging`, `G1AliranSeeder`, `HkmAliranSeeder`, `per:sample-cases`, then each `<Mod>AliranSeeder` + `<mod>:sample` for ADU, PJK, CKI, PTN, KES, TSU, AST, TKT, `PwsSeeder`, flows `dms.pelupusan` + `kba.terbit_artikel`, `ptn:kira-risiko`, `lap:analitik`, `kba:index-baseline` (needs Ollama; skip with `--tanpa-kba`). Every WFE approver group gets demo members, and the demo users get roles/scopes matching their SSO claims plus a local password `Sisdata@123` (password login only works with `AUTH_PASSWORD_LOGIN=true`): `siti.aminah@mais.test` (pegawai operasi + pelulus, district 1008 Petaling), `mohd.faizal@mais.test` (pegawai operasi + pelulus + demo KES Rahsia role), `nurul.huda@mais.test` (paparan + pelulus). It refuses `APP_ENV=production` and a DB that already has lots (unless `--force`).

**UAT stack** (own DB + ports; never point it at `myds_laravel` or :3000/:8000): create `myds_laravel_uat` like `infra/bin/sisdata-db-setup.sh` does, then with `APP_ENV=uat DB_DATABASE=myds_laravel_uat CACHE_STORE=file SESSION_DRIVER=file SESSION_COOKIE=sisdata_uat_session QUEUE_CONNECTION=sync AUD_SIEM=null REDIS_PREFIX=sisdata_uat_ FRONTEND_URL=http://localhost:3021 SANCTUM_STATEFUL_DOMAINS=localhost:3021 AUTH_PASSWORD_LOGIN=true` exported (`CACHE_STORE=file`, not `array`: the mock FPX/GRP/MBI/Portal Wakaf adapters keep state in the cache between requests): `php artisan migrate --force && php artisan sisdata:demo-seed --pintas-mfa-uat`, `php artisan serve --no-reload --port=8021`, and in `frontend/` `INTERNAL_API_URL=http://127.0.0.1:8021 FRONTEND_URL=http://localhost:3021 SESSION_COOKIE_NAME=sisdata_uat_session npx next build && npx next start -p 3021` (a second `next dev` can't share `.next/dev` with the lead's; `next build` leaves `.next/dev` alone — delete the build output afterwards). Cross-module scenarios: `UAT_BASE_URL=http://localhost:3021 npm run test:uat` (`frontend/e2e/uat/`, refuses unless the API reports `APP_ENV=uat`; the default `npm run test:e2e` ignores them). Tester script: `docs/uat/senario-uat.md`; production checklist: `docs/kesediaan-produksi.md`.

**UAT MFA bypass:** the UAT host isn't registered with SSO MAIS, so `--pintas-mfa-uat` (refused unless `APP_ENV=uat`) sets two critical parameters: `wfe.mfa_pintas_uat=true` (WFE step-up treated as fresh — `StepUp::uatBypass()` ignores it in every env except `uat`) and `kes.mfa_wajib=false` (KES's existing switch). Never set either in production.

## Critical gotchas — read before touching these areas

- **MYDS theming uses a universal `*` selector, not `:root`.** `color.css` defines `--_primary-*` on `*`, so every element gets its own directly-assigned value rather than inheriting from `<html>`. Any runtime color override (Site Settings' theme color) must match that same `*` selector and be placed *after* the compiled stylesheet in `<head>` to win by source order. See `app/Support/ThemeColor.php` (`css()`) and `frontend/app/layout.tsx`.
- **Sanctum SPA auth is cross-origin** (`:3000` → `:8000`). The session cookie *rotates* on logout rather than clearing — cookie-presence alone can't distinguish "logged in" from "just logged out." `frontend/proxy.ts` only does a cheap presence check to gate `/admin/*`; the authoritative check is always `getServerUser()` server-side (see `frontend/app/(dashboard)/layout.tsx` and `frontend/app/(auth)/layout.tsx`). Don't add cookie-presence-based redirects for the guest routes (`/login`, `/register`) — that caused a real infinite redirect loop once.
- **`Setting::allCached()` must return a plain array, not a `Collection`.** Laravel 13's `serializable_classes: false` cache hardening silently breaks unserializing cached objects. If you touch `app/Models/Setting.php`, keep it array-based.

## Backend conventions (SISDATA)

- **API prefix is `/api/v1`** (`apiPrefix` in `bootstrap/app.php`). Route names (`api.login` …) are unchanged.
- **Modules live in `app/Modules/<Name>/`**, each with a `<Name>ServiceProvider` extending `App\Support\Modules\ModuleServiceProvider` and listed in `config/modules.php`. `routes.php` mounts at `/api/v1/<code>`; migrations and `lang/` load automatically. Copy `app/Modules/Platform` to start one. A module may only reference another module's `Contracts\` or `Events\` — `tests/Architecture/ModuleBoundaryTest` fails otherwise.
- **Errors:** every `/api` error goes through `App\Support\ApiErrorRenderer` → `{message, code, request_id, errors?}`; no internals unless `APP_DEBUG`. Every response carries `X-Request-Id` (`AssignRequestId` middleware, also in log `Context`).
- **Language is Malay** (`APP_LOCALE=ms`). Write source strings in English with `__()` and add the Malay to `lang/ms.json` / `lang/ms/*.php`.
- **Tests are named after SRS codes** (`test_SRS_ADM_12_…`) so `php artisan srs:rtm` can build `docs/RTM.md`. Pint's `php_unit_method_casing` is disabled for that reason — don't re-enable it.
- **Login:** SSO MAIS via OIDC (`App\Http\Controllers\Auth\OidcController`, `/auth/oidc/*`, proxied by Next's `/auth/:path*` rewrite). Password login stays behind `AUTH_PASSWORD_LOGIN`.
- **Authorisation is MOD-RBC:** permissions `<modul>.<fungsi>.<tindakan>` declared via `PermissionRegistry`, checked with `can:` / `$user->can()` (Gate → `AccessControl`). Record scoping: `ScopedByAccess` trait. Sulit/Rahsia keys are never granted by wildcard base-role rules.
- **Tests refuse to run on a DB without `_test` in its name** (`tests/TestCase.php`) — the shared dev DB was wiped twice before that guard.

## Frontend conventions

- **Every page's content wrapper is `<PageContainer>`** (`frontend/components/PageContainer.tsx`, default `py-8 px-4 sm:px-6 lg:px-8 space-y-6`). Never hand-roll a `py-*` wrapper div — that's exactly the kind of drift that caused a real bug (Users/Settings pages inherited stale `py-12` from the old Blade port while everything else used `py-8`).
- **Two different tab patterns exist, don't mix them up:**
  - Page-level section nav (e.g. Kitchen Sink's Foundation/Components/Data Visual tabs, the About page's Stack/Changelog tabs) is a **full-width white bar directly under `<PageHeader>`**, outside `PageContainer`. Copy the markup from `frontend/app/(dashboard)/admin/kitchen-sink/layout.tsx` or `frontend/app/(dashboard)/admin/about/page.tsx`.
  - The generic `components/ui/Tabs.tsx` (`Tabs`/`TabPanel`) is for tabs *inside* a card/content area (a Kitchen Sink demo, a form section) — it renders its own underline nav, not a full-bleed bar.
- **`SidebarLink` needs `w-full`** on its root element or the hover/active background only hugs the icon+label instead of spanning the row.
- **Kitchen Sink (`/admin/kitchen-sink/**`) is the component reference library** — check there first before building a new UI primitive. ~40 shared components already exist in `components/ui/`.
- **`LanguageSwitcher` is intentionally UI-only** — no real i18n switch. `NotificationBell` is now real (MOD-NTF: `/api/v1/ntf/*`, polls for unread count); modules send notifications only through `App\Modules\Ntf\Contracts\Notifier`. Same goes for the "Forgot password?" link pattern in the Kitchen Sink auth samples (`href="#"`) — there's no password-reset flow.

## Version constraints — don't blindly bump these

- **Tailwind CSS is pinned to v3.4.x.** `@govtechmy/myds-style` declares `tailwindcss: ^3.4.10` as a peer dependency and has no v4 support. Don't upgrade until MYDS ships v4 compatibility.
- **TypeScript and ESLint are capped by `eslint-config-next`.** As of Next 16.3.0, its bundled `typescript-eslint@8.66.0` hard-requires `TypeScript <6.1.0` and caps at `eslint ^9` — ESLint literally crashes under TS 7 (`typescript-eslint does not support TS 7.0`). Check `eslint-config-next`'s own resolved sub-dependency constraints (`npm ls eslint typescript`) before bumping either.
- When bumping Laravel majors, check the official upgrade guide for cache/serialization changes — that's what broke `Setting::allCached()` last time (see gotchas above).

## Verification — run before considering any change done

Backend (`/`):
```bash
composer lint      # Pint + Larastan (level 5)
php artisan test   # Postgres myds_laravel_test, never the dev DB
```

Frontend (`frontend/`):
```bash
npx tsc --noEmit
npx eslint . --max-warnings=0
npm run test:e2e   # Playwright — requires both servers running; see frontend/e2e/
```

For anything touching auth, the settings form, or layout/spacing, prefer an actual browser check (Playwright script or manual) over trusting static checks alone — several bugs this project hit (the logout redirect loop, the Reset-to-Default button misalignment) only showed up live, not in `tsc`/`eslint`.

## Scope decisions already made (don't relitigate without asking)

- Profile self-service, password reset, and an EN/MS locale switcher were **deliberately dropped** when Blade was retired. A real notifications backend was dropped too, but SISDATA's MOD-NTF (G0) will build one — that's planned, not relitigation.
- Sidebar links are now hidden by permission (`user.permissions` from the API). The API still enforces access itself; never rely on the UI hiding.

## Git

Never commit or push without the user explicitly asking in that turn — a prior "yes, commit" does not carry forward to later changes.
