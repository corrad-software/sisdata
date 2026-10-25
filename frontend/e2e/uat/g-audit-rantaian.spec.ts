import { expect, test } from '@playwright/test';
import { rows, uniq, type Json } from './helpers';
import { apiTahan, artisanUat, luluskan, masuk, type Tahan } from './_rantaian';

/**
 * UAT g — Jejak audit merentas modul dan integriti rantaian (MOD-AUD).
 *
 * Siti Aminah (pegawai daerah Petaling) menerima aduan kaunter tentang longgokan sisa binaan di sebuah
 * lot MAIS (MOD-ADU, identiti pengadu disimpan Sulit), mengemas kini lokasi
 * aduan, dan merekod isu pemantauan pada lot yang sama (MOD-PTN). Dia cuba
 * melihat identiti pengadu tetapi tiada kebenaran (paparan sensitif DITOLAK);
 * pentadbir pula mendedahkannya dengan tujuan (paparan sensitif BERJAYA).
 * Siti memohon penutupan aduan → MOD-WFE → diluluskan oleh pelulus lain →
 * aduan ditutup. Juruaudit (pentadbir) kemudian menyemak kronologi audit bagi
 * setiap rekod: setiap tindakan tercatat dengan modul, jenis tindakan,
 * pengguna dan id_korelasi = X-Request-Id permintaan asal, dan semakan
 * integriti rantaian cincangan (API + kerja berjadual aud:semak-integriti)
 * melaporkan rantaian utuh. Siti sendiri (tiada aud.rekod.lihat) tidak boleh membaca jejak audit.
 *
 * SRS: AUD-01/02/03/05/07/09/12/18, ADU-01/06/16 (privasi pengadu), PTN-13, WFE-xx (keputusan diaudit).
 */

/** Call through `raw` and return [body, X-Request-Id]. */
async function panggil(api: Tahan, method: 'POST' | 'PUT' | 'GET', path: string, data?: unknown, expected = [200, 201]): Promise<[Json, string]> {
    const res = await api.raw(method, path, data);
    const text = await res.text();
    expect(expected, `${method} ${path} → ${res.status()}: ${text.slice(0, 400)}`).toContain(res.status());
    const rid = res.headers()['x-request-id'];
    expect(rid, `X-Request-Id on ${path}`).toBeTruthy();
    return [text ? JSON.parse(text) : {}, rid];
}

test.describe('UAT g: rantaian tindakan ADU/PTN/WFE + paparan sensitif → kronologi AUD + integriti', () => {
    test('setiap tindakan muncul dalam kronologi audit dengan modul/tindakan/id_korelasi betul dan rantaian utuh', async ({ browser }) => {
        test.setTimeout(240_000);
        const tag = uniq('G');
        // Actor: siti (district officer). The adu.penutupan approver group (adu_penyelia) is faizal,
        // so the requester must be someone else for segregation of duties to leave an approver.
        const pegawai = await apiTahan('siti');
        const fa = pegawai.api;
        const me = (await fa.get('/user')).data;
        const auditor = await masuk(browser, 'admin');
        const ad = auditor.api;

        const lots = rows(await fa.get("/ptn/lot?per_page=100"));
        expect(lots.length, "lots in siti scope").toBeGreaterThan(0);
        const lot = lots[Math.floor(Math.random() * lots.length)];

        // --- 1. MOD-ADU: counter complaint with an identified complainant.
        const [created, ridCipta] = await panggil(fa, 'POST', '/adu/aduan', {
            saluran_penerimaan: 'kaunter',
            kategori: 'PEMBUANGAN_SAMPAH',
            keterangan: `Longgokan sisa binaan di tepi lot MAIS — aduan kaunter ${tag}`,
            lot_id: lot.lot_id,
            pengadu: { nama: `Pengadu ${tag}`, no_telefon: '012-3456789' },
        });
        const aduan = created.data;
        // --- 2. MOD-ADU update.
        const lokasi = `Tepi longkang, pintu masuk lot (${tag})`;
        const [, ridKemas] = await panggil(fa, 'PUT', `/adu/aduan/${aduan.id}`, { lokasi_teks: lokasi, tahap_keutamaan: 'tinggi' });
        // --- 3. MOD-PTN: monitoring issue on the same lot.
        const [isuRes, ridIsu] = await panggil(fa, 'POST', '/ptn/isu', {
            lot_id: lot.lot_id,
            kategori_isu: 'pembuangan_sampah',
            tahap_keterukan: 'sederhana',
            keterangan: `Isu dikenal pasti daripada aduan ${aduan.no_rujukan_aduan} (${tag})`,
        });
        const isu = isuRes.data;
        // --- 4. Sensitive view: refused for siti (no adu.sulit.lihat), granted to the admin with a purpose.
        const [, ridDitolak] = await panggil(fa, 'POST', `/adu/aduan/${aduan.id}/pengadu/dedah`, { tujuan: 'Hubungi pengadu untuk lawatan tapak' }, [403]);
        const [dedah, ridDedah] = await panggil(ad, 'POST', `/adu/aduan/${aduan.id}/pengadu/dedah`, { tujuan: `Semakan juruaudit ${tag}` });
        expect(dedah.data.nama).toBe(`Pengadu ${tag}`);
        // --- 5. MOD-WFE: closure request → approvals (requester excluded by SoD) → complaint closed.
        const [tutup] = await panggil(fa, 'POST', `/adu/aduan/${aduan.id}/penutupan`, {
            kategori_resolusi: 'selesai_tindakan',
            keterangan_resolusi: 'Sisa binaan telah dialihkan oleh kontraktor.',
            justifikasi: 'Disahkan melalui lawatan tapak bergambar.',
        });
        const instans: string = tutup.data.id_instans_aliran;
        expect(tutup.data.status).toBe('menunggu_kelulusan');
        expect(await luluskan(instans, ['faizal', 'huda'])).toBeGreaterThan(0);
        expect((await ad.get(`/wfe/instans/${instans}`)).data.status).toBe('lulus');
        expect((await fa.get(`/adu/aduan/${aduan.id}`)).data.status).toBe('ditutup');

        // --- AUD chronology of the complaint: each action, right module/action/user/correlation id.
        const kron = rows(await ad.get(`/aud/kronologi?nama_entiti=aduan&id_rekod=${aduan.id}`));
        const cari = (pred: (r: Json) => boolean, label: string) => {
            const r = kron.find(pred);
            expect(r, `audit: ${label}`).toBeTruthy();
            return r;
        };
        const rCipta = cari((r) => r.jenis_tindakan === 'cipta' && r.id_korelasi === ridCipta, 'aduan cipta');
        expect(rCipta.modul).toBe('adu');
        expect(rCipta.id_pengguna).toBe(me.id);
        expect(rCipta.status_transaksi).toBe('berjaya');
        const rKemas = cari((r) => r.jenis_tindakan === 'kemas_kini' && r.id_korelasi === ridKemas, 'aduan kemas_kini');
        expect((rKemas.perubahan as Json[]).some((p) => p.nama_medan === 'lokasi_teks' && String(p.nilai_baharu).includes(tag))).toBe(true);
        const rTolak = cari((r) => r.jenis_tindakan === 'lihat' && r.id_korelasi === ridDitolak, 'refused sensitive view');
        expect(rTolak.status_transaksi).toBe('gagal');
        expect(rTolak.id_pengguna).toBe(me.id);
        // Closure decided via WFE: the complaint's status change to "ditutup" is audited too.
        cari((r) => r.jenis_tindakan === 'kemas_kini' && (r.perubahan as Json[]).some((p: Json) => p.nama_medan === 'status' && p.nilai_baharu === 'ditutup'), 'status → ditutup');
        // Chronology is in chain order.
        const seq = kron.map((r: Json) => Number(r.no_urutan));
        expect([...seq].sort((a, b) => a - b)).toEqual(seq);

        // Granted sensitive view (entity pengadu, by the admin, Sulit).
        const dedahRows = rows(await ad.get(`/aud/rekod?modul=adu&nama_entiti=pengadu&id_pengguna=1&jenis_tindakan=lihat&per_page=50`));
        const rDedah = dedahRows.find((r: Json) => r.id_korelasi === ridDedah);
        expect(rDedah, 'audit: granted reveal').toBeTruthy();
        expect(rDedah.status_transaksi).toBe('berjaya');
        expect(rDedah.sebab_perubahan).toContain(tag);

        // MOD-PTN issue.
        const isuKron = rows(await ad.get(`/aud/kronologi?nama_entiti=isu_pemantauan&id_rekod=${isu.id}`));
        const rIsu = isuKron.find((r: Json) => r.jenis_tindakan === 'cipta' && r.id_korelasi === ridIsu);
        expect(rIsu, 'audit: ptn isu cipta').toBeTruthy();
        expect(rIsu.modul).toBe('ptn');

        // MOD-WFE decisions on the instance: explicit "lulus" actions by approvers other than the requester.
        const wfKron = rows(await ad.get(`/aud/kronologi?nama_entiti=wf_instans&id_rekod=${instans}`));
        const lulus = wfKron.filter((r: Json) => r.jenis_tindakan === 'lulus');
        expect(lulus.length).toBeGreaterThan(0);
        expect(lulus.every((r: Json) => r.modul === 'wfe' && r.id_pengguna !== me.id && r.meta?.kod_aliran === 'adu.penutupan')).toBe(true);

        // Search by user: siti's trail today spans adu + ptn.
        const today = new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Kuala_Lumpur' });
        const byUser = rows(await ad.get(`/aud/rekod?id_pengguna=${me.id}&dari=${today}&per_page=200`));
        for (const rid of [ridCipta, ridKemas, ridIsu, ridDitolak]) {
            expect(byUser.some((r: Json) => r.id_korelasi === rid), `user search finds ${rid}`).toBe(true);
        }

        // --- Integrity: API check (SRS-AUD-09) + scheduled job.
        const semak = (await ad.post('/aud/integriti/semak')).data;
        expect(semak.status).toBe('lulus');
        expect(Number(semak.last)).toBeGreaterThanOrEqual(Math.max(...seq));
        expect(semak.bad ?? null).toBeNull();
        expect(semak.checked).toBeGreaterThan(0);
        // Scheduled job (exits non-zero → execFileSync throws if the chain is broken).
        expect(artisanUat('aud:semak-integriti')).toMatch(/Integriti audit trail sah: \d+ rekod disemak/);
        const semakan = (await ad.get('/aud/integriti')).data.semakan as Json[];
        expect(semakan.find((s) => s.id === semak.id)?.status).toBe('lulus');
        expect(semakan.find((s) => s.dicetuskan_oleh === 'jadual')?.status).toBe('lulus');

        // --- Access: siti has no aud.* permission → cannot read the trail of her own actions.
        expect(await fa.status('GET', `/aud/kronologi?nama_entiti=aduan&id_rekod=${aduan.id}`)).toBe(403);
        expect(await fa.status('GET', '/aud/rekod')).toBe(403);

        // --- UI: auditor searches the trail by record id, then opens the chronology and integrity pages.
        const page = auditor.page;
        await ad.segar();
        await page.goto('/admin/aud');
        await page.getByLabel('ID rekod').fill(aduan.id);
        await page.getByRole('button', { name: 'Cari', exact: true }).click();
        await expect(page.getByText(/\d+ rekod dijumpai/)).toBeVisible({ timeout: 30_000 });
        await expect(page.getByText(`#${aduan.id}`).first()).toBeVisible();
        await expect(page.getByText(me.email).first()).toBeVisible();

        await ad.segar();
        await page.goto(`/admin/aud/kronologi?nama_entiti=aduan&id_rekod=${aduan.id}`);
        await expect(page.getByText(me.email).first()).toBeVisible({ timeout: 30_000 });

        await ad.segar();
        await page.goto('/admin/aud/integriti');
        await expect(page.getByRole('heading', { name: 'Semakan integriti' })).toBeVisible({ timeout: 30_000 });
        await expect(page.getByText(/Rantaian cincangan audit trail sah pada semakan terakhir/)).toBeVisible();
        await expect(page.getByRole('columnheader', { name: 'No. urutan terakhir' })).toBeVisible();

        await auditor.context.close();
        await pegawai.dispose();
    });
});
