import { expect, test } from '@playwright/test';
import { signIn } from './helpers';

test('UAT stack: admin signs in and reaches the dashboard', async ({ browser }) => {
    const { page, api, context } = await signIn(browser, 'admin');
    const me = await api.get('/user');
    expect(me.data.email).toBe('admin@example.com');
    await page.goto('/admin');
    await expect(page).toHaveURL(/\/admin/);
    await context.close();
});
