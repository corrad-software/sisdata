import { expect, test } from '@playwright/test';
import { rows, type Json } from './helpers';
import { apiTahan, masuk, pusat, tileOf, type Tahan } from './_rantaian';

/**
 * UAT f — Skop data mengikut daerah (SRS-RBC-08), semakan silang modul.
 *
 * Siti Aminah ialah pegawai daerah Petaling (skop daerah 1008). Pentadbir
 * sistem tiada had skop. Kita pilih satu lot di Petaling dan satu lot di
 * daerah lain (Sabak Bernam, jauh dari Petaling) dan pastikan skop Siti
 * dikuatkuasakan di setiap pintu masuk data, bukan sekadar disembunyikan di
 * UI: senarai & profil 360° lot (MOD-PRO), ciri GeoJSON & jubin vektor (MOD-GIS),
 * carian global (MOD-ADM), laporan ad hoc / katalog (MOD-LAP), KPI papan
 * pemuka (MOD-DSH) dan eksport. Rekod luar skop = 404 (bukan 403) supaya
 * kewujudannya tidak didedahkan. Pentadbir melihat kedua-dua lot.
 *
 * SRS: RBC-08, PRO-10/27, GIS-08/34, ADM-17 (carian global), LAP-02/03, DSH-01/04.
 */
const SKOP = '1008';
const LAIN = '1005';

async function lotDalam(api: Tahan, daerah: string): Promise<Json> {
    const list = rows(await api.get(`/pro/lot?kod_daerah=${daerah}&per_page=100`)).filter((l: Json) => l.spatial?.keluasan_dikira);
    expect(list.length, `lots in ${daerah}`).toBeGreaterThan(0);
    return list[Math.floor(Math.random() * list.length)];
}

test.describe('UAT f: skop daerah siti (1008) vs pentadbir merentas PRO/GIS/ADM/LAP/DSH', () => {
    test('lot luar daerah tidak boleh dilihat oleh siti di mana-mana modul; pentadbir melihat semua', async ({ browser }) => {
        test.setTimeout(180_000);
        const admin = await apiTahan('admin');
        const ad = admin.api;
        const dalam = await lotDalam(ad, SKOP);
        const luar = await lotDalam(ad, LAIN);
        expect(luar.kod_daerah).not.toBe(SKOP);

        const siti = await masuk(browser, 'siti');
        const si = siti.api;
        expect((await si.get('/user')).data.email).toBe('siti.aminah@mais.test');

        // --- MOD-PRO list: only 1008, and no more than the admin sees.
        const sitiList = await si.get('/pro/lot?per_page=200');
        const adminList = await ad.get('/pro/lot?per_page=1');
        expect(rows(sitiList).length).toBeGreaterThan(0);
        expect(new Set(rows(sitiList).map((l: Json) => l.kod_daerah))).toEqual(new Set([SKOP]));
        expect(sitiList.meta.total).toBeLessThan(adminList.meta.total);
        // Filtering on another district does not widen the scope.
        expect(rows(await si.get(`/pro/lot?kod_daerah=${LAIN}`))).toHaveLength(0);
        expect(rows(await si.get(`/pro/lot?q=${encodeURIComponent(luar.no_lot)}&kod_daerah=${LAIN}`))).toHaveLength(0);

        // --- MOD-PRO detail + 360° profile + geometry: other district is a 404 for siti.
        for (const p of [`/pro/lot/${luar.id}`, `/pro/lot/${luar.id}/profil`, `/pro/lot/${luar.id}/geojson`, `/pro/lot/${luar.id}/versi`]) {
            expect(await si.status('GET', p), `siti ${p}`).toBe(404);
        }
        expect((await si.get(`/pro/lot/${dalam.id}/profil`)).data.lot.id).toBe(dalam.id);
        expect((await ad.get(`/pro/lot/${luar.id}/profil`)).data.lot.id).toBe(luar.id);
        expect((await ad.get(`/pro/lot/${dalam.id}`)).data.id).toBe(dalam.id);

        // --- MOD-GIS GeoJSON features: siti only gets 1008 features; admin gets other districts.
        const sitiCiri = (await si.get('/gis/lapisan/gis.lot/ciri')).features as Json[];
        expect(sitiCiri.length).toBeGreaterThan(0);
        expect(new Set(sitiCiri.map((f) => f.properties.kod_daerah))).toEqual(new Set([SKOP]));
        expect(sitiCiri.some((f) => f.properties.id === luar.id)).toBe(false);
        const adminCiri = (await ad.get('/gis/lapisan/gis.lot/ciri')).features as Json[];
        expect(new Set(adminCiri.map((f) => f.properties.kod_daerah)).size).toBeGreaterThan(1);

        // --- MOD-GIS vector tile over the other-district lot: empty (204) for siti, has the lot for admin.
        const geom = (await ad.get(`/pro/lot/${luar.id}/geojson`)).geometry;
        const c = pusat(geom);
        const { x, y } = tileOf(c.lon, c.lat, 16);
        const tilePath = `/gis/tiles/gis.lot/16/${x}/${y}.pbf`;
        const adminTile = await ad.raw('GET', tilePath);
        expect(adminTile.status()).toBe(200);
        expect((await adminTile.body()).includes(Buffer.from(String(luar.no_lot))), 'admin tile carries the lot').toBe(true);
        const sitiTile = await si.raw('GET', tilePath);
        expect([200, 204]).toContain(sitiTile.status());
        expect((await sitiTile.body()).includes(Buffer.from(String(luar.no_lot))), 'siti tile must not carry the lot').toBe(false);

        // --- MOD-ADM global search: admin finds the other lot, siti does not (but finds her own).
        const cari = async (api: Tahan, q: string) =>
            ((await api.get(`/adm/carian?q=${encodeURIComponent(q)}`)).data as Json[]).flatMap((g) => g.hasil.map((h: Json) => ({ ...h, entiti: g.entiti })));
        expect((await cari(ad, luar.no_lot)).some((h) => h.entiti === 'lot_tanah' && h.id === luar.id)).toBe(true);
        const sitiLuar = await cari(si, luar.no_lot);
        expect(sitiLuar.some((h) => h.id === luar.id || String(h.link).includes(luar.id))).toBe(false);
        expect(sitiLuar.filter((h) => h.entiti === 'lot_tanah').every((h) => String(h.subtitle ?? '').includes('Petaling') || String(h.title).includes('Petaling'))).toBe(true);
        expect((await cari(si, dalam.no_lot)).some((h) => h.entiti === 'lot_tanah' && h.id === dalam.id)).toBe(true);

        // --- MOD-LAP ad hoc report on the lot data set: siti's rows are all 1008.
        const jana = async (api: Tahan, adhoc: Json) => (await api.post('/lap/jana', { sumber: { adhoc } })).data.bahagian[0].hasil;
        const sitiKump = await jana(si, { set: 'lot', kumpulan: ['kod_daerah'] });
        expect((sitiKump.baris as Json[]).map((b) => b.kod_daerah)).toEqual([SKOP]);
        const adminKump = await jana(ad, { set: 'lot', kumpulan: ['kod_daerah'] });
        expect((adminKump.baris as Json[]).map((b) => b.kod_daerah)).toEqual(expect.arrayContaining([SKOP, LAIN]));
        expect(sitiKump.bilangan_rekod).toBeLessThan(adminKump.bilangan_rekod);
        const penapisLuar = { set: 'lot', medan: ['no_lot', 'kod_daerah'], penapis: [{ medan: 'kod_daerah', op: 'sama', nilai: LAIN }] };
        expect((await jana(si, penapisLuar)).baris).toHaveLength(0);
        expect((await jana(ad, penapisLuar)).baris.length).toBeGreaterThan(0);

        // --- Exports: siti holds no export permission in any of these modules → refused;
        // the admin's export is not scoped down (other district present).
        expect(await si.status('POST', '/lap/eksport', { sumber: { adhoc: { set: 'lot', medan: ['no_lot', 'kod_daerah'] } }, format: 'csv' })).toBe(403);
        expect(await si.status('GET', '/pro/lot/eksport?format=csv')).toBe(403);
        expect(await si.status('GET', '/gis/lapisan/gis.lot/eksport?format=geojson')).toBe(403);
        const csv = await ad.raw('POST', '/lap/eksport', { sumber: { adhoc: { set: 'lot', medan: ['no_lot', 'kod_daerah'], penapis: [{ medan: 'kod_daerah', op: 'sama', nilai: LAIN }] } }, format: 'csv' });
        expect(csv.status()).toBe(200);
        expect(await csv.text()).toContain(String(luar.no_lot));

        // --- MOD-DSH KPIs: scoped counts ≤ admin's; the lot count breakdown is Petaling only.
        // segar=1: KPIs are cached per user (NFR-09 allows 15 min lag), so compare fresh values.
        const kpi = async (api: Tahan) => Object.fromEntries(((await api.get('/dsh/kpi?segar=1')).data as Json[]).map((k) => [k.key, k]));
        const [ks, ka] = [await kpi(si), await kpi(ad)];
        expect(ks['pro.bilangan_lot'].nilai).toBeLessThan(ka['pro.bilangan_lot'].nilai);
        expect((ks['pro.bilangan_lot'].pecahan as Json[]).map((p) => p.kod)).toEqual([`daerah:${SKOP}`]);
        // Record counts only (not %, averages, or per-user KPIs like unread notifications).
        const kiraan = ['lot', 'hak milik', 'kes', 'aduan', 'aset', 'urusan', 'penemuan', 'kontrak'];
        for (const key of Object.keys(ks)) {
            if (kiraan.includes(ks[key].unit) && typeof ka[key]?.nilai === 'number') {
                expect(ks[key].nilai, `KPI ${key}`).toBeLessThanOrEqual(ka[key].nilai);
            }
        }

        // --- UI as siti: lot list shows only Petaling rows; a direct URL to the other lot is not found.
        const page = siti.page;
        await si.segar();
        await page.goto('/admin/pro/lot');
        const body = page.locator('table tbody tr');
        await expect(body.first().getByRole('link')).toBeVisible({ timeout: 30_000 });
        const n = await body.count();
        expect(n).toBeGreaterThan(0);
        for (let i = 0; i < n; i++) {
            await expect(body.nth(i)).toContainText('Petaling');
        }
        await expect(page.getByRole('link', { name: String(luar.no_lot), exact: true })).toHaveCount(0);

        await si.segar();
        await page.goto(`/admin/pro/lot/${luar.id}`);
        await expect(page.getByText('Lot tidak dijumpai.').first()).toBeVisible({ timeout: 30_000 });

        await si.segar();
        await page.goto(`/admin/pro/lot/${dalam.id}`);
        await expect(page.getByText(String(dalam.no_lot)).first()).toBeVisible({ timeout: 30_000 });

        // --- UI as admin: the other-district lot opens.
        const adm = await masuk(browser, 'admin');
        await adm.page.goto(`/admin/pro/lot/${luar.id}`);
        await expect(adm.page.getByText(String(luar.no_lot)).first()).toBeVisible({ timeout: 30_000 });
        await expect(adm.page.getByText('Lot tidak dijumpai.')).toHaveCount(0);

        await adm.context.close();
        await siti.context.close();
        await admin.dispose();
    });
});
