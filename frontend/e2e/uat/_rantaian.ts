import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { request as pwRequest } from '@playwright/test';
import type { APIRequestContext, APIResponse, Browser } from '@playwright/test';
import { Api, apiLogin, rows, signIn, type Json, type Session, type UserKey } from './helpers';

/**
 * Helpers shared by the c / f / g UAT specs (PTN→ADU→KES chain, district
 * scope, audit chain).
 *
 * RBC caps concurrent sessions per user (`sesi_had_serentak`, default 3) and
 * ends the OLDEST one. Several UAT specs log in as the same demo accounts in
 * parallel, so a long-lived session can be ended mid-scenario (401). `Tahan`
 * signs in again once on a 401 and retries the call.
 */
export class Tahan extends Api {
    constructor(
        ctx: APIRequestContext,
        readonly who: UserKey,
    ) {
        super(ctx);
    }

    override async raw(method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE', p: string, data?: unknown, multipart?: Record<string, unknown>): Promise<APIResponse> {
        const res = await super.raw(method, p, data, multipart);
        if (res.status() !== 401) return res;
        await apiLogin(this.ctx, this.who);
        return super.raw(method, p, data, multipart);
    }

    /** Make sure the session is alive (call right before driving the UI). */
    async segar(): Promise<void> {
        await this.get('/user');
    }
}

export interface SesiTahan extends Session {
    api: Tahan;
}

export async function masuk(browser: Browser, who: UserKey): Promise<SesiTahan> {
    const s = await signIn(browser, who);
    return { ...s, api: new Tahan(s.context.request, who) };
}

export async function apiTahan(who: UserKey): Promise<{ api: Tahan; dispose: () => Promise<void> }> {
    const ctx = await pwRequest.newContext({ baseURL: process.env.UAT_BASE_URL ?? 'http://localhost:3021' });
    await apiLogin(ctx, who);
    return { api: new Tahan(ctx, who), dispose: () => ctx.dispose() };
}

/** Like helpers.approveInstance, but re-login tolerant (see Tahan). */
export async function luluskan(instanceId: string, approvers: UserKey[] = ['siti', 'faizal', 'huda']): Promise<number> {
    const sessions = await Promise.all(approvers.map(async (who) => ({ who, ...(await apiTahan(who)) })));
    let decided = 0;
    try {
        for (let round = 0; round < 10; round++) {
            let progressed = false;
            for (const s of sessions) {
                const mine = rows(await s.api.get('/wfe/tugasan?per_page=100')).filter(
                    (t: Json) => String(t.instans?.id) === String(instanceId) && t.boleh_bertindak && t.jenis !== 'susulan',
                );
                for (const t of mine) {
                    await s.api.post(`/wfe/tugasan/${t.id}/tindakan`, { tindakan: 'lulus', justifikasi: 'Disemak dan diluluskan (UAT).' });
                    decided++;
                    progressed = true;
                }
            }
            if (!progressed) break;
        }
    } finally {
        await Promise.all(sessions.map((s) => s.dispose()));
    }
    return decided;
}

/** 1×1 PNG (site photo stand-in for MOD-DMS / ClamAV). */
export const PNG_1X1 = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64');

/**
 * Run a NON-destructive scheduled artisan job (adu:semak-sla,
 * aud:semak-integriti) against the UAT database. Refuses any DB whose name
 * lacks "uat" (never the dev DB myds_laravel).
 */
export function artisanUat(...args: string[]): string {
    const db = process.env.UAT_DB_DATABASE ?? 'myds_laravel_uat';
    if (!db.includes('uat')) throw new Error(`artisanUat refuses DB ${db}`);
    const env = {
        ...process.env,
        APP_ENV: 'uat',
        DB_DATABASE: db,
        CACHE_STORE: 'array',
        SESSION_DRIVER: 'file',
        QUEUE_CONNECTION: 'sync',
        AUD_SIEM: 'null',
        REDIS_PREFIX: 'sisdata_uat_',
        CACHE_PREFIX: 'sisdata_uat_',
        HORIZON_PREFIX: 'sisdata_uat_horizon:',
        DOKUMEN_S3_BUCKET: process.env.UAT_DOKUMEN_BUCKET ?? 'sisdata-uat',
    };
    return execFileSync('php', ['artisan', ...args], { cwd: path.resolve(__dirname, '../../..'), env, encoding: 'utf8', timeout: 90_000 });
}

/** x/y of the slippy-map tile containing lon/lat at zoom z. */
export function tileOf(lon: number, lat: number, z: number): { x: number; y: number } {
    const n = 2 ** z;
    const x = Math.floor(((lon + 180) / 360) * n);
    const r = (lat * Math.PI) / 180;
    const y = Math.floor(((1 - Math.asinh(Math.tan(r)) / Math.PI) / 2) * n);
    return { x, y };
}

/** Rough centroid of a GeoJSON (Multi)Polygon: mean of the outer-ring vertices. */
export function pusat(geometry: Json): { lon: number; lat: number } {
    const ring: number[][] = geometry.type === 'MultiPolygon' ? geometry.coordinates[0][0] : geometry.coordinates[0];
    const pts = ring.slice(0, -1);
    return { lon: pts.reduce((s, p) => s + p[0], 0) / pts.length, lat: pts.reduce((s, p) => s + p[1], 0) / pts.length };
}
