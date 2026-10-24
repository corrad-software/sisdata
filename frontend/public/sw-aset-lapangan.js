/*
 * SISDATA MOD-AST field app service worker (SRS-AST-20) — same design as MOD-PTN's
 * sw-lapangan.js. Scope: /admin/ast/lapangan.
 * Caches only the app shell (page HTML + Next static assets) so the app opens
 * offline. API responses are NEVER cached here — field data lives encrypted
 * in IndexedDB (lib/ast-offline.ts).
 */
const CACHE = 'ast-lapangan-v1';
const SHELL = '/admin/ast/lapangan';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE)
            .then((c) => fetch(SHELL, { credentials: 'include' }).then((r) => (r.ok ? c.put(SHELL, r) : undefined)))
            .catch(() => undefined)
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((k) => k.startsWith('ast-lapangan-') && k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

function statik(url) {
    return url.pathname.startsWith('/_next/static/') || url.pathname.startsWith('/vendor/') || url.pathname === '/favicon.ico' || url.pathname === '/aset-lapangan.webmanifest';
}

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin || url.pathname.startsWith('/api/') || url.pathname.startsWith('/sanctum/')) return;

    if (req.mode === 'navigate' && url.pathname.startsWith(SHELL)) {
        event.respondWith(
            fetch(req)
                .then((res) => {
                    if (res.ok && !res.redirected) {
                        const copy = res.clone();
                        caches.open(CACHE).then((c) => c.put(SHELL, copy));
                    }
                    return res;
                })
                .catch(() =>
                    caches.match(SHELL).then(
                        (hit) => hit || new Response('<!doctype html><meta charset="utf-8"><title>Luar talian</title><p style="font-family:sans-serif;padding:16px">Aplikasi lapangan belum disimpan pada peranti ini. Buka sekali semasa dalam talian.</p>', { headers: { 'Content-Type': 'text/html; charset=utf-8' } }),
                    ),
                ),
        );
        return;
    }

    if (statik(url)) {
        event.respondWith(
            caches.open(CACHE).then((c) =>
                c.match(req).then((hit) => {
                    const net = fetch(req)
                        .then((res) => {
                            if (res.ok) c.put(req, res.clone());
                            return res;
                        })
                        .catch(() => hit);
                    return hit || net;
                }),
            ),
        );
    }
});
