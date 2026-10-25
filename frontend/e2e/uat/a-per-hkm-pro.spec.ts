import { expect, test } from "@playwright/test";
import type { Api, Json } from "./helpers";
import { instanceStatus, rows, signIn } from "./helpers";
import { approveAll, logout, pdfFile, today } from "./_per";

/**
 * UAT a: Pembelian terus tanah oleh MAIS — PER → WFE → HKM → PRO.
 *
 * Cerita: Bahagian Pengurusan Hartanah MAIS membeli sebidang tanah di Mukim
 * Bukit Raja, Petaling. Pentadbir mendaftar lot baharu dalam PRO, membuka kes
 * pemerolehan saluran "Pembelian Terus" (PER), mengisi butiran perjanjian jual
 * beli dan memuat naik dokumen wajib, lalu memajukan kes ke peringkat
 * kelulusan. Aliran WFE `per.kelulusan` diluluskan berlapis (Pegawai kanan
 * penyemak → Ketua Bahagian Pengurusan Hartanah) oleh pelulus demo; pendengar
 * WorkflowCompleted dalam PER menandakan kes "Diluluskan" dan memajukannya.
 * Selepas bayaran dan pengesahan PTG, kes ditutup: hak milik didaftar dalam
 * HKM dan disimpan dalam peti besi; Profil Hartanah 360° (PRO) memaparkan hak
 * milik serta garis masa daripada PER/HKM, dalam API dan halaman UI.
 *
 * SRS: PER-10 (pembelian terus), PER-16 (senarai semak dokumen), PER-17/18
 * (peringkat), PER-19 (garis masa), PER-21/22 (tutup → HKM + peti besi),
 * PER-29 / GEN-03 / WFE-20 (kelulusan berlapis WFE), HKM-01 (daftar hak
 * milik), HKM peti besi, PRO-26..30 (profil 360°), PRO-01 (cipta lot).
 */

async function uploadChecklist(
    api: Api,
    kes: Json,
    jenis: string[],
): Promise<void> {
    for (const j of jenis) {
        const item = (kes.senarai_semak as Json[]).find(
            (s) => s.jenis_dokumen === j,
        );
        expect(item, `senarai semak ${j}`).toBeTruthy();
        await api.call("POST", `/per/kes/${kes.id}/dokumen`, undefined, {
            fail: pdfFile(`${j}.pdf`),
            id_semak: String(item.id),
        });
    }
}

test.describe("UAT a: pembelian terus PER → kelulusan WFE → hak milik HKM → profil 360° PRO", () => {
    test("kes pembelian terus diluluskan berlapis, ditutup dengan hak milik dan peti besi, terpapar dalam profil 360°", async ({
        browser,
    }) => {
        const { api, page, context } = await signIn(browser, "admin");
        const stamp = `${1_000_000 + Math.floor(Math.random() * 8_999_999)}`; // no_lot format: PT + 1..7 digits
        const noLot = `PT ${stamp}`;
        const noHakMilik = `GRN ${stamp}`;

        // 1. PRO: lot baharu di Mukim Bukit Raja, Petaling.
        const lot = (
            await api.post("/pro/lot", {
                no_lot: noLot,
                kod_mukim: "100801",
                keluasan: 2023.5,
                unit_keluasan: "m2",
                kategori_guna_tanah: "BANGUNAN",
                bahagian_pemilik: "hartanah",
            })
        ).data;
        expect(lot.kod_daerah).toBe("1008");

        // 2. PER: daftar kes pembelian terus pada lot itu, dengan butiran perjanjian jual beli.
        let kes = (
            await api.post("/per/kes", {
                kod_saluran: "pembelian_terus",
                tajuk: `Pembelian tanah ${noLot} (UAT)`,
                lot_id: lot.id,
                nilai_pasaran: 900000,
                nilai_pemerolehan: 850000,
                sumber_dana: "Dana Hartanah MAIS",
                butiran: {
                    no_perjanjian_jual_beli: `SPA/${stamp}`,
                    tarikh_perjanjian: today(),
                    nama_penjual: "Syarikat Penjual Tanah Sdn Bhd",
                    harga_belian: 850000,
                    sumber_dana: "Dana Hartanah MAIS",
                    rujukan_penilaian_jpph: `JPPH/${stamp}`,
                },
            })
        ).data;
        expect(kes.status).toBe("dalam_proses");
        expect(kes.peringkat_semasa.kod).toBe("perjanjian_jual_beli");
        expect(kes.lot.id).toBe(lot.id);

        // Dokumen wajib menghalang kemasukan ke peringkat kelulusan (SRS-PER-16).
        expect(
            (await api.raw("POST", `/per/kes/${kes.id}/maju`, {})).status(),
        ).toBe(422);
        await uploadChecklist(api, kes, [
            "perjanjian_jual_beli",
            "laporan_penilaian_jpph",
            "kelulusan_perolehan_mais",
            "hak_milik_asal_penjual",
        ]);

        // 3. Maju ke peringkat kelulusan → aliran WFE per.kelulusan bermula.
        kes = (
            await api.post(`/per/kes/${kes.id}/maju`, {
                catatan: "Dokumen lengkap, hantar untuk kelulusan.",
            })
        ).data;
        expect(kes.status).toBe("menunggu_kelulusan");
        expect(kes.peringkat_semasa.kod).toBe("kelulusan");
        const instanceId = kes.id_aliran_kerja as string;
        expect(instanceId).toBeTruthy();

        // 4. WFE: pelulus demo meluluskan setiap lapisan (penyemak → Ketua Bahagian Hartanah).
        const decisions = await approveAll(instanceId);
        expect(decisions).toEqual([
            "siti:semakan_pegawai_kanan",
            "faizal:kb_hartanah",
        ]);
        expect(await instanceStatus(api, instanceId)).toBe("lulus");

        // 5. Kesan WorkflowCompleted dalam PER: kes diluluskan dan maju ke peringkat bayaran.
        kes = (await api.get(`/per/kes/${kes.id}`)).data;
        expect(kes.status).toBe("diluluskan");
        expect(kes.peringkat_semasa.kod).toBe("bayaran_penyelesaian");
        const aras = (kes.kelulusan as Json[]).map(
            (k) => `${k.aras}:${k.tindakan}`,
        );
        expect(aras).toContain("penghantaran:hantar");
        expect(aras).toContain("keputusan_akhir:lulus");
        expect(
            (kes.kelulusan as Json[]).filter(
                (k) => k.tindakan === "lulus" && k.aras !== "keputusan_akhir",
            ).length,
        ).toBeGreaterThanOrEqual(2);

        // 6. Bayaran → pindah milik dan pengesahan PTG → simpanan dokumen.
        await api.put(`/per/kes/${kes.id}`, {
            butiran: { tarikh_penyelesaian: today() },
        });
        await uploadChecklist(api, kes, ["bukti_bayaran", "borang_14a"]);
        kes = (await api.post(`/per/kes/${kes.id}/maju`, {})).data;
        expect(kes.peringkat_semasa.kod).toBe("pendaftaran_hak_milik");
        await api.put(`/per/kes/${kes.id}`, {
            butiran: {
                no_perserahan_ptg: `PRS/${stamp}`,
                status_pendaftaran_ptg: "didaftar",
            },
        });
        await uploadChecklist(api, kes, ["pengesahan_pendaftaran_ptg"]);
        kes = (await api.post(`/per/kes/${kes.id}/maju`, {})).data;
        expect(kes.peringkat_semasa.kod).toBe("simpanan_dokumen");

        // 7. Tutup kes: hak milik didaftar dalam HKM dan disimpan dalam peti besi (SRS-PER-21/22).
        kes = (
            await api.post(`/per/kes/${kes.id}/tutup`, {
                hak_milik: {
                    no_hak_milik: noHakMilik,
                    jenis_hak_milik: "GRN",
                    tarikh_pendaftaran: today(),
                    pejabat_pendaftaran: "Pejabat Tanah dan Galian Selangor",
                    keluasan: 2023.5,
                },
                peti_besi: {
                    kod_peti_besi: "PB-MAIS-01",
                    no_fail: `FAIL/${stamp}`,
                    lokasi_rak: "R-UAT-01",
                },
            })
        ).data;
        expect(kes.status).toBe("selesai");

        // 8. HKM: hak milik pada lot, pemilik MAIS, dengan daftar peti besi.
        const titles = rows(await api.get(`/hkm/lot/${lot.id}/hak-milik`));
        const title = titles.find((t: Json) => t.no_hak_milik === noHakMilik);
        expect(title, "hak milik baharu dalam HKM").toBeTruthy();
        const titleDetail = (await api.get(`/hkm/hak-milik/${title.id}`)).data;
        expect(JSON.stringify(titleDetail)).toContain(
            "Majlis Agama Islam Selangor",
        );
        expect(JSON.stringify(titleDetail)).toContain("PB-MAIS-01");

        // PER garis masa (SRS-PER-19): peringkat + keputusan kelulusan.
        const timeline = (await api.get(`/per/kes/${kes.id}/garis-masa`))
            .data as Json[];
        expect(timeline.filter((e) => e.jenis === "peringkat").length).toBe(5);
        expect(
            timeline.some(
                (e) => e.jenis === "kelulusan" && e.kod === "keputusan_akhir",
            ),
        ).toBe(true);

        // 9. PRO profil 360° (API): seksyen hak milik + pemerolehan dan garis masa PER/HKM.
        const profil = (await api.get(`/pro/lot/${lot.id}/profil`)).data;
        const flat = JSON.stringify(profil.tab);
        expect(flat).toContain(noHakMilik);
        expect(flat).toContain(kes.no_kes);
        const identiti = (profil.tab as Json[]).find(
            (t) => t.kod === "identiti",
        );
        expect(
            identiti.seksyen
                .filter((x: Json) =>
                    ["hak_milik", "pemerolehan"].includes(x.key),
                )
                .map((x: Json) => x.status),
        ).toEqual(["ada", "ada"]);
        const events = profil.garis_masa as Json[];
        const closed = `Kes pemerolehan ${kes.no_kes} selesai; hak milik didaftarkan`;
        expect(
            events.some(
                (e) => e.seksyen === "pemerolehan" && e.title === closed,
            ),
        ).toBe(true);
        expect(
            events.some(
                (e) =>
                    e.seksyen === "hak_milik" &&
                    e.title.includes(noHakMilik) &&
                    e.title.includes("Majlis Agama Islam Selangor"),
            ),
        ).toBe(true);

        // 10. UI: halaman profil 360° lot memaparkan hak milik dan garis masa.
        await page.goto(`/admin/pro/lot/${lot.id}?tab=identiti`);
        await expect(
            page.getByRole("heading", { name: noLot, exact: true }),
        ).toBeVisible({ timeout: 30_000 });
        await expect(page.getByText(noHakMilik).first()).toBeVisible();
        await expect(
            page.getByText("Garis masa", { exact: true }),
        ).toBeVisible();
        const showAll = page.getByRole("button", { name: /Papar semua/ });
        if (await showAll.isVisible()) await showAll.click();
        await expect(page.getByRole("heading", { name: closed })).toBeVisible();
        await expect(
            page.getByRole("heading", {
                name: `Pemilik direkodkan: Majlis Agama Islam Selangor (${noHakMilik})`,
            }),
        ).toBeVisible();

        // UI: halaman kes PER memaparkan status Selesai.
        await page.goto(`/admin/per/kes/${kes.id}`);
        await expect(page.getByText(kes.no_kes).first()).toBeVisible({
            timeout: 30_000,
        });
        await expect(page.getByText("Selesai").first()).toBeVisible();

        // Audit (AUD): penutupan kes direkodkan (pentadbir boleh semak jejak audit).
        const audit = rows(
            await api.get(
                `/aud/rekod?modul=per&id_rekod=${kes.id}&per_page=100`,
            ),
        );
        const actions = audit.map((r: Json) => r.jenis_tindakan);
        expect(actions).toContain("tutup");
        expect(actions).toContain("lulus");
        await logout(api);
        await context.close();
    });
});
