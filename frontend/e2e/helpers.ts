import type { BrowserContext, Page } from '@playwright/test';

export const ADMIN_EMAIL = process.env.TEST_ADMIN_EMAIL ?? 'admin@example.com';
export const ADMIN_PASSWORD = process.env.TEST_ADMIN_PASSWORD ?? 'password';

/**
 * SRS-GEN-08: keep the interactive guide from auto-starting in automated runs.
 * PanduanInteraktif already skips auto-start when navigator.webdriver is set;
 * this localStorage flag makes that explicit (and survives a forced `?panduan=1`).
 */
export async function matikanPanduan(target: Page | BrowserContext): Promise<void> {
    await target.addInitScript(() => {
        try {
            window.localStorage.setItem('sisdata.panduan.mati', '1');
        } catch {
            // storage unavailable on this origin (about:blank) — nothing to do
        }
    });
}

export async function login(page: Page, email = ADMIN_EMAIL, password = ADMIN_PASSWORD): Promise<void> {
    await matikanPanduan(page);
    await page.goto('/login');
    await page.getByLabel('E-mel').fill(email);
    await page.getByLabel('Kata Laluan').fill(password);
    await page.getByRole('button', { name: 'Log Masuk', exact: true }).click();
}
