import { expect, test } from '@playwright/test';
import { eventually, instanceStatus, rows, signIn, uniq } from './helpers';
import type { Api, Json } from './helpers';
import { luluskanInstans } from './_bdh';

/*
 * UAT b — Pajakan baharu, bayaran FPX dan hasil di Dashboard.
 *
 * Cerita MAIS: Unit Sewaan mendaftarkan premis baharu di atas lot MAIS yang
 * belum ada premis (MOD-PRO → MOD-PJK), mendaftar penyewa syarikat dan
 * menyediakan perjanjian sewaan. Perjanjian dihantar untuk kelulusan berlapis
 * dalam MOD-WFE (pjk.kelulusan_kontrak: semakan Unit Sewaan → Ketua Bahagian);
 * selepas lulus, listener WorkflowCompleted mengaktifkan kontrak, premis jadi
 * "disewa" dan jadual caj dijana. Penyewa membayar sewa bulan pertama melalui
 * FPX (gateway olok-olok, panggilan balik bertandatangan); lejar dikreditkan
 * sekali sahaja, resit bernombor siri dikeluarkan dan KPI hasil MOD-DSH naik.
 *
 * SRS: PJK-01, PJK-02, PJK-03, PJK-05, PJK-11, PJK-12, PJK-14, PJK-15,
 *      PJK-16, PJK-17, WFE-20, DSH-04 (KPI hasil).
 */

const KADAR = 1250;
const DEPOSIT = 2500;

/** Today / +1 year − 1 day in Malaysia time (Y-m-d). */
function tempoh(): { mula: string; tamat: string } {
    const fmt = (d: Date) => d.toLocaleDateString('en-CA', { timeZone: 'Asia/Kuala_Lumpur' });
    const now = new Date();
    const end = new Date(now.getTime());
    end.setFullYear(end.getFullYear() + 1);
    end.setDate(end.getDate() - 1);
    return { mula: fmt(now), tamat: fmt(end) };
}

/** A lot (any district) that has no premise registered on it yet. */
async function lotTanpaPremis(api: Api): Promise<Json> {
    for (let i = 0; i < 30; i++) {
        const q = String(Math.floor(Math.random() * 90) + 10);
        const lots = rows(await api.get(`/pjk/lot?q=${q}`)).sort(() => Math.random() - 0.5);
        for (const lot of lots) {
            if (lot.status_lot !== 'aktif') continue;
            const premis = await api.get(`/pjk/premis?lot_id=${lot.id}`);
            if ((premis.meta?.total ?? rows(premis).length) === 0) return lot;
        }
    }
    throw new Error('Tiada lot tanpa premis ditemui.');
}

/** The "hasil sewaan" slice (RM) of the DSH revenue-vs-cost KPI, computed fresh (segar=1 bypasses the KPI cache). */
async function hasilSewaan(api: Api): Promise<number> {
    const body = await api.get('/dsh/kpi?kunci=dsh.hasil_kos&segar=1');
    const kpi = rows(body).find((r: Json) => r.key === 'dsh.hasil_kos');
    expect(kpi, 'KPI dsh.hasil_kos tersedia untuk pentadbir').toBeTruthy();
    expect(kpi.ralat ?? false).toBeFalsy();
    const slice = (kpi.pecahan ?? []).find((p: Json) => p.kod === 'hasil');
    expect(slice, 'pecahan hasil sewaan').toBeTruthy();
    return Number(slice.nilai);
}

test.describe('UAT b: PJK pajakan baharu → kelulusan WFE → bayaran FPX → lejar, resit & KPI DSH', () => {
    test('premis baharu atas lot, kontrak diluluskan berlapis, sewa dibayar melalui FPX dan hasil DSH bertambah', async ({ browser }) => {
        const { context, page, api } = await signIn(browser, 'admin');
        const ref = uniq('PJKB');

        // 1. MOD-PRO lot without a premise → register the premise (SRS-PJK-03).
        const lot = await lotTanpaPremis(api);
        const premis = (
            await api.post('/pjk/premis', {
                lot_id: lot.id,
                nama_premis: `Kedai Wakaf ${ref}`,
                jenis_aset: 'unit_kedai',
                kategori_hartanah: 'premis_perniagaan',
                keluasan: 120,
                alamat: `No. 1, Jalan UAT ${ref}, Selangor`,
                kadar_tawaran: '1300.00',
            })
        ).data;
        expect(premis.lot_id).toBe(lot.id);
        expect(premis.kod_daerah).toBe(lot.kod_daerah);
        expect(premis.status_premis).toBe('kosong');

        // 2. Tenant (SRS-PJK-01, Sulit) and the draft agreement.
        const ssm = ref.toUpperCase().replace(/[^0-9A-Z-]/g, '');
        const penyewa = (await api.post('/pjk/penyewa', { jenis_entiti: 'syarikat', nama: `Syarikat Niaga ${ref} Sdn Bhd`, no_pendaftaran_syarikat: ssm, emel: 'penyewa.uat@mais.test', telefon: '03-55001234' })).data;
        const { mula, tamat } = tempoh();
        const draf = (
            await api.post('/pjk/kontrak', {
                premis_id: premis.id,
                penyewa_id: penyewa.id,
                jenis_perjanjian: 'sewaan',
                tarikh_mula: mula,
                tarikh_tamat: tamat,
                kadar: KADAR.toFixed(2),
                deposit: DEPOSIT.toFixed(2),
                kitaran_caj: 'bulanan',
                terma: 'Sewaan premis perniagaan (UAT).',
            })
        ).data;
        expect(draf.status).toBe('draf');
        const kontrakId: string = draf.id;

        // 3. Submit → layered WFE approval (SRS-PJK-05).
        const hantar = (await api.post(`/pjk/kontrak/${kontrakId}/hantar`)).data;
        expect(hantar.status).toBe('dalam_kelulusan');
        const instans = hantar.id_instans_aliran;
        expect(instans, 'instans aliran pjk.kelulusan_kontrak').toBeTruthy();
        const wfe = (await api.get(`/wfe/instans/${instans}`)).data;
        expect(wfe.kod_aliran ?? wfe.definisi?.kod_aliran ?? wfe.aliran?.kod_aliran).toBe('pjk.kelulusan_kontrak');

        const keputusan = await luluskanInstans(instans);
        const bahagian = `kb_${lot.bahagian_pemilik === 'wakaf' || lot.bahagian_pemilik === 'baitulmal' ? lot.bahagian_pemilik : 'hartanah'}`;
        expect(keputusan.map((k) => k.peringkat), 'semakan Unit Sewaan → Ketua Bahagian ikut bahagian lot').toEqual(['semakan', bahagian]);
        expect(keputusan.map((k) => k.who)).toEqual(['siti', 'faizal']);
        expect(await instanceStatus(api, instans)).toMatch(/lulus|selesai/);

        // 4. WorkflowCompleted listener → contract active, premise "disewa", charges scheduled (SRS-PJK-02/03/12).
        const aktif = await eventually(async () => {
            const k = (await api.get(`/pjk/kontrak/${kontrakId}`)).data;
            return k.status === 'aktif' ? k : null;
        }, 'kontrak aktif');
        expect(aktif.no_kontrak).toBe(draf.no_kontrak);
        expect((await api.get(`/pjk/premis/${premis.id}`)).data.status_premis).toBe('disewa');

        const caj = rows(await api.get(`/pjk/kontrak/${kontrakId}/caj`));
        const sewa = caj.filter((c: Json) => c.kod_caj === 'sewa');
        expect(sewa.length, '12 caj sewa bulanan dijadualkan').toBeGreaterThanOrEqual(12);
        const sewaPertama = sewa.find((c: Json) => c.tarikh_caj === mula);
        expect(sewaPertama, 'caj sewa bulan pertama').toBeTruthy();
        expect(sewaPertama.dikeluarkan).toBe(true);
        expect(sewaPertama.status_caj).toBe('belum_bayar');
        expect(Number(sewaPertama.amaun.nilai)).toBe(KADAR);
        expect(caj.some((c: Json) => c.kod_caj === 'deposit' && Number(c.amaun.nilai) === DEPOSIT)).toBe(true);
        expect(sewa.filter((c: Json) => c.status_caj === 'dijadualkan').length).toBeGreaterThan(0);

        // 5. DSH revenue before the payment.
        const hasilSebelum = await hasilSewaan(api);

        // 6. FPX session for the first month's rent (SRS-PJK-14/15).
        const tx = (await api.post(`/pjk/kontrak/${kontrakId}/bayaran-dalam-talian`, { saluran: 'fpx', caj_ids: [sewaPertama.id], nama_pembayar: penyewa.nama })).data;
        expect(tx.status).toBe('menunggu');
        expect(Number(tx.amaun.nilai)).toBe(KADAR);
        expect(tx.url_bayaran).toContain('/admin/pjk/gateway-ujian/');

        // An unsigned callback is rejected (SRS-PJK-16) and changes nothing.
        const palsu = await api.raw('POST', '/pjk/gateway/panggilan-balik', { id_acara: `EVT-${ref}`, no_rujukan: tx.no_rujukan, status: 'berjaya', amaun: String(KADAR) });
        expect(palsu.status()).toBe(401);
        expect((await api.get(`/pjk/gateway/transaksi/${tx.id}`)).data.status).toBe('menunggu');

        // The payer completes on the test gateway page → signed callback through the webhook path.
        const sim = await api.post(`/pjk/gateway-ujian/${encodeURIComponent(tx.no_rujukan)}/simulasi`, { keputusan: 'berjaya' });
        expect(sim.panggilan_balik).toBe('berjaya');
        expect(sim.data.status).toBe('berjaya');
        // A repeated callback is idempotent — never a second credit.
        const ulang = await api.post(`/pjk/gateway-ujian/${encodeURIComponent(tx.no_rujukan)}/simulasi`, { keputusan: 'berjaya' });
        expect(ulang.panggilan_balik).toBe('pendua');

        // 7. Ledger credited once, receipt issued (SRS-PJK-11/17).
        const lejar = await api.get(`/pjk/kontrak/${kontrakId}/lejar`);
        const kredit = rows(lejar).filter((l: Json) => l.jenis === 'bayaran');
        expect(kredit).toHaveLength(1);
        expect(Number(kredit[0].kredit.nilai)).toBe(KADAR);
        expect(kredit[0].kaedah_bayaran).toBe('fpx');
        expect(kredit[0].no_resit).toBeTruthy();
        const noResit: string = kredit[0].no_resit;

        const bayaran = await api.get(`/pjk/kontrak/${kontrakId}/bayaran`);
        const b = rows(bayaran).find((x: Json) => x.saluran === 'fpx');
        expect(b.status).toBe('disahkan');
        expect(b.rujukan_luar).toBe(sim.data.rujukan_gateway);
        expect((b.resit ?? []).map((r: Json) => r.no_resit)).toContain(noResit);
        const sewaDibayar = rows(await api.get(`/pjk/kontrak/${kontrakId}/caj`)).find((c: Json) => c.id === sewaPertama.id);
        expect(sewaDibayar.status_caj).toBe('dibayar');

        // 8. DSH revenue KPI moved by (at least — other UAT runs may pay too) the rent paid.
        const hasilSelepas = await hasilSewaan(api);
        expect(hasilSelepas - hasilSebelum).toBeGreaterThanOrEqual(KADAR - 0.005);

        // 9. UI: the contract page shows the active agreement, the receipt and the ledger credit.
        await page.goto(`/admin/pjk/kontrak/${kontrakId}?tab=bayaran`);
        await expect(page.getByText(aktif.no_kontrak).first()).toBeVisible({ timeout: 30_000 });
        await expect(page.getByText('Aktif').first()).toBeVisible();
        await expect(page.getByRole('button', { name: noResit })).toBeVisible();
        await page.getByRole('button', { name: 'Lejar', exact: true }).click();
        await expect(page.getByText(noResit).first()).toBeVisible();

        await context.close();
    });
});
