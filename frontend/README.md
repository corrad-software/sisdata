Next.js (App Router) frontend for the MYDS Laravel starter kit. Talks to the Laravel app in the repo root as a JSON API over Sanctum's cookie-based SPA auth.

## Setup

```bash
npm install
cp .env.example .env.local
```

## Develop

Run alongside the Laravel API (`php artisan serve`, port 8000, from the repo root):

```bash
npm run dev
```

Requests to `/api/*` and `/sanctum/*` are rewritten to the Laravel server (see `next.config.ts`), so the browser only ever talks to `localhost:3000` — no CORS configuration needed in dev.

## Structure

- `app/(auth)` — login/register pages, guest shell layout
- `app/(dashboard)` — authenticated admin shell (sidebar, breadcrumbs) and pages
- `lib/api.ts` — client-side fetch wrapper (CSRF cookie + `X-XSRF-TOKEN` header)
- `lib/auth-server.ts` — server-side session check for the dashboard layout
- `proxy.ts` — cheap cookie-presence redirect (Next.js 16's `middleware.ts` equivalent); the authoritative auth check happens in `app/(dashboard)/layout.tsx`
- `store/sidebar.ts` — Zustand store for sidebar collapsed state (persisted)

Design tokens come from the same `@govtechmy/myds-style` Tailwind v3 preset used by the root Laravel app's Blade views — see `tailwind.config.ts` and `app/globals.css`.
