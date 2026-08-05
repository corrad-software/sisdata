# MYDS-Laravel

Laravel API backend + Next.js admin frontend, styled with the Malaysian Government Design System (MYDS). Laravel is a **pure JSON API** — no Blade views render anything user-facing. Next.js is the sole UI. Full picture: [README.md](README.md).

## Running it locally

Two servers, two terminals (or `composer run dev`, which runs both via `concurrently`):

```bash
php artisan serve        # :8000 — API
cd frontend && npm run dev   # :3000 — UI
```

`composer run setup` bootstraps both sides (`.env` files, `key:generate`, `migrate`, `npm install`) for a fresh clone.

## Critical gotchas — read before touching these areas

- **MYDS theming uses a universal `*` selector, not `:root`.** `color.css` defines `--_primary-*` on `*`, so every element gets its own directly-assigned value rather than inheriting from `<html>`. Any runtime color override (Site Settings' theme color) must match that same `*` selector and be placed *after* the compiled stylesheet in `<head>` to win by source order. See `app/Support/ThemeColor.php` (`css()`) and `frontend/app/layout.tsx`.
- **Sanctum SPA auth is cross-origin** (`:3000` → `:8000`). The session cookie *rotates* on logout rather than clearing — cookie-presence alone can't distinguish "logged in" from "just logged out." `frontend/proxy.ts` only does a cheap presence check to gate `/admin/*`; the authoritative check is always `getServerUser()` server-side (see `frontend/app/(dashboard)/layout.tsx` and `frontend/app/(auth)/layout.tsx`). Don't add cookie-presence-based redirects for the guest routes (`/login`, `/register`) — that caused a real infinite redirect loop once.
- **`Setting::allCached()` must return a plain array, not a `Collection`.** Laravel 13's `serializable_classes: false` cache hardening silently breaks unserializing cached objects. If you touch `app/Models/Setting.php`, keep it array-based.

## Frontend conventions

- **Every page's content wrapper is `<PageContainer>`** (`frontend/components/PageContainer.tsx`, default `py-8 px-4 sm:px-6 lg:px-8 space-y-6`). Never hand-roll a `py-*` wrapper div — that's exactly the kind of drift that caused a real bug (Users/Settings pages inherited stale `py-12` from the old Blade port while everything else used `py-8`).
- **Two different tab patterns exist, don't mix them up:**
  - Page-level section nav (e.g. Kitchen Sink's Foundation/Components/Data Visual tabs, the About page's Stack/Changelog tabs) is a **full-width white bar directly under `<PageHeader>`**, outside `PageContainer`. Copy the markup from `frontend/app/(dashboard)/admin/kitchen-sink/layout.tsx` or `frontend/app/(dashboard)/admin/about/page.tsx`.
  - The generic `components/ui/Tabs.tsx` (`Tabs`/`TabPanel`) is for tabs *inside* a card/content area (a Kitchen Sink demo, a form section) — it renders its own underline nav, not a full-bleed bar.
- **`SidebarLink` needs `w-full`** on its root element or the hover/active background only hugs the icon+label instead of spanning the row.
- **Kitchen Sink (`/admin/kitchen-sink/**`) is the component reference library** — check there first before building a new UI primitive. ~40 shared components already exist in `components/ui/`.
- **`LanguageSwitcher` and `NotificationBell` are intentionally UI-only** — local state, no backend, no real i18n or notifications API. This was an explicit scope decision, not an oversight. Same goes for the "Forgot password?" link pattern in the Kitchen Sink auth samples (`href="#"`) — there's no password-reset flow.

## Version constraints — don't blindly bump these

- **Tailwind CSS is pinned to v3.4.x.** `@govtechmy/myds-style` declares `tailwindcss: ^3.4.10` as a peer dependency and has no v4 support. Don't upgrade until MYDS ships v4 compatibility.
- **TypeScript and ESLint are capped by `eslint-config-next`.** As of Next 16.3.0, its bundled `typescript-eslint@8.66.0` hard-requires `TypeScript <6.1.0` and caps at `eslint ^9` — ESLint literally crashes under TS 7 (`typescript-eslint does not support TS 7.0`). Check `eslint-config-next`'s own resolved sub-dependency constraints (`npm ls eslint typescript`) before bumping either.
- When bumping Laravel majors, check the official upgrade guide for cache/serialization changes — that's what broke `Setting::allCached()` last time (see gotchas above).

## Verification — run before considering any change done

Backend (`/`):
```bash
php artisan test
```

Frontend (`frontend/`):
```bash
npx tsc --noEmit
npx eslint . --max-warnings=0
npm run test:e2e   # Playwright — requires both servers running; see frontend/e2e/
```

For anything touching auth, the settings form, or layout/spacing, prefer an actual browser check (Playwright script or manual) over trusting static checks alone — several bugs this project hit (the logout redirect loop, the Reset-to-Default button misalignment) only showed up live, not in `tsc`/`eslint`.

## Scope decisions already made (don't relitigate without asking)

- Profile self-service, a real notifications backend, password reset, and an EN/MS locale switcher were **deliberately dropped**, not forgotten, when Blade was retired — only Kitchen Sink + Site Settings were ported.
- No client-side role-based UI hiding yet: a `role: 'user'` account still sees the Users/Settings nav links even though the API 403s their requests server-side. Known gap, not a bug — see [README's Notes section](README.md#notes).

## Git

Never commit or push without the user explicitly asking in that turn — a prior "yes, commit" does not carry forward to later changes.
