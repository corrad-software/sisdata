# Justifikasi Pangkalan Data — SISDATA (SRS-PRO-02)

Dokumen ini menyatakan pilihan pangkalan data SISDATA, sebab pemilihannya dan kerangka saiz pelayan. Ia ditulis berdasarkan timbunan teknologi yang sebenarnya dibina dan diuji dalam repositori ini. Angka perkakasan, lesen pengeluaran dan reka bentuk HA/DR muktamad bergantung pada infrastruktur MAIS dan ditanda **TBD**.

## 1. Produk dan versi

| Komponen | Produk | Versi diuji | Lesen |
| --- | --- | --- | --- |
| Pangkalan data hubungan | PostgreSQL | 17.10 | PostgreSQL License (sumber terbuka, tiada yuran lesen, tiada had teras/pengguna) |
| Sambungan spatial | PostGIS | 3.6.4 (GEOS 3.15, PROJ 9.9) | GPL v2 (sambungan; tidak menjangkiti kod aplikasi) |
| Sambungan vektor (RAG / carian semantik) | pgvector | 0.8.3 | PostgreSQL License |
| Padanan teks kabur | pg_trgm | 1.6 (contrib) | PostgreSQL License |
| Pemacu aplikasi | Laravel 13 (PDO pgsql) | — | MIT |

Bilangan lesen: **tiada lesen per teras atau per pengguna diperlukan.** Jika MAIS memilih langganan sokongan komersial (contohnya EDB, Crunchy Data, atau perkhidmatan terurus awan kerajaan), kos itu ialah sokongan, bukan lesen — **TBD** mengikut perolehan.

## 2. Keperluan yang dipenuhi

| Keperluan | Bagaimana dipenuhi |
| --- | --- |
| SRS-PRO-01 ACID, transaksi bersarang, concurrency, rollback | PostgreSQL MVCC; transaksi bersarang melalui SAVEPOINT (Laravel `DB::transaction` bersarang); rollback automatik apabila pengecualian (diuji: `test_SRS_PRO_01_failed_transaction_rolls_back_completely`); kawalan concurrency optimistik pada rekod induk (`versi_rekod`, HTTP 409) |
| SRS-PRO-08 / ADR-04 data spatial dalam pangkalan data yang sama | PostGIS: jenis `geometry(MultiPolygon, 4326)`, indeks GiST, `ST_Transform` dari GDM2000 / Selangor Grid (EPSG:3380) / Peninsula RSO (EPSG:3375) / Kertau, `ST_Area(geography)`, `ST_Intersects` untuk pengesanan pertindihan |
| SRS-KBA-06 carian semantik tanpa AI awam | pgvector dalam pangkalan data yang sama — tiada pangkalan vektor berasingan |
| SRS-PRO-20 / DAT-02 padanan duplikasi | pg_trgm `similarity()` + indeks GIN trigram atas `no_lot_normal`; prosedur `sp_kesan_duplikasi_lot` |
| SRS-PRO-04 / DAT-07 view & stored procedure | View `vw_profil_hartanah_360`, `vw_kualiti_data_rekod_tidak_lengkap`, `vw_lot_spatial_belum_disahkan`; fungsi `sp_kesan_duplikasi_lot` — semuanya dalam DDL (`docs/data/ddl.sql`) |
| SRS-RBC-08 / ADR-06 skop rekod & RLS untuk BI | Skop daerah/mukim/klasifikasi dikuatkuasakan dalam aplikasi; PostgreSQL Row-Level Security tersedia untuk replika baca BI (SRS-LAP-08) |
| SRS-AUD-06/08 audit tidak boleh diubah | Jadual append-only `audit_rekod` dengan rantaian SHA-256; akaun aplikasi boleh dihadkan tanpa `UPDATE/DELETE` pada jadual audit |
| SRS-PRO-14 penyulitan | TLS 1.3 untuk sambungan (`sslmode=verify-full`); penyulitan storan pada peringkat cakera/volum (AES-256, LUKS / storan awan tersulit) — **TBD** konfigurasi pengeluaran; fail dokumen disulitkan sampul oleh MOD-DMS |

## 3. Justifikasi berbanding alternatif

| Kriteria | PostgreSQL + PostGIS | Oracle Database + Spatial | Microsoft SQL Server | MySQL / MariaDB |
| --- | --- | --- | --- | --- |
| Kos lesen | Tiada | Tinggi (per teras + pilihan Spatial/Partitioning) | Sederhana–tinggi (per teras) | Tiada / dwi-lesen |
| Keupayaan spatial (unjuran GDM2000, Cassini, geodesi) | Matang (PostGIS + PROJ), standard OGC | Matang | Asas (tiada reprojeksi terbina) | Terhad |
| Vektor (RAG) dalam DB sama | pgvector | Oracle 23ai sahaja | Pratonton | Tiada |
| Kebolehpindahan / tiada kunci vendor (SRS 12.4) | Tinggi — boleh di premis, awan kerajaan atau terurus | Rendah | Sederhana | Tinggi |
| Kemahiran tempatan & ekosistem (GeoServer, QGIS, Metabase) | Sangat baik | Baik | Baik | Sederhana |

Kesimpulan: PostgreSQL 17 + PostGIS memenuhi semua keperluan Bab 4/7 dalam satu enjin (relasi, spatial, vektor, carian teks), tanpa yuran lesen, dan disokong terus oleh GeoServer (pengganti SuperMap semasa pembangunan) serta alat BI.

## 4. Keperluan pelayan (kerangka saiz)

Anggaran awal berdasarkan isi padu SRS (≈ puluhan ribu lot, ratusan ribu rekod satelit, audit setiap transaksi). **Semua angka TBD** sehingga data sebenar MAIS dan sasaran prestasi Bab 12 disahkan dengan ujian beban.

| Peranan | vCPU | Memori | Storan | IOPS | Catatan |
| --- | --- | --- | --- | --- | --- |
| Primer (pengeluaran) | TBD (cadangan permulaan 8) | TBD (cadangan 32 GB) | TBD (cadangan 500 GB SSD) | TBD (≥ 3,000) | `shared_buffers` ≈ 25% RAM |
| Replika siap sedia (HA) | sama seperti primer | sama | sama | sama | replikasi penstriman segerak |
| Replika baca (BI / laporan) | TBD | TBD | sama | TBD | RLS untuk BI |
| Pelayan DR (tapak kedua) | TBD | TBD | TBD | TBD | replikasi tak segerak |
| Storan sandaran | — | — | TBD (≥ 3× saiz DB) | — | objek / MinIO / S3 |

## 5. Kaedah sandaran

- Sandaran fizikal penuh harian (`pgBackRest` atau `pg_basebackup`) + arkib WAL berterusan → Point-in-Time Recovery. RPO sasaran dan tempoh simpanan: **TBD** (Bab 14.3).
- Sandaran logik mingguan (`pg_dump`) untuk pemulihan jadual tunggal dan pengesahan skema.
- Ujian pemulihan berkala didokumenkan.

## 6. Reka bentuk HA dan DR

- **HA:** primer + siap sedia segerak dalam zon berbeza, failover automatik (Patroni / perkhidmatan terurus) — **TBD** mengikut platform MAIS.
- **DR:** replika tak segerak di tapak kedua; RTO/RPO mengikut Bab 14.4 — **TBD**.
- Sambungan aplikasi melalui pengumpul sambungan (PgBouncer) supaya failover telus kepada aplikasi.

## 7. Persekitaran pembangunan (sedia ada)

PostgreSQL 17 asli (Homebrew) pada port 5432, pangkalan data `myds_laravel` (dev) dan `myds_laravel_test_*` (ujian). Sambungan dicipta oleh `infra/bin/sisdata-db-setup.sh` kerana PostGIS bukan sambungan dipercayai. DDL semasa dan model fizikal dijana daripada skema langsung: `php artisan pro:jana-dokumen-data` → `docs/data/`.
