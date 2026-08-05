import { test, expect } from '@playwright/test';

const ADMIN_EMAIL = process.env.TEST_ADMIN_EMAIL ?? 'admin@example.com';
const ADMIN_PASSWORD = process.env.TEST_ADMIN_PASSWORD ?? 'password';

async function login(page: import('@playwright/test').Page) {
    await page.goto('/login');
    await page.getByLabel('Email').fill(ADMIN_EMAIL);
    await page.getByLabel('Password').fill(ADMIN_PASSWORD);
    await page.getByRole('button', { name: 'Log in' }).click();
}

test.describe('authentication', () => {
    test('logs in with valid credentials and reaches the dashboard', async ({ page }) => {
        await login(page);
        await expect(page).toHaveURL('/admin');
        await expect(page.getByRole('heading', { name: 'Admin' })).toBeVisible();
    });

    test('shows an error on invalid credentials and stays on the login page', async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Email').fill(ADMIN_EMAIL);
        await page.getByLabel('Password').fill('wrong-password');
        await page.getByRole('button', { name: 'Log in' }).click();

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

        await page.getByRole('button', { name: /admin/i }).click();
        await page.getByRole('button', { name: 'Log Out' }).click();

        await expect(page).toHaveURL('/login', { timeout: 5000 });
        await expect(page.getByRole('heading', { name: 'Log in to your account' })).toBeVisible();
    });

    test('unauthenticated visitor is redirected from /admin to /login', async ({ page }) => {
        await page.goto('/admin');
        await expect(page).toHaveURL(/\/login/);
    });
});
