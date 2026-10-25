import { expect, request as pwRequest, test } from "@playwright/test";
import type { APIRequestContext } from "@playwright/test";
import type { Json } from "./helpers";
import { BASE_URL, rows, signIn, uniq } from "./helpers";
import { logout } from "./_per";

/**
 * UAT i: Pendaftaran wakaf melalui Portal Hartanah Wakaf Selangor — PWS → PER.
 *
 * Cerita: Pentadbir SISDATA mendaftar klien API untuk pelayan portal (WordPress,
 * sistem berasingan). Pelayan portal menukar kelayakan klien kepada token
 * pembawa jangka pendek (client credentials) dan menghantar borang ikrar wakaf
 * seorang pewakaf. MOD-PWS mengeluarkan rujukan tunggal PHWS-…, menolak
 * permohonan ke MOD-PER (saluran wakaf portal) sebagai kes "Permohonan awam
 * menunggu semakan". Pegawai Bahagian Wakaf (mohd.faizal) menemui kes dalam
 * senarai PER, membukanya dan menerima permohonan; status itu dikembalikan
 * kepada portal (semakan status awam dengan rujukan + e-mel, dan suapan
 * perubahan). Akhirnya klien API dibatalkan dan token serta-merta ditolak.
 *
 * SRS: PWS-02/23/24 (API awam, klien, token, skop), PWS-07 (permohonan wakaf),
 * PWS-09 (semakan status tanpa enumerasi), PWS-21 (status dua hala),
 * PWS-22 (log integrasi / pemantauan), INT-03/05/13, PER-12/25 (permohonan awam
 * portal wakaf + semakan), SEC (pembatalan kunci API).
 */

type Portal = { ctx: APIRequestContext; token: string };

/** The portal server: no SISDATA session, only the bearer token from the client-credentials grant. */
async function portalLogin(clientId: string, secret: string): Promise<Portal> {
    const ctx = await pwRequest.newContext({
        baseURL: BASE_URL,
        extraHTTPHeaders: { Accept: "application/json" },
    });
    const res = await ctx.post("/api/v1/pws/awam/token", {
        data: {
            grant_type: "client_credentials",
            client_id: clientId,
            client_secret: secret,
        },
    });
    expect(res.status(), await res.text()).toBe(200);
    const body = await res.json();
    expect(body.token_type).toBe("Bearer");
    expect(body.scope).toContain("permohonan");
    return { ctx, token: body.access_token };
}

function portalPost(
    p: Portal,
    path: string,
    data: unknown,
    headers: Record<string, string> = {},
) {
    return p.ctx.post(`/api/v1/pws/awam/${path}`, {
        data,
        headers: {
            Authorization: `Bearer ${p.token}`,
            "X-Pengguna-IP": "203.0.113.25",
            ...headers,
        },
    });
}

test.describe("UAT i: permohonan wakaf portal awam (PWS) → kes PER → semakan pegawai → status kembali ke portal", () => {
    test("portal menghantar ikrar wakaf, pegawai wakaf menerimanya dalam PER, status dipaparkan semula melalui API awam", async ({
        browser,
    }) => {
        const admin = await signIn(browser, "admin");
        const since = new Date(Date.now() - 60_000).toISOString();

        // 1. Pentadbir mencipta klien API portal (rahsia dipaparkan sekali sahaja).
        const klien = (
            await admin.api.post("/pws/klien", {
                nama: `Portal Wakaf ${uniq("UAT-i")}`,
                skop: ["baca", "permohonan"],
                had_kadar_minit: 600,
            })
        ).data;
        expect(klien.id_klien).toMatch(/^phws_/);
        expect(klien.client_secret).toMatch(/^phws_s_/);
        const listed = rows(await admin.api.get("/pws/klien")).find(
            (k: Json) => k.id === klien.id,
        );
        expect(listed.client_secret).toBeUndefined();

        // 2. Pelayan portal: token client-credentials; kelayakan salah → 401 generik.
        const bad = await (
            await pwRequest.newContext({ baseURL: BASE_URL })
        ).post("/api/v1/pws/awam/token", {
            data: {
                grant_type: "client_credentials",
                client_id: klien.id_klien,
                client_secret: "salah",
            },
        });
        expect(bad.status()).toBe(401);
        const portal = await portalLogin(klien.id_klien, klien.client_secret);
        try {
            // Skop: klien ini tiada skop "bayaran".
            const noScope = await portal.ctx.get(
                "/api/v1/pws/awam/bayaran/saluran",
                { headers: { Authorization: `Bearer ${portal.token}` } },
            );
            expect(noScope.status()).toBe(403);

            // 3. Hantar ikrar wakaf (Idempotency-Key wajib bagi panggilan yang mengubah keadaan).
            const emel = `pewakaf.${Date.now()}@contoh.my`;
            const idem = uniq("IDEM");
            const form = {
                nama_pewakaf: "Haji Ahmad bin Ismail",
                jenis_pemohon: "individu",
                no_pengenalan: "600101105511",
                emel,
                jenis_wakaf: "am",
                tujuan_wakaf:
                    "Tapak surau dan kelas fardu ain untuk penduduk setempat.",
                no_lot: `Lot ${1_000_000 + Math.floor(Math.random() * 8_999_999)}`,
                kod_mukim: "100801",
                nilai_anggaran: 350000,
                persetujuan_pdpa: true,
            };
            const sent = await portalPost(portal, "permohonan/wakaf", form, {
                "Idempotency-Key": idem,
            });
            expect(sent.status(), await sent.text()).toBe(201);
            const app = (await sent.json()).data;
            expect(app.no_rujukan).toMatch(/^PHWS-\d{4}-[A-Z0-9]{7}$/);
            expect(app.jenis).toBe("wakaf");
            expect(app.diterima_sisdata).toBe(true);
            expect(app.status).toBe("menunggu_semakan");
            const noKes: string = app.rujukan_sisdata;
            expect(noKes).toMatch(/^PER\/\d{4}\/\d{5}$/);
            // Tiada data peribadi dalam respons awam.
            expect(JSON.stringify(app)).not.toContain("600101105511");

            // Ulangan dengan kunci idempotensi yang sama tidak mencipta permohonan kedua.
            const replay = await portalPost(portal, "permohonan/wakaf", form, {
                "Idempotency-Key": idem,
            });
            expect((await replay.json()).data.no_rujukan).toBe(app.no_rujukan);

            // 4. Semakan status awam: rujukan + e-mel; e-mel salah → 404 yang sama (tiada enumerasi).
            const st = await portalPost(portal, "permohonan/status", {
                no_rujukan: app.no_rujukan,
                emel,
            });
            expect(st.status()).toBe(200);
            expect((await st.json()).data.status).toBe("menunggu_semakan");
            expect(
                (
                    await portalPost(portal, "permohonan/status", {
                        no_rujukan: app.no_rujukan,
                        emel: "orang.lain@contoh.my",
                    })
                ).status(),
            ).toBe(404);

            // 5. PER: kes wakaf baharu daripada portal, menunggu semakan, lot dijangka (belum didaftar dalam PRO).
            const faizal = await signIn(browser, "faizal");
            const found = rows(
                await faizal.api.get(`/per/kes?q=${encodeURIComponent(noKes)}`),
            );
            expect(found).toHaveLength(1);
            const kesId = found[0].id;
            const kes = (await faizal.api.get(`/per/kes/${kesId}`)).data;
            expect(kes.saluran.kod).toBe("wakaf");
            expect(kes.status).toBe("permohonan_awam_menunggu_semakan");
            expect(kes.sumber_rekod).toBe("portal_wakaf");
            expect(kes.rujukan_luaran).toBe(app.no_rujukan);
            expect(kes.lot.berdaftar).toBe(false);
            // Data peribadi pewakaf (Sulit) dikaburkan bagi pegawai tanpa per.sulit.lihat; pentadbir melihatnya penuh.
            if (kes.peribadi_dikaburkan)
                expect(kes.butiran.nama_pewakaf).toBe("H*** A**** b** I*****");
            expect(
                (await admin.api.get(`/per/kes/${kesId}`)).data.butiran
                    .nama_pewakaf,
            ).toBe(form.nama_pewakaf);
            expect(
                (kes.pihak as Json[]).some(
                    (p) => p.peranan_pihak === "pewakaf",
                ),
            ).toBe(true);
            // Kes tidak boleh dimajukan sebelum disemak (SRS-PER-25).
            expect(
                (
                    await faizal.api.raw("POST", `/per/kes/${kesId}/maju`, {})
                ).status(),
            ).toBe(409);

            // 6. UI: pegawai wakaf mencari kes dalam senarai PER dan menerimanya.
            const page = faizal.page;
            await page.goto(
                "/admin/per?status=permohonan_awam_menunggu_semakan",
            );
            await page
                .getByPlaceholder("Cari no. kes, tajuk atau no. lot…")
                .fill(noKes);
            const link = page
                .getByRole("link", {
                    name: new RegExp(noKes.replace(/\//g, "\\/")),
                })
                .first();
            await expect(link).toBeVisible({ timeout: 30_000 });
            await link.click();
            await expect(page).toHaveURL(new RegExp(`/admin/per/kes/${kesId}`));
            await expect(
                page.getByText("Permohonan awam menunggu semakan").first(),
            ).toBeVisible({ timeout: 30_000 });
            await page
                .getByRole("button", { name: "Terima permohonan" })
                .click();
            await expect(
                page.getByText("Permohonan diterima untuk diproses."),
            ).toBeVisible();
            await expect(page.getByText("Dalam proses").first()).toBeVisible();

            const accepted = (await faizal.api.get(`/per/kes/${kesId}`)).data;
            expect(accepted.status).toBe("dalam_proses");
            expect(accepted.peringkat_semasa.kod).toBe("penerimaan_ikrar");
            expect(accepted.pendaftar?.nama).toBeTruthy();
            await logout(faizal.api);
            await faizal.context.close();

            // 7. Status kembali ke portal (dua hala): diterima, dengan sejarah penuh, dan dalam suapan perubahan.
            const after = (
                await (
                    await portalPost(portal, "permohonan/status", {
                        no_rujukan: app.no_rujukan,
                        emel,
                    })
                ).json()
            ).data;
            expect(after.status).toBe("diterima");
            expect((after.sejarah as Json[]).map((h) => h.status)).toEqual(
                expect.arrayContaining([
                    "dihantar",
                    "menunggu_semakan",
                    "diterima",
                ]),
            );
            const changes = await portal.ctx.get(
                `/api/v1/pws/awam/permohonan/perubahan?sejak=${encodeURIComponent(since)}`,
                { headers: { Authorization: `Bearer ${portal.token}` } },
            );
            expect(changes.status()).toBe(200);
            expect(
                ((await changes.json()).data as Json[]).find(
                    (c) => c.no_rujukan === app.no_rujukan,
                )?.status,
            ).toBe("diterima");

            // 8. Pemantauan PWS (pentadbir): permohonan dan log integrasi direkodkan.
            const mon = rows(
                await admin.api.get(
                    `/pws/permohonan?q=${encodeURIComponent(app.no_rujukan)}`,
                ),
            );
            expect(JSON.stringify(mon)).toContain(app.no_rujukan);
            const log = JSON.stringify(
                await admin.api.get("/pws/log-integrasi?per_page=100"),
            );
            expect(log).toContain("permohonan/wakaf");

            // 9. Pembatalan klien: token sedia ada serta-merta ditolak.
            await admin.api.post(`/pws/klien/${klien.id}/batal`, {
                sebab: "Tamat ujian UAT i.",
            });
            expect(
                (
                    await portalPost(portal, "permohonan/status", {
                        no_rujukan: app.no_rujukan,
                        emel,
                    })
                ).status(),
            ).toBe(401);
        } finally {
            await portal.ctx.dispose();
            await logout(admin.api);
            await admin.context.close();
        }
    });
});
