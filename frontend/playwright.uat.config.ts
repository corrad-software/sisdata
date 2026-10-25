import { defineConfig, devices } from '@playwright/test';

/**
 * Cross-module UAT scenarios (frontend/e2e/uat). They WRITE data, so they must
 * run against a dedicated UAT stack, never the dev servers on :3000/:8000:
 *
 *   API  : APP_ENV=uat DB_DATABASE=myds_laravel_uat … php artisan serve --no-reload --port=8021
 *   UI   : INTERNAL_API_URL=http://127.0.0.1:8021 FRONTEND_URL=http://localhost:3021 next start -p 3021
 *   data : php artisan sisdata:demo-seed --pintas-mfa-uat   (on the empty UAT DB)
 *
 *   UAT_BASE_URL=http://localhost:3021 npm run test:uat
 *
 * global-setup refuses to start unless the API reports APP_ENV=uat.
 * See docs/uat/senario-uat.md and CLAUDE.md "Running it locally".
 */
export default defineConfig({
    testDir: './e2e/uat',
    globalSetup: './e2e/uat/global-setup.ts',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 120_000,
    expect: { timeout: 15_000 },
    reporter: [['list']],
    use: {
        baseURL: process.env.UAT_BASE_URL ?? 'http://localhost:3021',
        trace: 'retain-on-failure',
        locale: 'ms-MY',
        timezoneId: 'Asia/Kuala_Lumpur',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
