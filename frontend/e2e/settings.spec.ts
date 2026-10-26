import { test, expect } from '@playwright/test';
import { login } from './helpers';

test.beforeEach(async ({ page }) => {
    await login(page);
    await expect(page).toHaveURL('/admin');
});

test.describe('site settings', () => {
    test('loads existing values into the form', async ({ page }) => {
        await page.goto('/admin/settings');
        await expect(page.getByRole('heading', { name: 'Tetapan Laman' })).toBeVisible();
        await expect(page.getByLabel('Tajuk Laman')).not.toHaveValue('');
    });

    test('saving a new site title persists after reload', async ({ page }) => {
        await page.goto('/admin/settings');

        const titleInput = page.getByLabel('Tajuk Laman');
        const original = await titleInput.inputValue();
        const updated = `MYDS-Laravel Test ${Date.now()}`;

        await titleInput.fill(updated);
        await page.getByRole('button', { name: 'Simpan Tetapan' }).click();
        await expect(page.getByText('Tetapan berjaya disimpan.')).toBeVisible();

        await page.reload();
        await expect(page.getByLabel('Tajuk Laman')).toHaveValue(updated);

        // Restore the original value so the run is idempotent.
        await page.getByLabel('Tajuk Laman').fill(original);
        await page.getByRole('button', { name: 'Simpan Tetapan' }).click();
        await expect(page.getByText('Tetapan berjaya disimpan.')).toBeVisible();
    });

    // Regression test: the "Reset to Default" button used to render below the
    // input's help text instead of level with the input box, because
    // sm:items-end aligned to the bottom of the whole (taller) field column.
    // See app/(dashboard)/admin/settings/page.tsx.
    test('footer Reset to Default button is vertically aligned with its input', async ({ page }) => {
        await page.goto('/admin/settings');

        const footerInput = page.getByLabel('Teks Pengaki');
        const resetButton = page.getByRole('button', { name: 'Tetap Semula ke Lalai' }).first();

        const inputBox = await footerInput.boundingBox();
        const buttonBox = await resetButton.boundingBox();

        expect(inputBox).not.toBeNull();
        expect(buttonBox).not.toBeNull();

        // The two bottom edges should be close (within a few px), not offset
        // by a full line of help text (~16-20px).
        const bottomDelta = Math.abs((inputBox!.y + inputBox!.height) - (buttonBox!.y + buttonBox!.height));
        expect(bottomDelta).toBeLessThan(8);
    });
});
