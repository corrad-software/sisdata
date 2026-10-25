import { expect, test } from '@playwright/test';
import { apiAs, rows, signIn, uniq } from './helpers';
import type { Json } from './helpers';

/*
 * UAT h — Butang "Bantuan" dari skrin modul → cadangan KBA → tiket sokongan.
 *
 * Cerita MAIS: Pegawai daerah Petaling (Siti) sedang di senarai kontrak
 * MOD-PJK dan gagal menjana penyata akaun penyewa. Dia menekan "Bantuan" di
 * pengepala; panel bantuan dibuka di atas skrin (kerja tidak hilang) dan
 * konteks skrin ditangkap secara automatik. Semasa menaip, MOD-KBA
 * mencadangkan artikel / jawapan berkaitan. Masalah belum selesai, jadi dia
 * menghantar tiket; MOD-TKT menyimpan konteks (modul, skrin, kod fungsi,
 * URL), mengira sasaran SLA respons & penyelesaian, dan tiket muncul dalam
 * senarai /admin/tkt/tiket (pelapor) serta barisan sokongan (pentadbir).
 *
 * SRS: TKT-01, TKT-02, TKT-05, TKT-06, TKT-08 (SLA), TKT-20, KBA (carian).
 */

test.describe('UAT h: TKT Bantuan dari skrin modul dengan cadangan KBA', () => {
    test('pegawai membuka tiket dari skrin kontrak PJK; konteks & SLA disimpan dan tiket tersenarai', async ({ browser }) => {
        const { context, page, api } = await signIn(browser, 'siti');
        const ref = uniq('TKTH');
        const tajuk = `Penyata akaun penyewa gagal dijana ${ref}`;

        // 1. On a module screen (MOD-PJK contract list), open the help panel.
        await page.goto('/admin/pjk/kontrak');
        await expect(page).toHaveURL(/\/admin\/pjk\/kontrak/);
        await page.getByRole('button', { name: 'Bantuan' }).click();
        const panel = page.getByRole('dialog', { name: /Bantuan & tiket sokongan/ });
        await expect(panel).toBeVisible();
        await expect(panel.getByRole('link', { name: /Panduan bagi skrin ini/ })).toHaveAttribute('href', /laluan=%2Fadmin%2Fpjk%2Fkontrak/);

        // 2. Type the issue → KBA suggestions before submitting (≥ 3, open in a new tab).
        await panel.getByLabel('Tajuk masalah').fill(tajuk);
        await panel.getByLabel('Penerangan').fill('Saya tidak dapat menjana penyata akaun penyewa bagi kontrak sewaan; butang eksport PDF penyata tidak memberi sebarang respons.');
        const cadangan = panel.locator('section[aria-live="polite"]').getByRole('link');
        await expect(cadangan.first()).toBeVisible({ timeout: 30_000 });
        expect(await cadangan.count()).toBeGreaterThanOrEqual(3);
        await expect(cadangan.first()).toHaveAttribute('href', /^\/admin\/kba\//);
        await expect(cadangan.first()).toHaveAttribute('target', '_blank');
        await expect(panel.getByRole('button', { name: 'Artikel ini menyelesaikan masalah saya' })).toBeVisible();

        // 3. The automatically captured context is shown to the user.
        await panel.getByRole('button', { name: /Maklumat yang ditangkap secara automatik/ }).click();
        await expect(panel.getByText('admin.pjk.kontrak')).toBeVisible();

        // 4. Submit the ticket.
        await panel.getByRole('button', { name: 'Hantar tiket' }).click();
        const pautan = panel.getByRole('link', { name: /^Lihat tiket / });
        await expect(pautan).toBeVisible({ timeout: 30_000 });
        const nombor = ((await pautan.textContent()) ?? '').replace('Lihat tiket', '').trim();
        expect(nombor).not.toBe('');
        await expect(panel.getByText(`Tiket ${nombor} telah dibuka`)).toBeVisible();
        const tiketId = ((await pautan.getAttribute('href')) ?? '').split('/').pop() ?? '';
        expect(tiketId).not.toBe('');

        // 5. Stored context (modul / skrin / kod fungsi) and SLA targets (API, as the reporter).
        const t: Json = (await api.get(`/tkt/tiket/${tiketId}`)).data;
        expect(t.nombor_rujukan).toBe(nombor);
        expect(t.tajuk).toBe(tajuk);
        expect(t.modul).toBe('pjk');
        expect(t.konteks.modul).toBe('pjk');
        expect(t.konteks.kod_fungsi).toBe('admin.pjk.kontrak');
        expect(t.konteks.url).toContain('/admin/pjk/kontrak');
        expect(t.konteks.skrin).toBeTruthy();
        expect(t.konteks.peranan_pengguna).toEqual(expect.arrayContaining(['pegawai_operasi']));
        expect(t.sla.respons.sasaran, 'sasaran SLA respons').toBeTruthy();
        expect(t.sla.selesai.sasaran, 'sasaran SLA penyelesaian').toBeTruthy();
        expect(new Date(t.sla.selesai.sasaran).getTime()).toBeGreaterThanOrEqual(new Date(t.sla.respons.sasaran).getTime());
        expect(t.sla.respons.status).toBe('dalam_tempoh');

        // The support queue (admin) sees it too, with the same context.
        const admin = await apiAs('admin');
        try {
            const q = rows(await admin.api.get(`/tkt/tiket?q=${encodeURIComponent(nombor)}`));
            expect(q.map((r: Json) => r.nombor_rujukan)).toContain(nombor);
            expect((await admin.api.get(`/tkt/tiket/${tiketId}`)).data.konteks.kod_fungsi).toBe('admin.pjk.kontrak');
        } finally {
            await admin.dispose();
        }

        // 6. UI (reporter): the ticket page with its SLA, and the ticket in "Tiket saya" at /admin/tkt/tiket.
        await pautan.click();
        await expect(page).toHaveURL(new RegExp(`/admin/tkt/tiket/${tiketId}`));
        await expect(page.getByText(tajuk).first()).toBeVisible({ timeout: 30_000 });
        await expect(page.getByText(/SLA/).first()).toBeVisible();
        await page.goto('/admin/tkt/tiket');
        await page.getByPlaceholder('No. tiket atau tajuk').fill(nombor);
        await expect(page.getByRole('row').filter({ hasText: nombor })).toBeVisible({ timeout: 30_000 });

        // 7. UI (support management): the ticket is in the support queue and its page shows the captured context.
        const sokongan = await signIn(browser, 'admin');
        try {
            await sokongan.page.goto('/admin/tkt/tiket');
            await expect(sokongan.page.getByText('Barisan tiket sokongan')).toBeVisible({ timeout: 30_000 });
            await sokongan.page.getByPlaceholder('No. tiket atau tajuk').fill(nombor);
            await expect(sokongan.page.getByRole('row').filter({ hasText: nombor })).toBeVisible({ timeout: 30_000 });
            await sokongan.page.goto(`/admin/tkt/tiket/${tiketId}`);
            await expect(sokongan.page.getByText('Konteks yang ditangkap')).toBeVisible({ timeout: 30_000 });
            await expect(sokongan.page.getByText(t.konteks.skrin).first()).toBeVisible();
        } finally {
            await sokongan.context.close();
        }

        await context.close();
    });
});
