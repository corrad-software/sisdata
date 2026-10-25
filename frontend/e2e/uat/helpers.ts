import { expect, request as pwRequest } from '@playwright/test';
import type { APIRequestContext, APIResponse, Browser, BrowserContext, Page } from '@playwright/test';

/**
 * Shared helpers for the cross-module UAT scenarios. API calls go through the
 * Next.js origin (/api/v1 is rewritten to the UAT API) with the same Sanctum
 * SPA session the browser uses, so a scenario can set data up over the API
 * and then assert in the UI as that same user.
 */

export const BASE_URL = process.env.UAT_BASE_URL ?? 'http://localhost:3021';

/** Demo accounts created by `php artisan sisdata:demo-seed` (see app/Console/Commands/SisdataDemoSeed.php). */
export const USERS = {
    admin: { email: process.env.UAT_ADMIN_EMAIL ?? 'admin@example.com', password: process.env.UAT_ADMIN_PASSWORD ?? 'password' },
    /** Pegawai daerah Petaling — pegawai_operasi + pelulus, skop daerah 1008. */
    siti: { email: 'siti.aminah@mais.test', password: process.env.UAT_DEMO_PASSWORD ?? 'Sisdata@123' },
    /** Pegawai HQ / Wakaf — pegawai_operasi + pelulus + pegawai perundangan (demo); no district scope. */
    faizal: { email: 'mohd.faizal@mais.test', password: process.env.UAT_DEMO_PASSWORD ?? 'Sisdata@123' },
    /** Pengurusan — pengguna_paparan + pelulus. */
    huda: { email: 'nurul.huda@mais.test', password: process.env.UAT_DEMO_PASSWORD ?? 'Sisdata@123' },
} as const;

export type UserKey = keyof typeof USERS;

/** Unique suffix so every spec run creates its own data (specs stay independent and re-runnable). */
export function uniq(prefix = 'UAT'): string {
    return `${prefix}-${Date.now().toString(36)}-${Math.floor(Math.random() * 1e4)}`;
}

export class Api {
    constructor(readonly ctx: APIRequestContext) {}

    private async xsrf(): Promise<string> {
        let token = (await this.ctx.storageState()).cookies.find((c) => c.name === 'XSRF-TOKEN')?.value;
        if (!token) {
            await this.ctx.get('/sanctum/csrf-cookie');
            token = (await this.ctx.storageState()).cookies.find((c) => c.name === 'XSRF-TOKEN')?.value;
        }
        return decodeURIComponent(token ?? '');
    }

    private async headers(): Promise<Record<string, string>> {
        return { Accept: 'application/json', Referer: `${BASE_URL}/admin`, Origin: BASE_URL, 'X-XSRF-TOKEN': await this.xsrf() };
    }

    async raw(method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE', path: string, data?: unknown, multipart?: Record<string, unknown>): Promise<APIResponse> {
        const url = path.startsWith('/') && !path.startsWith('/api/') && !path.startsWith('/sanctum') ? `/api/v1${path}` : path;
        const headers = await this.headers();
        const opts: Record<string, unknown> = { headers, failOnStatusCode: false };
        if (multipart) opts.multipart = multipart;
        else if (data !== undefined) opts.data = data;
        return this.ctx.fetch(url, { method, ...opts });
    }

    /** Call and assert a 2xx; returns the parsed JSON body. */
    async call<T = Json>(method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE', path: string, data?: unknown, multipart?: Record<string, unknown>): Promise<T> {
        const res = await this.raw(method, path, data, multipart);
        const text = await res.text();
        if (!res.ok()) {
            throw new Error(`${method} ${path} → ${res.status()}: ${text.slice(0, 800)}`);
        }
        return (text ? JSON.parse(text) : {}) as T;
    }

    get<T = Json>(path: string) {
        return this.call<T>('GET', path);
    }

    post<T = Json>(path: string, data?: unknown) {
        return this.call<T>('POST', path, data ?? {});
    }

    put<T = Json>(path: string, data?: unknown) {
        return this.call<T>('PUT', path, data ?? {});
    }

    patch<T = Json>(path: string, data?: unknown) {
        return this.call<T>('PATCH', path, data ?? {});
    }

    async status(method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE', path: string, data?: unknown): Promise<number> {
        return (await this.raw(method, path, data)).status();
    }
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type Json = any;

/** Password login over the API (AUTH_PASSWORD_LOGIN is on in UAT). */
export async function apiLogin(ctx: APIRequestContext, who: UserKey): Promise<Api> {
    const api = new Api(ctx);
    const res = await api.raw('POST', '/login', { email: USERS[who].email, password: USERS[who].password });
    expect(res.status(), `login ${USERS[who].email}: ${await res.text()}`).toBe(200);
    return api;
}

export interface Session {
    context: BrowserContext;
    page: Page;
    api: Api;
}

/** A fresh, isolated browser context signed in as `who` (UI and API share the session). */
export async function signIn(browser: Browser, who: UserKey): Promise<Session> {
    const context = await browser.newContext({ baseURL: BASE_URL, locale: 'ms-MY', timezoneId: 'Asia/Kuala_Lumpur' });
    const api = await apiLogin(context.request, who);
    const page = await context.newPage();
    return { context, page, api };
}

/** Standalone API session (no browser) — for approvers and setup steps. */
export async function apiAs(who: UserKey): Promise<{ api: Api; dispose: () => Promise<void> }> {
    const ctx = await pwRequest.newContext({ baseURL: BASE_URL });
    const api = await apiLogin(ctx, who);
    // Log out on dispose: RBC caps concurrent sessions per user (rbc.sesi_had_serentak), so
    // abandoned sessions would evict the browser sessions of other specs.
    return {
        api,
        dispose: async () => {
            await api.raw('POST', '/logout').catch(() => undefined);
            await ctx.dispose();
        },
    };
}

/** Rows of a list response, whatever its envelope ({data: [...]}, {data: {data: [...]}}, or a bare array). */
export function rows(body: Json): Json[] {
    if (Array.isArray(body)) return body;
    if (Array.isArray(body?.data)) return body.data;
    if (Array.isArray(body?.data?.data)) return body.data.data;
    if (Array.isArray(body?.items)) return body.items;
    return [];
}

/** Every open task in the user's WFE inbox (pages through it — demo approvers hold hundreds of sample tasks). */
export async function openTasks(api: Api): Promise<Json[]> {
    const all: Json[] = [];
    for (let page = 1; page <= 50; page++) {
        const body = await api.get(`/wfe/tugasan?per_page=100&page=${page}`);
        all.push(...rows(body));
        if (page >= (body?.meta?.last_page ?? 1)) break;
    }
    return all;
}

/**
 * Approve every open WFE task of workflow instance `instanceId`, stage by
 * stage, as whichever demo approver holds it (segregation of duties: the
 * requester is never among them). Returns the number of decisions made.
 */
export async function approveInstance(instanceId: string | number, approvers: UserKey[] = ['siti', 'faizal', 'huda'], action: 'lulus' | 'tolak' = 'lulus', justification = 'Disemak dan diluluskan (UAT).'): Promise<number> {
    const sessions = await Promise.all(approvers.map(async (who) => ({ who, ...(await apiAs(who)) })));
    let decided = 0;
    try {
        for (let round = 0; round < 10; round++) {
            let progressed = false;
            for (const s of sessions) {
                const mine = (await openTasks(s.api)).filter(
                    (t: Json) => String(t.instans?.id) === String(instanceId) && t.boleh_bertindak && t.jenis !== 'susulan',
                );
                for (const t of mine) {
                    await s.api.post(`/wfe/tugasan/${t.id}/tindakan`, { tindakan: action, justifikasi: justification });
                    decided++;
                    progressed = true;
                    if (action === 'tolak') return decided;
                }
            }
            if (!progressed) break;
        }
    } finally {
        await Promise.all(sessions.map((s) => s.dispose()));
    }
    return decided;
}

/** Status of a workflow instance as seen by the admin (wfe.pemantauan.lihat). */
export async function instanceStatus(api: Api, instanceId: string | number): Promise<string> {
    return (await api.get(`/wfe/instans/${instanceId}`)).data?.status;
}

/** Poll until `fn` returns a truthy value (for async side effects like listeners / sync queue). */
export async function eventually<T>(fn: () => Promise<T | null | undefined | false>, label: string, timeoutMs = 20_000): Promise<T> {
    const until = Date.now() + timeoutMs;
    let last: unknown;
    while (Date.now() < until) {
        try {
            const v = await fn();
            if (v) return v as T;
        } catch (e) {
            last = e;
        }
        await new Promise((r) => setTimeout(r, 500));
    }
    throw new Error(`Timed out waiting for ${label}${last ? `: ${String(last)}` : ''}`);
}
