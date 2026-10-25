import { request } from '@playwright/test';
import { apiLogin, BASE_URL } from './helpers';

/**
 * Safety net: the UAT scenarios create and approve real records, so refuse to
 * run unless the API behind UAT_BASE_URL reports APP_ENV=uat (never the dev
 * stack on :3000/:8000 with the lead's demo data).
 */
export default async function globalSetup(): Promise<void> {
    const ctx = await request.newContext({ baseURL: BASE_URL });
    try {
        const api = await apiLogin(ctx, 'admin');
        const status = await api.get('/platform/status');
        const env = status?.data?.environment;
        if (env !== 'uat') {
            throw new Error(`UAT specs refuse to run: ${BASE_URL} reports APP_ENV=${env} (expected "uat"). See playwright.uat.config.ts.`);
        }
    } finally {
        await ctx.dispose();
    }
}
