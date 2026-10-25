# MOD-LAP — platform BI & row-level security (keputusan reka bentuk)

SRS-LAP-01 (platform BI sumber terbuka), SRS-LAP-08 (RLS pada peringkat pertanyaan, replika baca), SRS-LAP-09, SRS-RBC-08 (BI tidak boleh memintas kebenaran). PLAN §2/§10: Metabase pada replika baca + PostgreSQL RLS.

## Keputusan

1. **Semua pelaporan membaca skema `laporan`, bukan jadual mentah.**
   - `laporan.v_*` — paparan baris (lot, hak milik, kontrak, caj sewa, cukai, aduan, isu pemantauan, tugasan WFE, kes, aset, penyelenggaraan, pemerolehan, risk rating, hasil analitik, log penjanaan, aktiviti audit). Setiap paparan dicipta `WITH (security_barrier)` dan menapis baris dengan `laporan.dalam_skop(nilai, tetapan)` terhadap tetapan sesi:
     `sisdata.skop_daerah`, `sisdata.skop_mukim`, `sisdata.skop_bahagian`, `sisdata.tahap_klasifikasi`.
     Tetapan kosong = **tiada baris** (tolak secara lalai); `*` = tanpa sekatan.
   - Enjin laporan SISDATA (`App\Modules\Lap\Services\Enjin`) membuka transaksi `READ ONLY`, menetapkan nilai-nilai itu dengan `set_config(..., true)` (SET LOCAL) daripada `AccessControl::scope()` + kebenaran Sulit/Rahsia set data, dan `statement_timeout` (parameter `lap.had_masa_saat`), kemudian menjalankan pertanyaan. Penguatkuasaan berlaku dalam pangkalan data — laporan standard, ad hoc, AI prompt, berjadual, eksport dan latar belakang semuanya melalui laluan yang sama. Ujian `KeselamatanBarisTest` menyoal paparan secara terus untuk membuktikannya (kriteria penerimaan 4).
   - PostgreSQL RLS sebenar (`ALTER TABLE … ENABLE ROW LEVEL SECURITY`) tidak dipasang pada jadual modul lain kerana ia akan mengubah tingkah laku setiap modul transaksi; paparan security-barrier yang dikunci pada tetapan sesi memberi kesan yang sama untuk peranan pelaporan, dan peranan pelaporan tidak diberi capaian kepada jadual mentah.
2. **Metabase OSS tidak boleh menghantar konteks pengguna kepada Postgres** (tiada SSO/JWT, tiada *data sandboxing* — ciri berbayar Pro/Enterprise). Oleh itu:
   - Metabase menyambung sebagai peranan baca-sahaja **`sisdata_bi`** yang hanya boleh `SELECT` pada paparan **agregat** `laporan.bi_*` (tiada ID rekod, tiada data peribadi): `bi_portfolio`, `bi_hasil_sewaan_bulanan`, `bi_tunggakan`, `bi_aduan_bulanan`, `bi_sla_tugasan`, `bi_aset_kondisi`. Tiada capaian kepada `public.*` atau `laporan.v_*`; `default_transaction_read_only = on`.
   - Pengguna SISDATA melihat papan pemuka Metabase yang diluluskan melalui **static (signed) embedding**: SISDATA menandatangani JWT HS256 (`METABASE_EMBED_SECRET`) dengan parameter **dikunci** (cth. `kod_daerah` = skop daerah pengguna). Pelayar tidak boleh mengubah parameter itu; token tamat dalam `METABASE_EMBED_MINUTES` minit; setiap pembukaan diaudit. Skop kosong ditolak (dalam Metabase, nilai terkunci kosong = semua).
   - Laporan **berperingkat baris** (senarai rekod, drill-down ke rekod asal, eksport) kekal dalam SISDATA (`/admin/lap`), bukan Metabase.
   - Penganalisis yang perlu membina soalan baharu dalam Metabase menggunakan log masuk Metabase sendiri — dan tetap hanya melihat paparan agregat.

## Komponen tempatan

| Item | Lokasi |
| --- | --- |
| Peranan `sisdata_bi`, `sisdata_lap`, `metabase` + DB `metabase_app` | `infra/bin/sisdata-bi-setup.sh [db…]` (superuser) |
| Metabase OSS v0.64.1 (jar) di :3030 | `infra/bin/metabase-dev.sh install|start|stop|status|setup`; dipanggil oleh `infra/bin/sisdata-services.sh` |
| Bootstrap API (admin, pembenaman, sambungan `sisdata_bi` skema `laporan`, soalan contoh dengan `kod_daerah` dikunci) | `infra/bin/metabase-dev.sh setup` (`MB_SOURCE_DB`, `METABASE_EMBED_SECRET`) |
| Pendaftaran papan pemuka yang diluluskan | `/admin/lap/bi` (`lap.bi.kemas_kini`), jadual `lap_papan_bi` |
| `.env` SISDATA | `METABASE_SITE_URL`, `METABASE_EMBED_SECRET` (sama seperti Metabase), opsyenal `LAP_DB_READ_HOST/PORT/DATABASE/USERNAME/PASSWORD` |

## Keperluan produksi

- **Replika baca**: tetapkan `LAP_DB_READ_HOST…` (sambungan `lap_baca`) dengan peranan `sisdata_lap` (baca `laporan.v_*` sahaja) dan arahkan Metabase (`MB_SOURCE_HOST`) ke replika yang sama. Paparan & fungsi `laporan` direplikasi bersama skema; `set_config` berfungsi pada hot standby. `data_setakat` dalam setiap laporan menggunakan `pg_last_xact_replay_timestamp()` pada replika.
- **SSO untuk Metabase** (SAML/JWT/LDAP-group sync) dan **data sandboxing** adalah ciri berbayar Metabase Pro/Enterprise. Dengan OSS: pengguna biasa hanya melalui pembenaman SISDATA (SSO MAIS SISDATA); akaun penganalisis Metabase diurus manual. Jika MAIS memilih Apache Superset (alternatif SRS-LAP-01), Superset menyokong OAuth/OIDC (Keycloak) dan RLS terbina — keputusan CRP-2.
- Rahsia pembenaman 64 heks dalam pengurus rahsia (bukan fail); putar bersama kedua-dua pihak.
- Metabase di belakang proksi TLS yang sama domain dengan SISDATA (`frame-ancestors`/CSP untuk iframe).
- Pangkalan `metabase_app` disandarkan seperti DB aplikasi lain.
- Paparan agregat `bi_*` patut disemak MAIS semasa CRP-2 (ambang saiz kumpulan minimum jika perlu bagi data kewangan peringkat daerah).
