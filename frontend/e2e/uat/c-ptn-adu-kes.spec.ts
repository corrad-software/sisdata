import { randomUUID } from 'node:crypto';
import { expect, test } from '@playwright/test';
import { rows, uniq, type Json } from './helpers';
import { apiTahan, artisanUat, masuk, PNG_1X1 } from './_rantaian';

/**
 * UAT c — Pemantauan tanah → aduan → kes mahkamah.
 *
 * Pegawai lapangan (Mohd Faizal) melawat sebuah lot MAIS dan mendapati lot
 * bersebelahan telah mengalihkan pagar ke dalam tanah MAIS (pencerobohan).
 * Laporan lawatan yang dihantar membuka isu pemantauan dan, kerana kategori
 * pencerobohan, terus membuka SATU aduan dalam MOD-ADU (saluran "ptn") dengan
 * SLA berjalan. Kerja berjadual adu:semak-sla dijalankan; pegawai kemudian
 * mengeskalasi aduan secara manual kepada penyelia, dan isu itu dinaikkan ke
 * Bahagian Perundangan sebagai kes MOD-KES yang dipautkan kepada lot.
 * Rekod kes ialah Rahsia: Siti (tiada kebenaran kes.rahsia.lihat) ditolak dan
 * penolakan itu dilog; Faizal (peranan Rahsia demo) melihatnya. MFA KES
 * dipintas di UAT melalui kes.mfa_wajib=false (setara SSO MAIS).
 *
 * SRS: PTN-05/06/13/17/18, ADU-01/12/13/14, KES-01/13/14, RBC-08.
 */
test.describe('UAT c: PTN lawatan → ADU aduan + SLA/eskalasi → KES kes perundangan', () => {
    test('penemuan pencerobohan membuka aduan, dieskalasi, dan dinaikkan ke kes mahkamah Rahsia', async ({ browser }) => {
        test.setTimeout(240_000);
        const tag = uniq('C');
        const faizal = await masuk(browser, 'faizal');
        const fa = faizal.api;

        // KES MFA is the documented UAT bypass (kes.mfa_wajib=false), not switched off silently.
        const admin = await apiTahan('admin');
        const params = (await admin.api.get('/adm/parameter?q=kes.mfa_wajib')).data.flatMap((g: Json) => g.parameter);
        expect(params.find((p: Json) => p.kunci === 'kes.mfa_wajib')?.nilai, 'kes.mfa_wajib (UAT bypass)').toBe(false);
        await admin.dispose();

        // --- 1. Pick a lot (random page so parallel runs don't pile on one lot).
        const first = await fa.get('/ptn/lot?per_page=50');
        const page = 1 + Math.floor(Math.random() * Math.max(1, Math.min(first.meta.last_page, 8)));
        const lots = rows(await fa.get(`/ptn/lot?per_page=50&page=${page}`));
        const lot = lots[Math.floor(Math.random() * lots.length)];
        expect(lot?.lot_id).toBeTruthy();

        // --- 2. Field visit (SRS-PTN-05): ad-hoc visit, checklist, photo, submit.
        const visit = (await fa.post('/ptn/lapangan/lawatan', { id_luar_talian: randomUUID(), lot_id: lot.lot_id, sumber: 'web' })).data;
        expect(visit.status_lawatan).toBe('draf');
        const detail = (await fa.get(`/ptn/lawatan/${visit.id}`)).data;
        const kods: string[] = (detail.templat?.senarai_item ?? []).map((i: Json) => i.kod);
        expect(kods.length, 'checklist template items').toBeGreaterThan(0);
        // The template's encroachment item, else any item (category given explicitly).
        const cer = (detail.templat.senarai_item as Json[]).find((i) => i.kategori_isu === 'pencerobohan')?.kod ?? kods[0];
        const catatan = `Pagar lot bersebelahan dianjak ±5 m ke dalam lot MAIS (${tag})`;
        await fa.put(`/ptn/lapangan/lawatan/${visit.id}`, {
            keadaan_tapak: 'buruk',
            ringkasan_penemuan: `Pencerobohan sempadan dikesan semasa lawatan ${tag}.`,
            isu_pencerobohan: catatan,
            item: kods.map((k) => (k === cer ? { kod_item: k, status_item: 'isu', kategori_isu: 'pencerobohan', tahap_keterukan: 'tinggi', catatan } : { kod_item: k, status_item: 'baik' })),
        });
        const photo = await fa.raw('POST', `/ptn/lapangan/lawatan/${visit.id}/gambar`, undefined, {
            fail: { name: `tapak-${tag}.png`, mimeType: 'image/png', buffer: PNG_1X1 },
            id_luar_talian: randomUUID(),
            sumber_gambar: 'kamera',
            kod_item: cer,
        });
        expect(photo.status(), await photo.text()).toBe(201);
        const sent = await fa.post(`/ptn/lapangan/lawatan/${visit.id}/hantar`);
        expect(sent.data.status_lawatan).toBe('dihantar');

        // --- 3. PTN → ADU (SRS-PTN-13/17): the finding became an issue and ONE complaint (channel ptn).
        const after = (await fa.get(`/ptn/lawatan/${visit.id}`)).data;
        const isu = (after.isu as Json[]).find((i) => i.kod_item === cer);
        expect(isu, 'monitoring issue from the visit').toBeTruthy();
        expect(isu.kategori_isu).toBe('pencerobohan');
        expect(isu.id_aduan_berkaitan, 'complaint opened automatically').toBeTruthy();
        expect(after.laporan?.[0]?.id_aliran, 'visit report sent for WFE verification').toBeTruthy();
        const aduanId: string = isu.id_aduan_berkaitan;

        const aduan = (await fa.get(`/adu/aduan/${aduanId}`)).data;
        expect(aduan.saluran_penerimaan).toBe('ptn');
        expect(aduan.lot_id).toBe(lot.lot_id);
        expect(aduan.kategori).toBe('PENCEROBOHAN');
        expect(aduan.keterangan).toContain(isu.no_rujukan);
        // SLA tracked from intake (SRS-ADU-12/13).
        expect(aduan.sla?.kitaran).toBe(1);
        expect(aduan.sla.status_sla).toBe('dalam_tempoh');
        expect(new Date(aduan.sla.masa_sasaran_selesai).getTime()).toBeGreaterThan(new Date(aduan.sla.masa_mula).getTime());
        // Idempotent: escalating the same issue again returns the same complaint.
        expect((await fa.post(`/ptn/isu/${isu.id}/eskalasi-aduan`)).data.id_aduan).toBe(aduanId);

        // --- 4. SLA job (SRS-ADU-14). A complaint opened now is still within SLA, so the
        // scheduled job checks it and leaves it "dalam_tempoh" with no automatic escalation
        // (working-time SLA cannot be time-travelled over the API).
        const out = artisanUat('adu:semak-sla');
        expect(out).toMatch(/Aduan disemak: \d+/);
        const checked = (await fa.get(`/adu/aduan/${aduanId}`)).data;
        expect(checked.sla.status_sla).toBe('dalam_tempoh');
        expect((checked.eskalasi as Json[]).filter((e) => e.jenis === 'automatik')).toHaveLength(0);

        // Manual escalation to the supervisor (SRS-ADU-14 manual path) — recorded on the complaint + chronology.
        const sebab = `Pencerobohan aktif — perlu tindakan segera penguatkuasaan (${tag})`;
        await fa.post(`/adu/aduan/${aduanId}/eskalasi`, { sebab });
        const escalated = (await fa.get(`/adu/aduan/${aduanId}`)).data;
        expect((escalated.eskalasi as Json[]).some((e) => e.jenis === 'manual' && e.sebab === sebab)).toBe(true);
        const kron = rows(await fa.get(`/adu/aduan/${aduanId}/kronologi`));
        expect(kron.map((k: Json) => k.jenis_tindakan)).toEqual(expect.arrayContaining(['terima', 'triaj', 'eskalasi']));

        // --- 5. PTN → KES (SRS-PTN-18 via LegalCaseIntake): case registered, lot linked.
        const kesId: string = (await fa.post(`/ptn/isu/${isu.id}/eskalasi-kes`)).data.id_kes;
        expect(kesId).toBeTruthy();
        expect((await fa.post(`/ptn/isu/${isu.id}/eskalasi-kes`)).data.id_kes, 'idempotent').toBe(kesId);
        expect((await fa.get(`/ptn/isu/${isu.id}`)).data.id_kes_berkaitan).toBe(kesId);

        const kes = (await fa.get(`/kes/kes/${kesId}`)).data;
        expect(kes.status).toBe('didaftar');
        expect(kes.sumber).toBe('ptn');
        expect(kes.jenis).toBe('pencerobohan');
        expect(kes.tajuk).toContain(isu.no_rujukan);
        expect((kes.lot as Json[]).map((l) => l.lot_id)).toContain(lot.lot_id);
        expect((kes.kronologi as Json[]).some((k) => String(k.tajuk).includes(`ptn_isu:${isu.id}`))).toBe(true);
        // In faizal's case register filtered by the lot.
        expect(rows(await fa.get(`/kes/kes?lot_id=${lot.lot_id}`)).map((k: Json) => k.id)).toContain(kesId);

        // --- 6. Rahsia gate (SRS-KES-13/14): siti has kes.kes.lihat but not kes.rahsia.lihat → refused + logged.
        const siti = await masuk(browser, 'siti');
        const refused = await siti.api.raw('GET', `/kes/kes/${kesId}`);
        expect(refused.status()).toBe(403);
        expect((await refused.json()).code).toBe('FORBIDDEN');
        expect(await siti.api.status('GET', '/kes/kes')).toBe(403);
        const log = rows(await fa.get(`/kes/log-capaian?kes_id=${kesId}&jenis_tindakan=ditolak`));
        expect(log.some((l: Json) => l.id_pengguna === 3 || /Siti/i.test(String(l.pengguna)))).toBe(true);

        // --- UI: the chain as the officers see it.
        const p = faizal.page;
        await fa.segar();
        await p.goto(`/admin/ptn/isu/${isu.id}`);
        await expect(p.getByText(isu.no_rujukan).first()).toBeVisible({ timeout: 30_000 });

        await fa.segar();
        await p.goto(`/admin/adu/aduan/${aduanId}`);
        await expect(p.getByText(aduan.no_rujukan_aduan).first()).toBeVisible({ timeout: 30_000 });
        await p.getByRole('button', { name: 'Rujukan & eskalasi', exact: true }).click();
        await expect(p.getByText(sebab).first()).toBeVisible();

        await fa.segar();
        await p.goto(`/admin/kes/kes/${kesId}`);
        await expect(p.getByText(kes.no_rujukan).first()).toBeVisible({ timeout: 30_000 });
        await expect(p.getByText(String(kes.lot[0].no_lot)).first()).toBeVisible();

        await siti.api.segar();
        await siti.page.goto(`/admin/kes/kes/${kesId}`);
        await expect(siti.page.getByText(/tidak mempunyai kebenaran Bahagian Perundangan/).first()).toBeVisible({ timeout: 30_000 });
        await expect(siti.page.getByText(kes.no_rujukan)).toHaveCount(0);

        await siti.context.close();
        await faizal.context.close();
    });
});
