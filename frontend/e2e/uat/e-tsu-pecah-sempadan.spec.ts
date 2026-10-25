import { expect, test } from "@playwright/test";
import type { Api, Json } from "./helpers";
import { instanceStatus, rows, signIn } from "./helpers";
import { approveAll, asUser, logout, pdfFile, today } from "./_per";

/**
 * UAT e: Pecah sempadan lot MAIS — TSU (urusan ukur) → WFE → PRO → CKI.
 *
 * Cerita: Pegawai daerah Petaling (siti.aminah) mendaftar urusan pecah
 * sempadan bagi sebidang lot hartanah MAIS yang mempunyai akaun cukai tanah
 * (CKI). Ketua Bahagian (mohd.faizal) meluluskan peringkat kelulusan dalaman
 * MAIS melalui WFE `tsu.kelulusan_urusan`. Permohonan dihantar ke PTD, jurukur
 * berlesen dilantik melalui sebut harga (penilaian oleh mohd.faizal, kelulusan
 * Pegawai Berkuasa nurul.huda melalui `tsu.kelulusan_lantikan`). Hasil ukur
 * (GeoJSON, dua petak) diimport dan disahkan oleh pegawai lain; selepas fi
 * dibayar dan pelan akui diterima, dua lot pengganti dicipta dalam PRO dengan
 * salasilah asal ↔ baharu (lot_sejarah_pecahan) dan lot asal diarkibkan.
 * Akaun cukai lot asal dipetakan ke lot baharu dan dipindahkan selepas
 * pengesahan dua peringkat oleh dua pegawai berbeza
 * (`tsu.pengesahan_pemindahan`). Urusan ditutup dan dikunci.
 *
 * SRS: TSU-01..03 (daftar urusan, peringkat, dokumen wajib), TSU-07/WFE-19
 * (kelulusan dalaman), TSU-08..10 (sebut harga, perbandingan, lantikan),
 * TSU-13/14 (kos), TSU-16..18 (hasil ukur, keluasan, pengesahan),
 * TSU-19/21 (lot baharu + salasilah dua hala), TSU-20 (pemindahan rekod dua
 * pengesah), TSU-22 (status PTD), TSU-24 (tutup & kunci), PRO-01/23 (lot
 * pengganti, arkib), PRO 360° seksyen urusan_tanah, CKI obligasi.
 */

function square(lng: number, lat: number, d = 0.0004): number[][][] {
    return [
        [
            [lng, lat],
            [lng + d, lat],
            [lng + d, lat + d],
            [lng, lat + d],
            [lng, lat],
        ],
    ];
}

async function urusan(api: Api, id: string): Promise<Json> {
    return (await api.get(`/tsu/urusan/${id}`)).data;
}

function currentStage(u: Json): Json {
    return (u.peringkat as Json[]).find(
        (p) => p.status === "aktif" || p.status === "menunggu_kelulusan",
    );
}

async function upload(api: Api, id: string, kategori: string): Promise<Json> {
    return (
        await api.call("POST", `/tsu/urusan/${id}/dokumen`, undefined, {
            fail: pdfFile(`${kategori}.pdf`),
            kategori_dokumen: kategori,
            tarikh_dokumen: today(),
        })
    ).data;
}

/** Complete the current stage and check we landed on `next` (or the approval hand-off). */
async function complete(
    api: Api,
    id: string,
    expectedCode: string,
    catatan?: string,
): Promise<Json> {
    const before = currentStage(await urusan(api, id));
    expect(before.kod).toBe(expectedCode);
    return (
        await api.post(
            `/tsu/urusan/${id}/peringkat/selesai`,
            catatan ? { catatan } : {},
        )
    ).data;
}

test.describe("UAT e: pecah sempadan TSU → kelulusan WFE → lot pengganti PRO → pemindahan rekod CKI", () => {
    test("urusan pecah sempadan dari daftar hingga lot baharu, pemindahan akaun cukai dua pengesah dan tutup", async ({
        browser,
    }) => {
        test.setTimeout(180_000);
        const siti = await signIn(browser, "siti");
        const admin = await signIn(browser, "admin");
        const n = 1_000_000 + Math.floor(Math.random() * 8_999_997);
        const [noAsal, noBaru1, noBaru2] = [
            `Lot ${n}`,
            `Lot ${n + 1}`,
            `Lot ${n + 2}`,
        ];

        try {
            // 0. Lot hartanah MAIS di Petaling (PRO) dengan akaun cukai tanah (CKI).
            const lot = (
                await admin.api.post("/pro/lot", {
                    no_lot: noAsal,
                    kod_mukim: "100801",
                    keluasan: 4000,
                    unit_keluasan: "m2",
                    kategori_guna_tanah: "BANGUNAN",
                    bahagian_pemilik: "hartanah",
                })
            ).data;
            const obligasi = (
                await siti.api.post("/cki/obligasi", {
                    lot_id: lot.id,
                    jenis_cukai: "tanah",
                    no_akaun_cukai: `CT-${n}`,
                    catatan: "Akaun cukai lot asal (UAT e).",
                })
            ).data;

            // 1. Daftar urusan pecah sempadan (pegawai daerah).
            const created = (
                await siti.api.post("/tsu/urusan", {
                    jenis_urusan: "pecah_sempadan",
                    lot_ids: [lot.id],
                    tajuk: `Pecah sempadan ${noAsal} kepada dua lot (UAT)`,
                    pejabat_tanah: "Pejabat Tanah Petaling",
                    butiran: { bilangan_lot_dicadang: 2 },
                })
            ).data;
            const id = created.id as string;
            let u = await urusan(siti.api, id);
            expect(u.status).toBe("aktif");
            expect(u.lot[0].peranan_lot).toBe("asal");
            expect(u.dokumen_belum_lengkap.length).toBe(2);

            // Dokumen wajib menghalang peringkat (SRS-TSU-03).
            expect(
                (
                    await siti.api.raw(
                        "POST",
                        `/tsu/urusan/${id}/peringkat/selesai`,
                        {},
                    )
                ).status(),
            ).toBe(422);
            await upload(siti.api, id, "borang_permohonan");
            await upload(siti.api, id, "pelan_permulaan");
            await complete(siti.api, id, "penyediaan_dokumen");

            // 2. Kelulusan dalaman MAIS melalui WFE tsu.kelulusan_urusan (Ketua Bahagian).
            const approval = await complete(siti.api, id, "kelulusan_mais");
            expect(approval.status).toBe("menunggu_kelulusan");
            expect(
                await approveAll(approval.id_aliran_kerja, ["faizal"]),
            ).toEqual(["faizal:kelulusan_ketua_bahagian"]);
            expect(
                await instanceStatus(admin.api, approval.id_aliran_kerja),
            ).toBe("lulus");
            u = await urusan(siti.api, id);
            expect(u.status).toBe("aktif");
            expect(currentStage(u).kod).toBe("permohonan_ptd");
            expect(
                (u.keputusan as Json[]).some(
                    (k) => k.jenis_keputusan === "lulus",
                ),
            ).toBe(true);

            // 3. Permohonan ke PTD melalui API berpusat (Petaling diliputi; kemas kini manual ditolak, SRS-TSU-22).
            await upload(siti.api, id, "surat_kelulusan");
            const ptdRef = (
                await siti.api.post(`/tsu/urusan/${id}/ptd/hantar`, {})
            ).data.no_rujukan_ptd;
            expect(ptdRef).toBeTruthy();
            expect(
                (
                    await siti.api.raw("POST", `/tsu/urusan/${id}/ptd/manual`, {
                        status_ptd: "lulus",
                        tarikh_status: today(),
                    })
                ).status(),
            ).toBe(422);
            u = await urusan(siti.api, id);
            expect(u.ptd.liputan_api).toBe(true);
            expect((u.ptd.sejarah as Json[])[0].status_ptd).toBe("diterima");
            await complete(siti.api, id, "permohonan_ptd");

            // 4. Jemputan sebut harga ukur → tawaran → penilaian → kelulusan → lantikan.
            const panel = rows(await siti.api.get("/tsu/panel?layak=1")).slice(
                0,
                3,
            );
            expect(panel.length).toBe(3);
            const closes = new Date(Date.now() + 6_000).toISOString();
            const sh = (
                await siti.api.post(`/tsu/urusan/${id}/sebutharga`, {
                    tarikh_tutup: closes,
                    skop_kerja: `Ukur halus pecah sempadan ${noAsal} kepada 2 lot.`,
                    jurukur_ids: panel.map((p: Json) => p.id),
                })
            ).data;
            const offers = sh.tawaran as Json[];
            expect(offers.length).toBe(3);
            for (const [i, t] of offers.entries()) {
                await siti.api.post(
                    `/tsu/sebutharga/${sh.id}/tawaran/${t.id}`,
                    {
                        harga_tawaran: String(8000 + i * 1500),
                        tempoh_siap_hari: 30 + i * 10,
                        pengalaman_tahun: 10 - i,
                    },
                );
            }
            // Harga tersembunyi sehingga tarikh tutup (SRS-TSU-09).
            expect(
                (
                    await siti.api.raw(
                        "GET",
                        `/tsu/sebutharga/${sh.id}/perbandingan`,
                    )
                ).status(),
            ).toBe(403);
            await new Promise((r) =>
                setTimeout(
                    r,
                    Math.max(0, Date.parse(closes) - Date.now() + 1_500),
                ),
            );
            const evaluated = await asUser("faizal", async (faizal) => {
                const table = (
                    await faizal.get(`/tsu/sebutharga/${sh.id}/perbandingan`)
                ).data.jadual as Json[];
                const best = table.find((r) => r.kedudukan === 1);
                expect(best).toBeTruthy();
                // Pendaftar urusan tidak boleh menilai (pengasingan tugas).
                expect(
                    (
                        await siti.api.raw(
                            "POST",
                            `/tsu/sebutharga/${sh.id}/nilai`,
                            {
                                id_tawaran_disyorkan: best.id,
                                justifikasi_pemilihan: "Markah tertinggi.",
                            },
                        )
                    ).status(),
                ).toBe(422);
                return (
                    await faizal.post(`/tsu/sebutharga/${sh.id}/nilai`, {
                        id_tawaran_disyorkan: best.id,
                        justifikasi_pemilihan:
                            "Markah gabungan tertinggi: harga terendah dan tempoh terpendek.",
                    })
                ).data;
            });
            expect(evaluated.status).toBe("menunggu_kelulusan");
            // Aliran tsu.kelulusan_lantikan bagi sebut harga ini (subjek = id sebut harga), dilihat melalui pemantauan WFE.
            const shFlow = rows(
                await admin.api.get(
                    `/wfe/instans?kod_aliran=tsu.kelulusan_lantikan&q=${sh.id}`,
                ),
            );
            expect(shFlow).toHaveLength(1);
            expect(await approveAll(shFlow[0].id, ["huda"])).toEqual([
                "huda:kelulusan_pegawai_berkuasa",
            ]);
            expect(
                (await siti.api.get(`/tsu/urusan/${id}/ukur`)).data
                    .sebutharga[0].status,
            ).toBe("diluluskan");
            const lantikan = (
                await siti.api.post(`/tsu/sebutharga/${sh.id}/lantik`, {
                    no_surat_setuju_terima: `SST/${n}`,
                    tarikh_mula: today(),
                })
            ).data;
            expect(lantikan.status).toBe("aktif");
            await complete(siti.api, id, "jemputan_ukur");

            // 5. Kerja ukur: kemajuan jurukur, import hasil ukur (2 petak), pengesahan oleh pegawai lain.
            await siti.api.post(`/tsu/lantikan/${lantikan.id}/kemajuan`, {
                milestone: "Kerja lapangan siap",
                peratus_siap: 100,
                tarikh_lapor: today(),
            });
            const geojson = JSON.stringify({
                type: "FeatureCollection",
                features: [
                    {
                        type: "Feature",
                        properties: { no_lot: noBaru1 },
                        geometry: {
                            type: "Polygon",
                            coordinates: square(101.5512, 3.0811),
                        },
                    },
                    {
                        type: "Feature",
                        properties: { no_lot: noBaru2 },
                        geometry: {
                            type: "Polygon",
                            coordinates: square(101.5516, 3.0811),
                        },
                    },
                ],
            });
            const results = (
                await siti.api.post(`/tsu/urusan/${id}/hasil-ukur`, {
                    format_fail: "geojson",
                    sistem_koordinat: 4326,
                    geojson,
                    lantikan_id: lantikan.id,
                })
            ).data as Json[];
            expect(results.map((h) => h.no_lot_cadangan)).toEqual([
                noBaru1,
                noBaru2,
            ]);
            for (const h of results)
                expect(Number(h.keluasan_dikira)).toBeGreaterThan(1500);
            // Pemuat naik tidak boleh mengesahkan hasil sendiri.
            expect(
                (
                    await siti.api.raw(
                        "POST",
                        `/tsu/hasil-ukur/${results[0].id}/sahkan`,
                        { status_pengesahan: "diterima" },
                    )
                ).status(),
            ).toBe(422);
            await asUser("faizal", async (faizal) => {
                for (const h of results)
                    await faizal.post(`/tsu/hasil-ukur/${h.id}/sahkan`, {
                        status_pengesahan: "diterima",
                        catatan: "Disemak GIS: poligon sah.",
                    });
            });
            await complete(siti.api, id, "kerja_ukur");

            // 6. Pengesahan hasil ukur (pelan ukur).
            await upload(siti.api, id, "pelan_ukur");
            await complete(siti.api, id, "pengesahan_hasil_ukur");

            // 7. Bayaran fi: premium belum dibayar menghalang; selepas dibayar, peringkat selesai.
            u = await urusan(siti.api, id);
            const feeStage = currentStage(u);
            const fee = (
                await siti.api.post(`/tsu/urusan/${id}/kos`, {
                    kategori_kos: "premium",
                    amaun: "1250.00",
                    peringkat_id: feeStage.id,
                    keterangan: "Premium pecah sempadan",
                    no_invois: `INV-${n}`,
                })
            ).data;
            await upload(siti.api, id, "resit");
            expect(
                (
                    await siti.api.raw(
                        "POST",
                        `/tsu/urusan/${id}/peringkat/selesai`,
                        {},
                    )
                ).status(),
            ).toBe(422);
            await siti.api.put(`/tsu/kos/${fee.id}`, {
                status_bayaran: "dibayar",
                no_resit: `RES-${n}`,
                tarikh_bayaran: today(),
            });
            await complete(siti.api, id, "bayaran_fi");

            // 8. Daftar lot baharu: lot pengganti dicipta dalam PRO, lot asal diarkibkan, salasilah direkod.
            await upload(siti.api, id, "pelan_akui");
            await complete(siti.api, id, "daftar_lot_baharu");
            u = await urusan(siti.api, id);
            const baharu = (u.lot as Json[]).filter(
                (l) => l.peranan_lot === "baharu",
            );
            expect(baharu.map((l) => l.no_lot).sort()).toEqual(
                [noBaru1, noBaru2].sort(),
            );
            expect(
                (u.lot as Json[]).find((l) => l.peranan_lot === "asal")
                    .status_lot,
            ).toBe("arkib");
            expect(
                (u.sejarah_pecahan as Json[]).filter(
                    (s) =>
                        s.id_lot_asal === lot.id &&
                        s.jenis_perubahan === "pecah_sempadan",
                ),
            ).toHaveLength(2);
            expect(currentStage(u).kod).toBe("pemindahan_rekod");

            for (const b of baharu) {
                const pl = (await siti.api.get(`/pro/lot/${b.id}`)).data;
                expect(pl.no_lot).toBe(b.no_lot);
                expect(pl.kod_mukim).toBe("100801");
                expect(pl.status_lot).toBe("aktif");
                expect(pl.spatial.status).not.toBe("tiada_geometri");
            }
            expect(
                (await siti.api.get(`/pro/lot/${lot.id}`)).data.status_lot,
            ).toBe("arkib");

            // 9. Pemindahan rekod: akaun cukai lot asal → lot baharu pertama, disahkan oleh dua pegawai berbeza.
            const screen = (await admin.api.get(`/tsu/urusan/${id}/pemindahan`))
                .data;
            const tax = (screen.lot_asal[0].rekod as Json[]).find(
                (r) =>
                    r.jenis_rekod === "obligasi_cukai" &&
                    String(r.id_rekod) === String(obligasi.id),
            );
            expect(
                tax,
                "akaun cukai lot asal dalam skrin pemindahan",
            ).toBeTruthy();
            const target = baharu.find((l) => l.no_lot === noBaru1);
            await admin.api.post(`/tsu/urusan/${id}/pemindahan`, {
                pemetaan: [
                    {
                        jenis_rekod: "obligasi_cukai",
                        id_rekod: String(tax.id_rekod),
                        id_lot_asal: lot.id,
                        id_lot_baharu: target.id,
                    },
                ],
            });
            // Peringkat pemindahan tidak boleh selesai selagi pemetaan belum disahkan.
            expect(
                (
                    await siti.api.raw(
                        "POST",
                        `/tsu/urusan/${id}/peringkat/selesai`,
                        {},
                    )
                ).status(),
            ).toBe(422);
            const transfer = (
                await admin.api.post(`/tsu/urusan/${id}/pemindahan/hantar`, {})
            ).data;
            expect(
                await approveAll(transfer.id_aliran_kerja, ["siti", "faizal"]),
            ).toEqual(["siti:pengesahan_1", "faizal:pengesahan_2"]);
            expect(
                await instanceStatus(admin.api, transfer.id_aliran_kerja),
            ).toBe("lulus");
            const mapped = (await admin.api.get(`/tsu/urusan/${id}/pemindahan`))
                .data.pemetaan as Json[];
            const done = mapped.find(
                (m) => m.status_pemindahan === "dilaksanakan",
            );
            expect(done, JSON.stringify(mapped)).toBeTruthy();
            expect(done.id_pengesah_1).not.toBe(done.id_pengesah_2);
            // CKI: akaun cukai kini milik lot baharu.
            const onNew = rows(
                await siti.api.get(`/cki/obligasi?lot_id=${target.id}`),
            );
            expect(onNew.map((o: Json) => o.id)).toContain(obligasi.id);
            expect(
                rows(await siti.api.get(`/cki/obligasi?lot_id=${lot.id}`)).map(
                    (o: Json) => o.id,
                ),
            ).not.toContain(obligasi.id);

            await complete(siti.api, id, "pemindahan_rekod");
            u = await urusan(siti.api, id);
            expect(u.status).toBe("selesai");

            // 10. Tutup & kunci urusan (SRS-TSU-24): tiada perubahan lagi.
            await siti.api.post(`/tsu/urusan/${id}/tutup`, {
                catatan: "Pecah sempadan selesai; rekod dipindahkan.",
            });
            u = await urusan(siti.api, id);
            expect(u.status).toBe("ditutup");
            expect(
                (
                    await siti.api.raw("PUT", `/tsu/urusan/${id}`, {
                        tajuk: "Cubaan ubah selepas tutup",
                    })
                ).status(),
            ).toBe(409);

            // 11. PRO 360°: salasilah dua hala dalam seksyen urusan_tanah lot baharu dan lot asal.
            const profilBaru = (
                await siti.api.get(`/pro/lot/${target.id}/profil`)
            ).data;
            const sekBaru = (profilBaru.tab as Json[])
                .flatMap((t) => t.seksyen)
                .find((s: Json) => s.key === "urusan_tanah");
            expect(JSON.stringify(sekBaru.items)).toContain(noAsal);
            const profilAsal = (await siti.api.get(`/pro/lot/${lot.id}/profil`))
                .data;
            const sekAsal = (profilAsal.tab as Json[])
                .flatMap((t) => t.seksyen)
                .find((s: Json) => s.key === "urusan_tanah");
            expect(JSON.stringify(sekAsal.items)).toContain(noBaru1);
            expect(JSON.stringify(sekAsal.items)).toContain(noBaru2);
            expect(JSON.stringify(sekAsal.flags)).toContain("Lot asal");

            // 12. UI: pegawai daerah melihat lot baharu (profil 360°) dan salasilahnya.
            const page = siti.page;
            await page.goto(`/admin/pro/lot/${target.id}?tab=risiko`);
            await expect(
                page.getByRole("heading", { name: noBaru1, exact: true }),
            ).toBeVisible({ timeout: 30_000 });
            await expect(page.getByText(noAsal).first()).toBeVisible();
            await page.goto(`/admin/pro/lot/${lot.id}`);
            await expect(
                page.getByRole("heading", { name: noAsal, exact: true }),
            ).toBeVisible({ timeout: 30_000 });
            await expect(page.getByText("Diarkib").first()).toBeVisible();
            await page.goto(`/admin/tsu/urusan/${id}`);
            await expect(page.getByText(u.no_rujukan_mais).first()).toBeVisible(
                { timeout: 30_000 },
            );
            await expect(page.getByText(noBaru2).first()).toBeVisible();
        } finally {
            for (const s of [siti, admin]) {
                await logout(s.api);
                await s.context.close();
            }
        }
    });
});
