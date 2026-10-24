# Pelan Pembangunan SISDATA — 22 Modul

Disediakan: 24 Okt 2026 · Rujukan: *SRS SISDATA* (22 Okt 2026, 572 keperluan) · Asas kod: repo ini (`MYDS-Laravel`)

Dokumen ini menukar SRS kepada pelan kerja yang boleh dilaksanakan: susunan pembinaan, pecahan setiap modul kepada kepingan kerja (slice), kebergantungan, kriteria siap, dan isu dalam SRS yang perlu diputuskan dulu.

---

## 1. Titik mula — apa yang ada vs apa yang SRS minta

| Perkara | Ada sekarang dalam repo | SRS minta | Jurang |
| --- | --- | --- | --- |
| Backend | Laravel 13, API JSON tulen, Sanctum SPA | Monolit modular, API-first, OpenAPI 3.0 berversi | Struktur modul, versi API, OpenAPI belum ada |
| Frontend | Next.js 16 + MYDS (`@govtechmy/myds-style`), ~40 komponen `components/ui/` | MyDS + WCAG 2.1 AA, responsif, BM | Asas sudah betul — guna semula Kitchen Sink |
| Auth | Login e-mel/kata laluan, `users.role` (string) + `is_active` | SSO MAIS (OIDC), MFA, RBAC 4 dimensi (peranan × daerah × kategori data × tindakan) | Hampir semua — kena bina semula lapisan capaian |
| DB | PostgreSQL 17 lokal (`myds_laravel`), `vector` 0.8.3 & `pg_trgm` tersedia | PostgreSQL + **PostGIS** + pgvector dalam satu DB (ADR-04) | **PostGIS belum dipasang** |
| Audit / Workflow / Notifikasi / DMS | Tiada (NotificationBell UI sahaja) | Enjin platform G0 | Bina dari kosong |
| Ujian | PHPUnit (SQLite in-memory), Playwright e2e | Unit, SIT, UAT, prestasi, keselamatan, RTM | Ujian kena tukar ke PostgreSQL (PostGIS/pgvector tak jalan atas SQLite) |

**Kesimpulan:** repo ini sesuai sebagai rangka (shell UI MYDS + API Laravel), tapi ~95% SRS masih belum dibina. Gelombang G0 bukan sekadar "6 modul" — ia juga kerja asas seni bina.

---

## 2. Keputusan teknikal (cadangan — sahkan sebelum mula)

| Perkara | Cadangan | Sebab |
| --- | --- | --- |
| Struktur monolit modular | `app/Modules/<Kod>/` (cth. `Modules/Dms`, `Modules/Wfe`) — setiap satu ada `Models/`, `Http/`, `Services/`, `Policies/`, `Events/`, `Database/migrations/`, `routes.php`, `ModuleServiceProvider` | ADR-03. Sempadan dikuatkuasa melalui ujian seni bina (modul hanya panggil modul lain melalui `Contracts/` atau event) |
| Paksi data | Jadual `lot_tanah` + `lot_id` (UUID) sebagai FK semua satelit | ADR-01 |
| Spatial | PostGIS 3.x; simpan SRID 4326 + transform ke GDM2000/Cassini bila perlu | ADR-04, SRS-GIS-03 |
| Perkhidmatan peta OGC | Lokal: **GeoServer** (WMS/WFS/WMTS) atas PostGIS sebagai pengganti SuperMap; frontend **MapLibre GL** | SRS-GIS-04 sebut SuperMap/setara dibekal vendor — dev tak boleh tunggu lesen |
| Vektor / RAG | pgvector dalam DB yang sama; LLM & embedding lokal (Ollama dari `~/rag-stack`) di belakang satu `AiGateway` interface | SRS-KBA-06, SRS-DSH-18 (tiada AI awam) |
| Auth | Fasa dev: **Keycloak lokal** sebagai IdP OIDC tiruan SSO MAIS; Sanctum kekal utk token API mesin. MFA melalui IdP (TOTP) + step-up claim `acr` | SRS-RBC-01/03 |
| RBAC | Jadual `peranan`, `peranan_kebenaran`, `pengguna_skop` sendiri + Laravel Policies + **global scope Eloquent** utk skop daerah. Tambah **PostgreSQL Row-Level Security** pada replika baca utk BI | ADR-06, SRS-RBC-08, SRS-LAP-08 |
| Audit | Jadual append-only `audit_rekod` + rantaian SHA-256 (`cincangan_sebelum`), akaun DB aplikasi tiada `UPDATE/DELETE`; dipintas melalui Eloquent observer global + middleware | SRS-AUD-06/08/09 |
| Workflow | Enjin sendiri (definisi JSON berversi → instans → tugasan), editor visual guna React Flow | SRS-WFE-03/09; elak enjin BPMN berat |
| Queue / jadual | Redis + Laravel Horizon; `schedule:run` utk job scheduler, dibungkus dalam `ADM_JOB` | SRS-ADM-06 |
| Storan dokumen | S3 (lokal: MinIO), SSE AES-256; antivirus ClamAV; OCR Tesseract; carian teks penuh PostgreSQL `tsvector` | SRS-DMS-07/12/20 |
| BI | Metabase (sahkan vs Superset di CRP-2) baca dari replika + RLS | SRS-LAP-01 |
| Portal PWS | WordPress berasingan (repo/domain lain), panggil SISDATA melalui API Gateway sahaja | SRS-PWS-02/03 |
| PWA lapangan | Laluan `/lapangan` dalam Next.js yang sama + service worker + IndexedDB tersulit | SRS-PTN-05, SRS-AST-20 |
| API docs | `dedoc/scramble` jana OpenAPI 3.0 dari kod; awalan `/api/v1` | SRS-INT-02 |
| Ujian | Tukar PHPUnit ke PostgreSQL (`myds_laravel_test`); nama ujian bawa kod SRS (`test_SRS_DMS_04_...`) supaya RTM boleh dijana automatik | Bab 16, 19.5 |

---

## 3. Fasa & susunan (ikut kebergantungan, bukan nombor modul)

```
F0 Asas teknikal ─► G0 Platform ─► G1 Teras ─┬─► G2 Ruang (GIS, DSH)
                                             ├─► G3 Operasi (PJK, CKI, PTN, ADU)
                                             └─► G4 Undang & aset (KES, TSU, AST)
                                                        └─► G5 Wawasan & portal (LAP, PWS, TKT, KBA)
Trek data (migrasi) berjalan selari dari F0 hingga cutover (ADR-07)
```

Anggaran dalam **sprint 2 minggu**. SRS beri setiap gelombang hanya ~0.5 bulan (Bulan 3.0–5.5) — itu tak realistik utk 572 keperluan; angka di bawah ialah anggaran jujur kerja pembangunan sebenar.

| Fasa | Modul | Keperluan | Sprint anggaran | Boleh selari dengan |
| --- | --- | --- | --- | --- |
| F0 | Asas teknikal | — | 1–2 | — |
| G0 | DMS, WFE, NTF, ADM, RBC, AUD | 156 | 5–6 | Trek data |
| G1 | PRO, HKM, PER | 82 | 3–4 | — |
| G2 | GIS, DSH | 60 | 3 | G3 |
| G3 | PJK, CKI, PTN, ADU | 103 | 4–5 | G2, G4 |
| G4 | KES, TSU, AST | 77 | 3 | G3 |
| G5 | LAP, PWS, TKT, KBA | 94 | 4 | — |
| | **Jumlah** | **572** | **~23–27 sprint** (bersiri penuh); ~16–18 jika G2/G3/G4 selari | |

---

## 4. Fasa F0 — Asas teknikal (1–2 sprint)

Tiada fungsi pengguna; semua modul lain bergantung padanya.

1. Pasang PostGIS dalam PG17 lokal; `CREATE EXTENSION postgis, vector, pg_trgm` dalam migrasi.
2. Rangka `app/Modules/*` + `ModuleServiceProvider` autoload + ujian seni bina (modul tak boleh import model modul lain secara terus).
3. Awalan `/api/v1`, format ralat seragam (tiada stack trace kepada pengguna — SRS-ADM-13), ID korelasi setiap request (SRS-ADM-12).
4. Keycloak lokal (Docker) + login OIDC di Next.js; kekalkan login lama di belakang flag utk dev.
5. Redis + Horizon, MinIO, ClamAV, GeoServer dalam `docker-compose.dev.yml`.
6. PHPUnit atas PostgreSQL; CI: Pint, PHPUnit, `tsc`, ESLint, SAST (Larastan + Semgrep), `composer audit`/`npm audit`, gitleaks (SRS-ADM-23/24).
7. Kerangka BM: semua label/mesej dalam `lang/ms` (SRS-GEN-09).
8. Lapisan frontend: menu modul ikut kebenaran (tutup jurang "role-based UI hiding" yang tercatat dalam README).

**Siap bila:** `composer run dev` naik semua servis, satu modul kosong contoh lulus ujian seni bina, CI hijau.

---

## 5. G0 — Perkhidmatan Platform (156 keperluan)

Susunan dalaman penting: **RBC → AUD → ADM → NTF → DMS → WFE**. Audit & RBAC mesti wujud sebelum modul lain menulis data, supaya tiada rekod tanpa jejak.

### 5.1 MOD-RBC · Pengguna, Peranan, Capaian (27)
| Slice | Isi | Kod SRS utama |
| --- | --- | --- |
| RBC-a | SSO OIDC, pemetaan tuntutan → peranan, status akaun 4 nilai, auto Tamat Tempoh/Gantung | 01, 02, 15, 16 |
| RBC-b | Peranan dinamik + 7 peranan asas seed, kebenaran modul×tindakan, skop daerah/bahagian/klasifikasi, least-privilege | 04–09 |
| RBC-c | Penilaian kebenaran pusat (`Gate` + global scope) dipakai API, eksport, GIS, BI | 08, GEN-01 |
| RBC-d | Sesi: timeout 15 min, had serentak, tamat paksa, pengikatan sesi, cookie flags | 17–20 |
| RBC-e | Akaun dalaman: polisi kata laluan, kunci 5 cubaan, hash | 11–14 |
| RBC-f | Log log masuk, amaran mencurigakan, laporan & eksport matriks capaian, recertification | 10, 21, 22, 26, 27 |

### 5.2 MOD-AUD · Audit Trail (26)
- AUD-a: observer global (cipta/kemas kini/padam/lihat sensitif) medan demi medan, konteks penuh (peranan semasa, sesi, IP, saluran) — 01–05, 09
- AUD-b: append-only + rantaian cincangan + job semakan integriti + amaran — 06–08, 26
- AUD-c: carian, kronologi per rekod, eksport 4 format + checksum, audit atas audit — 10–15
- AUD-d: retensi 24 bulan + arkib, NTP/UTC+8, tapis medan sensitif, strim SIEM (format JSON/CEF) — 20–25

### 5.3 MOD-ADM · Pentadbiran (29)
- ADM-a: parameter, kod rujukan (MDM berversi), templat — 01–03 (+DAT-05, PRO-25)
- ADM-b: konfigurasi kritikal dengan kelulusan pentadbir kedua (bergantung WFE ringkas) — 18, 19
- ADM-c: job scheduler + sejarah larian + anti-serentak + amaran gagal — 06–10
- ADM-d: log ralat & integrasi, skrin kesihatan, amaran ICT — 11–17
- ADM-e: carian global berpusat + resolver pautan dalam (drill-down) — 20–22
- ADM-f: secret store, WAF/X-Forwarded-For, mod penyelenggaraan, API pentadbiran — 04, 05, 26–29

### 5.4 MOD-NTF · Notifikasi (24)
Enjin tunggal: jenis → peraturan → templat (medan dinamik BM) → baris gilir → saluran (in-app, e-mel; antara muka SMS/WhatsApp kosong). Status hantar/terima/baca, retry 3× backoff, eskalasi Kritikal, pendiaman, ringkasan berkala. Ganti `NotificationBell` UI-only dengan backend sebenar.

### 5.5 MOD-DMS · Dokumen Digital (26)
- DMS-a: muat naik (seret-lepas 20 fail), senarai putih + magic number + sekatan mutlak, had saiz, ClamAV + kuarantin — 02–09
- DMS-b: metadata boleh konfigurasi, pautan polimorfik ke rekod induk, versi, kebenaran ikut klasifikasi — 10, 11, 14, 16, 19
- DMS-c: OCR + carian teks penuh tertapis kebenaran — 12, 13
- DMS-d: retensi/arkib automatik, pelupusan 2 peringkat (perlu WFE), URL bertempoh, tanda air — 15, 21–23, 26

### 5.6 MOD-WFE · Workflow (24)
- WFE-a: definisi berversi (peringkat bersiri/selari, syarat cabangan, pelulus peranan/kumpulan) + instans + tugasan — 01–05, 12
- WFE-b: SLA hari bekerja (kalendar cuti Selangor), eskalasi 2 aras — 06–08
- WFE-c: tindakan lulus/tolak/kembali/tangguh, justifikasi wajib, pengasingan tugas di enjin, MFA step-up — 16–19
- WFE-d: papan tugasan, dashboard, laporan prestasi, pemangku, tarik balik — 13–15, 21–23
- WFE-e: editor visual React Flow — 09
- WFE-f (lewat, boleh tolak ke G5): workflow melalui arahan AI + kelulusan kedua + log prompt — 10, 11, 24

**Siap G0 bila:** 7 kriteria penerimaan setiap modul dalam SRS Bab 6 lulus sebagai ujian automatik; modul contoh "dummy" boleh guna DMS+WFE+NTF+AUD tanpa kod tambahan.

---

## 6. G1 — Domain Teras (82 keperluan)

| Modul | Slice | Nota |
| --- | --- | --- |
| **MOD-PRO** (30) | PRO-a skema 10 entiti teras + `data_spatial` (PostGIS) + DDL/kamus data dijana | Jadual entiti lain (cukai, pajakan, kes) dicipta sekarang sebagai rangka kosong supaya FK stabil |
| | PRO-b kualiti data: peraturan boleh konfigurasi, keunikan lot/mukim, duplikasi + skor padanan (`pg_trgm`) | SRS-DAT-01/02, PRO-13, PRO-20 |
| | PRO-c import Excel/CSV: templat, validasi baris, pratonton, laporan, simpan fail asal | PRO-15–17 — **guna juga oleh trek migrasi** |
| | PRO-d versioning rekod induk + pengesahan pegawai kedua, stewardship, arkib/retensi | PRO-11, 12, 22, 23 |
| | PRO-e Profil Hartanah 360° (skrin bertab) + garis masa + log akses | PRO-26, 27, 30. Tab modul belum siap papar "belum tersedia" |
| | PRO-f REST API berskop + kunci API | PRO-29, INT-13 |
| **MOD-HKM** (22) | HKM-a pendaftaran hak milik 5 jenis + status + sejarah append-only | 01, 02, 08, 09 |
| | HKM-b peti besi: daftar, pengeluaran/pemulangan, notifikasi lewat | 03–07 |
| | HKM-c waris/pemilik bersama, sekatan kepentingan, perubahan 2 peringkat + MFA | 12, 13, 19 |
| | HKM-d permohonan hak milik kekal, notifikasi tamat 180/90/30, draf dari integrasi | 16–18, 21 |
| **MOD-PER** (30) | PER-a teras kes + 11 saluran (jadual induk + sub-jadual per saluran), senarai semak dokumen ikut saluran | 01, 16, 17, 23 |
| | PER-b saluran 5A penuh (MMKN, notis, bayaran menyekat) | 02–05 |
| | PER-c 10 saluran lain (borang dijana dari konfigurasi medan, bukan 10 skrin tulisan tangan) | 06–15 |
| | PER-d kelulusan berlapis, eskalasi, tutup kes → cipta hak milik + daftar peti besi | 20–22, 26, 29 |

**Siap G1 bila:** satu lot ujian boleh diwujudkan melalui setiap 11 saluran hingga hak milik + peti besi, dan Profil 360° memaparkannya.

---

## 7. G2 — Ruang (60) · selari dengan G3

- **MOD-GIS (35):** GeoServer lapisan dari view PostGIS (penapis RBAC melalui parameter/`CQL` + proksi API — bukan terus dari browser, SRS-GIS-32); 10 lapisan wajib (lapisan yang modul sumbernya belum siap → isi kemudian, tapi gaya & legenda dibina sekarang); 5 analisis spatial (`ST_Buffer`, `ST_Intersects`, `ST_Area` dalam unjuran tepat, `ST_DWithin`); import SHP/GeoJSON/KML/DXF (GDAL `ogr2ogr`) dengan semakan topologi & sempadan Selangor; versi geometri; cetak peta PDF.
  - SRS-GIS-04–07, 35 (LOA, brosur, workstation) ialah serahan **perolehan**, bukan kod — keluarkan dari backlog dev.
- **MOD-DSH (25):** 3 aras ikut peranan; widget dari replika baca; penapis gabungan kekal; 7 jenis visual (guna komponen Data Visual Kitchen Sink); drill-down ≤3 klik ke Profil 360°; inbox tugasan (dari WFE), draf transaksi, bantuan dalam talian. AI prompt (DSH-15–18) tangguh ke G5 bersama enjin AI.

## 8. G3 — Operasi (103)

| Modul | Fokus & risiko |
| --- | --- |
| **MOD-PJK** (28) | Lejar bertaraf kewangan (double-entry ringkas, baki berjalan, tiada padam), jadual caj auto, umur hutang, deposit, payment gateway (mock FPX/kad sebagai adapter), callback + pertanyaan status, resit bernombor siri, rekonsiliasi harian, Dasar Agihan Manfaat berversi. **6 keperluan Jurang** — banyak soalan proses untuk MAIS. |
| **MOD-CKI** (24) | Adapter API berpusat (mock dulu), daftar liputan, **mod sandaran manual** + penyegerakan semula + konflik, 5 kaedah bayaran, kelulusan had nilai, rekonsiliasi GRP. |
| **MOD-PTN** (24) | PWA luar talian (kepingan paling berisiko teknikal), senarai semak boleh konfigurasi, geotag + semakan dalam polygon, laporan auto dikunci, skor risiko 6 kategori + kod warna boleh konfigurasi. Enjin skor risiko dibina sekarang tetapi input CKI/PJK/KES diisi bila modul itu siap. |
| **MOD-ADU** (27) | Portal aduan awam tanpa akaun (domain berasingan, CAPTCHA, had kadar), triaj auto (padanan lot spatial, kata kunci, berulang), SLA hari bekerja (guna semula WFE), privasi pengadu (sulit lajur + audit capaian), API dua hala Sistem Aduan MAIS. **10 keperluan Jurang — tertinggi.** |

## 9. G4 — Undang-undang & Aset (77) · selari dengan G3

- **MOD-KES (24):** kes N:N lot, prosiding + kalendar, peringatan 14/7/1, klasifikasi Rahsia + MFA setiap capaian, kos guaman → graf kos lot, keputusan → tugasan susulan WFE, status lot auto "dalam tindakan undang-undang".
- **MOD-TSU (27):** 7 templat urusan berperingkat (konfigurasi), sebut harga jurukur hingga lantikan, import hasil ukur + banding keluasan, **pecah sempadan/penyatuan → lot baharu + pemindahan rekod 2 peringkat** (sentuh PRO, PJK, CKI, AST — paling banyak kebergantungan silang).
- **MOD-AST (26):** inventori + hierarki aset, QR unik + cetak label kelompok, imbas tanpa app (laluan awam minimum), pemeriksaan melalui PWA PTN, jadual penyelenggaraan + kos ke graf lot, sejarah nilai.

## 10. G5 — Wawasan & Portal (94)

- **MOD-LAP (23):** Metabase + SSO + RLS pada replika; set laporan standard (disahkan CRP-2); laporan berjadual e-mel; eksport + log; analitik (potensi hasil, risiko tunggakan, tanah tidak produktif) — model ringkas berasaskan peraturan dulu, ML kemudian.
- **Enjin AI dikongsi** (KBA-21): satu `AiGateway` utk KBA, DSH-15–18, LAP-13/19/20, WFE-10/11, PWS-04. Bina sekali di sini.
- **MOD-KBA (22):** indeks DMS → chunk → pgvector dengan metadata peranan; **tapis kebenaran pada retrieval**; jawapan bergrounding + petikan; "tiada sumber" bila tak cukup; log prompt; kawalan prompt injection. Boleh guna corak dari `~/rag-stack`.
- **MOD-TKT (21):** butang bantuan global + tangkap konteks, cadangan KBA sebelum hantar, SLA S1–S4, eskalasi, draf artikel ke KBA, API dua hala Sistem Aduan.
- **MOD-PWS (28):** WordPress berasingan + plugin tersuai panggil SISDATA API; hasrat wakaf & sewaan → PER/PJK; bayaran via gateway; paparan Dasar Agihan Manfaat. Boleh dimulakan lebih awal oleh pasukan berasingan selepas API PJK/PER stabil.

---

## 11. Integrasi luar — strategi "adapter + mock"

Semua sistem luar belum ada akses (Andaian A4–A6). Setiap integrasi dibina sebagai **adapter** (SRS-INT-11) dengan 2 implementasi: `Mock` (data palsu realistik, boleh simulasi gagal/lambat) dan `Live` (bila akses diberi). Ini membolehkan mod sandaran manual & circuit breaker diuji tanpa sistem sebenar.

| Integrasi | Modul | Bila perlu mock |
| --- | --- | --- |
| SSO MAIS (OIDC) | RBC | F0 (Keycloak) |
| Gateway e-mel MAIS | NTF | G0 (Mailpit lokal) |
| GRP MAIS, e-Sewa | PJK, CKI, PER | G1/G3 |
| Penyedia berpusat PBT/PTD (MBI) | CKI, TSU, HKM | G3 |
| Payment gateway (FPX/kad) | PJK, PWS | G3 |
| e-Faraid | PER, HKM | G1 |
| Sistem Aduan berpusat | ADU, TKT | G3/G5 |
| JUPEM/JPBD/MyGDI/MyLand | GIS | G2 (fail contoh) |
| SIEM, WAF | AUD, ADM | G0 (format log sahaja) |

## 12. Trek data / migrasi (selari dari F0)

Ikut SRS Bab 15. Kerja dev yang perlu: jadual `staging_*` + `migrasi_larian` + lineage (SRS-DAT-08), skrip import idempoten berversi (guna semula enjin import PRO-c), baris gilir pengecualian sebagai senarai tindakan dalam UI, laporan jumlah kawalan. **Perlukan sampel data sebenar MAIS secepat mungkin** — tanpanya format nombor lot & peraturan penormalan hanya tekaan.

## 13. Definisi "Siap" setiap slice

1. Setiap keperluan dalam slice ada sekurang-kurangnya satu ujian bernama kod SRS (`SRS_XXX_NN`).
2. Kebenaran diuji melalui API terus (bukan UI sahaja) — termasuk kes "pegawai daerah A cuba lot daerah B".
3. Setiap tindakan tulis muncul dalam `audit_rekod`.
4. Label/mesej BM, skrin lulus semakan WCAG (axe dalam Playwright), tiada skrol mendatar di 375px.
5. `php artisan test`, `tsc`, `eslint`, e2e lulus; OpenAPI dikemas kini.
6. Skrip `artisan srs:rtm` jana status RTM (kod SRS → ujian → lulus/gagal) — serahan Bab 19.6 dijana, bukan ditulis tangan.

---

## 14. Isu & percanggahan dalam SRS (perlu diputuskan/dibetulkan)

| # | Isu | Cadangan |
| --- | --- | --- |
| 1 | **Jadual masa:** setiap gelombang 0.5 bulan; G0 (156 keperluan, 6 enjin) dalam 2 minggu tidak boleh dicapai | Semak semula jadual bersama MAIS atau kurangkan skop Perlu/Baik-ada pada Go-Live |
| 2 | Nama entiti tak konsisten: Bab 4 `kontrak_pajakan`, `cukai`, `kes_mahkamah`; Bab 9 DSH guna `pajakan_kontrak`, `cukai_tanah`, `kes_perundangan`, `penilaian_hartanah` | Seragamkan ikut Bab 4 dalam SRS v1.1 |
| 3 | Susunan bab: Modul 3 & 4 (G1, Domain Teras) diletak dalam Bab 10 selepas PWS; Modul 6–8 selepas Modul 4 dalam Bab 10 — bertentangan dengan jadual Bab 5 | Pindah ke Bab 7/8 |
| 4 | Bab 18.4 rujuk integrasi "dalam Bab 14" — integrasi sebenarnya Bab 11 | Betulkan rujukan |
| 5 | SRS-INT-15 (OGC) & SRS-GIS-22 (import fail) kedua-duanya rujuk A-14.08 | Semak pemetaan Lampiran H |
| 6 | SRS-GIS-04–07, 35, SRS-PRO-02, SRS-ADM-25, SRS-LAP-22 ialah serahan dokumen/perolehan, bukan fungsi sistem | Pisah ke senarai serahan; jangan kira dalam backlog dev |
| 7 | SRS-PWS-03 tetapkan WordPress — stack kedua yang perlu dihardening & di-VAPT berasingan | Sahkan memang dikehendaki RFP; jika tidak, bina portal dalam Next.js |
| 8 | SRS-RBC-12 kata laluan luput 90 hari (bercanggah dengan amalan NIST semasa) | Kekal kerana RFP, tetapi boleh dikonfigurasi |
| 9 | SLA sokongan (Bab 18.2) tiada angka sebenar — semua "mengikut kontrak" | Perlu angka sebelum Modul T1 diuji |
| 10 | Dasar Agihan Manfaat (PJK-22–25): formula & penerima manfaat tak dinyatakan | Perlu dokumen dasar MAIS sebelum G3 |

## 15. Keputusan (dijawab 2026-10-24)

1. **Bina dalam repo ini** (`MYDS-Laravel`).
2. `app/Modules/<Nama>` — digunakan.
3. **Homebrew/native sahaja**, tiada Docker (CI boleh guna container).
4. Sampel data: **scrape sendiri dari internet** — data awam sahaja (daerah/mukim, masjid, sempadan); data peribadi (nama pemilik, No. KP) dijana sintetik (PDPA).
5. **Terus F0.**

### Status F0 — siap (2026-10-24, belum commit)

| Langkah | Status |
| --- | --- |
| 1 PostGIS 3.6 + pgvector + pg_trgm, migrasi extension | ✔ |
| 2 `app/Modules` + `ModuleServiceProvider` + ujian seni bina, modul contoh `Platform` | ✔ |
| 3 `/api/v1`, format ralat seragam (ADM-13), `X-Request-Id` (ADM-12) | ✔ |
| 4 Keycloak 26 lokal (realm `mais`, TOTP) + login OIDC (RBC-01/03), login kata laluan di belakang flag | ✔ diuji browser |
| 5 Redis+Horizon, MinIO, ClamAV, GeoServer, Mailpit — `infra/bin/sisdata-services.sh` (native) | ✔ |
| 6 PHPUnit atas Postgres; CI: Pint, Larastan L5, PHPUnit, tsc, ESLint, Semgrep, gitleaks, audit | ✔ (CI belum dijalankan di GitHub) |
| 7 BM sebagai bahasa sistem (`lang/ms`) | ✔ backend; label frontend masih BI |
| 8 Menu ikut kebenaran | ✔ (peta interim `App\Support\Permissions` sehingga MOD-RBC) |
| + `php artisan srs:rtm` | ✔ (belum import 572 kod SRS sebagai checklist) |

Juga: Laravel 13.24→13.38 & Next 16.3→16.4.3 kerana advisory kritikal.

### Status G0 — dibina & digabung (2026-10-24, belum commit)

Dibina serentak oleh 7 "developer" (subagent, satu worktree setiap seorang) atas kontrak `app/Modules/*/Contracts` yang ditetapkan dahulu, kemudian digabung + satu pusingan integrasi. 895 ujian lulus; `composer lint` bersih.

| Modul | Kod SRS ada ujian | Ditangguh |
| --- | --- | --- |
| RBC | 25/27 | 23 (TLS/AES — infrastruktur), 24/25 (proses OWASP/dasar) |
| AUD | 26/26 | — |
| ADM | 28/29 | 25 (VAPT — serahan perolehan); DAST belum dalam CI |
| NTF | 24/24 | — |
| DMS | 26/26 | pratonton Office (perlu LibreOffice) |
| WFE | 21/24 | 10, 11, 24 (aliran melalui AI → G5) |
| Ref (data) | DAT-01/05/08 | kod/sempadan rasmi JUPEM/PTG |

Isu terbuka: sempadan mukim anggaran; 5 kod mukim diteka; had muat naik PHP 50 MB (fail spatial 250 MB); MFA step-up WFE belum diuji dengan Keycloak sebenar; tiada hierarki penyelia sebenar (guna peranan lalai).

### Status G1 — siap (2026-10-25)

| Modul | Kod SRS ada ujian | Catatan |
| --- | --- | --- |
| PRO | 30/30 (+ DAT-01..04, 06..09) | Profil 360° (6 tab, garis masa, peta MapLibre), import dengan pratonton, duplikasi, stewardship, kamus data + ERD dijana (`docs/data/`), API OpenAPI + kunci API |
| HKM | 22/22 | Peti besi, sejarah tak boleh ubah (trigger), perubahan 2 peringkat MFA melalui WFE, legenda warna `frontend/lib/legend.ts` |
| PER | 29/30 diuji (30 dilaksana) | 11 saluran, Notis 5A, kelulusan berlapis melalui WFE, mock Portal Wakaf + e-Faraid |

DB dev diisi: 500 lot sintetik + geometri, 488 hak milik, 60 kes pemerolehan; aliran WFE `per.kelulusan`, `pro.perubahan_kritikal`, `pro.arkib_rekod`, `hkm.perubahan_hak_milik` aktif (`db:seed --class=G1AliranSeeder`, `HkmAliranSeeder`).

Terbuka: jadual satelit (penilaian, pajakan, cukai, aduan, kes) hanya rangka sehingga G3/G4; gabung duplikasi belum memindah rekod modul lain; `csv`/`zip` tiada dalam senarai putih DMS (fail import asal tidak disimpan).

## 16. Langkah seterusnya (bila diluluskan)

1. F0 langkah 1–3 (PostGIS, rangka modul, `/api/v1`) — boleh mula serta-merta.
2. RBC-a/b + AUD-a — supaya semua kod selepas ini sudah dikawal & diaudit.
3. Skrip `srs:rtm` awal dengan 572 kod diimport dari SRS sebagai checklist.
