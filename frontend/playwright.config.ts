import { defineConfig, devices } from '@playwright/test';

// Requires the Laravel API (php artisan serve, :8000) to already be running —
// Playwright only manages the Next.js dev server here, not the API backend.
export default defineConfig({
    testDir: './e2e',
    // UAT scenarios write data — they run only via playwright.uat.config.ts against the UAT stack.
    testIgnore: ['uat/**'],
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    reporter: 'list',

    use: {
        baseURL: 'http://localhost:3000',
        trace: 'on-first-retry',
    },

    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],

    webServer: {
        command: 'npm run dev',
        url: 'http://localhost:3000',
        reuseExistingServer: true,
        timeout: 30_000,
    },
});
