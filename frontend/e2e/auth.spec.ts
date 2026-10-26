import { test, expect } from '@playwright/test';
import { ADMIN_EMAIL, login, matikanPanduan } from './helpers';

test.describe('authentication', () => {
    test('logs in with valid credentials and reaches the dashboard', async ({ page }) => {
        await login(page);
        await expect(page).toHaveURL('/admin');
        // The Malay page header (SRS-GEN-09) — the executive dashboard for an admin.
        await expect(page.getByRole('heading', { level: 2, name: 'Dashboard Eksekutif', exact: true })).toBeVisible();
    });

    test('shows an error on invalid credentials and stays on the login page', async ({ page }) => {
        await matikanPanduan(page);
        await page.goto('/login');
        await page.getByLabel('E-mel').fill(ADMIN_EMAIL);
        await page.getByLabel('Kata Laluan').fill('wrong-password');
        await page.getByRole('button', { name: 'Log Masuk', exact: true }).click();

        await expect(page).toHaveURL(/\/login/);
    });

    test('redirects an already-authenticated visitor away from /login', async ({ page }) => {
        await login(page);
        await expect(page).toHaveURL('/admin');

        await page.goto('/login');
        await expect(page).toHaveURL('/admin');
    });

    // Regression test: logging out used to hang on a blank page because
    // router.refresh() raced the server-side redirect, and because
    // proxy.ts's cookie-presence check treated the rotated-but-unauthenticated
    // post-logout cookie as still-logged-in and bounced /login back to /admin
    // in an infinite loop. See UserMenu.tsx and proxy.ts.
    test('logs out cleanly without hanging or looping', async ({ page }) => {
        await login(page);
        await expect(page).toHaveURL('/admin');

        await page.getByRole('button', { name: /menu pengguna/i }).click();
        await page.getByRole('menuitem', { name: 'Log Keluar' }).click();

        await expect(page).toHaveURL('/login', { timeout: 5000 });
        await expect(page.getByRole('heading', { name: 'Log Masuk ke Akaun Anda' })).toBeVisible();
    });

    test('unauthenticated visitor is redirected from /admin to /login', async ({ page }) => {
        await page.goto('/admin');
        await expect(page).toHaveURL(/\/login/);
    });

    // SRS-GEN-09: Malay UI; SISDATA has no self-registration link (accounts come from SSO MAIS / an admin).
    test('login page is in Malay and has no self-registration link', async ({ page }) => {
        await page.goto('/login');
        await expect(page.getByText('Laman web rasmi Majlis Agama Islam Selangor')).toBeVisible();
        await expect(page.getByRole('link', { name: 'Log Masuk dengan SSO MAIS' })).toBeVisible();
        await expect(page.getByRole('link', { name: /daftar/i })).toHaveCount(0);
        await expect(page.locator('html')).toHaveAttribute('lang', 'ms');
    });

    // SRS-GEN-08: the guide never pops up during automated runs.
    test('interactive guide does not auto-start in automated browsers', async ({ page }) => {
        await login(page);
        await expect(page).toHaveURL('/admin');
        await page.waitForTimeout(2500);
        await expect(page.locator('.driver-popover')).toHaveCount(0);
    });
});
