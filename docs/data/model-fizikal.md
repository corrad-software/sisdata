# Model Data Fizikal — SISDATA

> Dijana secara automatik oleh `php artisan pro:jana-dokumen-data` daripada skema pangkalan data `myds_laravel_test_pro` pada 24/10/2026 15:16. Jangan sunting dengan tangan — jana semula.

**Ringkasan:** 124 jadual/view, 344 kekangan, 325 indeks (5 spatial GiST), 3 view, 3 fungsi/prosedur.

Jenis kekangan: p = primary key, f = foreign key, u = unique, c = check.

## Jadual

### `adm_indeks_carian`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('adm_indeks_carian_id_seq'::regclass) | TIDAK | PK |
| `entiti` | character varying | 60 |  | TIDAK | UQ |
| `label` | character varying | 120 |  | YA |  |
| `medan` | json |  |  | YA |  |
| `pemberat` | numeric | 5,2 | '1'::numeric | TIDAK |  |
| `aktif` | boolean |  | true | TIDAK |  |
| `status_indeks` | character varying | 20 |  | YA |  |
| `diindeks_semula_pada` | timestamp without time zone |  |  | YA |  |
| `justifikasi` | text |  |  | YA |  |
| `dikemas_kini_oleh` | bigint | 64 |  | YA | FK |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `adm_indeks_carian_dikemas_kini_oleh_foreign` (f): `FOREIGN KEY (dikemas_kini_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `adm_indeks_carian_pkey` (p): `PRIMARY KEY (id)`
- `adm_indeks_carian_entiti_unique` (u): `UNIQUE (entiti)`

**Indeks**

- `adm_indeks_carian_entiti_unique`: `CREATE UNIQUE INDEX adm_indeks_carian_entiti_unique ON public.adm_indeks_carian USING btree (entiti)`
- `adm_indeks_carian_pkey`: `CREATE UNIQUE INDEX adm_indeks_carian_pkey ON public.adm_indeks_carian USING btree (id)`

### `adm_integrasi`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('adm_integrasi_id_seq'::regclass) | TIDAK | PK |
| `kod` | character varying | 60 |  | TIDAK | UQ |
| `nama` | character varying | 200 |  | TIDAK |  |
| `jenis` | character varying | 30 | 'rest'::character varying | TIDAK |  |
| `endpoint` | character varying | 500 |  | TIDAK |  |
| `id_rahsia` | character varying | 120 |  | YA |  |
| `tempoh_tamat_masa` | integer | 32 | 30 | TIDAK |  |
| `kadar_capaian` | integer | 32 |  | YA |  |
| `jadual` | character varying | 100 |  | YA |  |
| `status` | character varying | 20 | 'aktif'::character varying | TIDAK |  |
| `kegagalan_berturut` | integer | 32 | 0 | TIDAK |  |
| `terakhir_berjaya_pada` | timestamp without time zone |  |  | YA |  |
| `terakhir_gagal_pada` | timestamp without time zone |  |  | YA |  |
| `justifikasi` | text |  |  | YA |  |
| `dikemas_kini_oleh` | bigint | 64 |  | YA | FK |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `adm_integrasi_dikemas_kini_oleh_foreign` (f): `FOREIGN KEY (dikemas_kini_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `adm_integrasi_pkey` (p): `PRIMARY KEY (id)`
- `adm_integrasi_kod_unique` (u): `UNIQUE (kod)`

**Indeks**

- `adm_integrasi_kod_unique`: `CREATE UNIQUE INDEX adm_integrasi_kod_unique ON public.adm_integrasi USING btree (kod)`
- `adm_integrasi_pkey`: `CREATE UNIQUE INDEX adm_integrasi_pkey ON public.adm_integrasi USING btree (id)`

### `adm_kesihatan`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('adm_kesihatan_id_seq'::regclass) | TIDAK | PK |
| `komponen` | character varying | 40 |  | TIDAK |  |
| `status` | character varying | 10 |  | TIDAK |  |
| `masa_respons_ms` | integer | 32 |  | YA |  |
| `mesej` | character varying | 500 |  | YA |  |
| `butiran` | json |  |  | YA |  |
| `dicatat_pada` | timestamp without time zone |  | CURRENT_TIMESTAMP | TIDAK |  |

**Kekangan**

- `adm_kesihatan_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `adm_kesihatan_komponen_dicatat_pada_index`: `CREATE INDEX adm_kesihatan_komponen_dicatat_pada_index ON public.adm_kesihatan USING btree (komponen, dicatat_pada)`
- `adm_kesihatan_pkey`: `CREATE UNIQUE INDEX adm_kesihatan_pkey ON public.adm_kesihatan USING btree (id)`

### `adm_kod_rujukan`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('adm_kod_rujukan_id_seq'::regclass) | TIDAK | PK |
| `jenis_kod` | character varying | 60 |  | TIDAK | UQ |
| `kod` | character varying | 60 |  | TIDAK | UQ |
| `keterangan` | character varying | 255 |  | TIDAK |  |
| `kod_induk` | character varying | 60 |  | YA |  |
| `susunan` | integer | 32 | 0 | TIDAK |  |
| `status` | character varying | 20 | 'aktif'::character varying | TIDAK |  |
| `tarikh_mula` | date |  |  | TIDAK |  |
| `tarikh_tamat` | date |  |  | YA |  |
| `versi` | integer | 32 | 1 | TIDAK |  |
| `justifikasi` | text |  |  | YA |  |
| `dikemas_kini_oleh` | bigint | 64 |  | YA | FK |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `adm_kod_rujukan_dikemas_kini_oleh_foreign` (f): `FOREIGN KEY (dikemas_kini_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `adm_kod_rujukan_pkey` (p): `PRIMARY KEY (id)`
- `adm_kod_rujukan_jenis_kod_kod_unique` (u): `UNIQUE (jenis_kod, kod)`

**Indeks**

- `adm_kod_rujukan_jenis_kod_kod_unique`: `CREATE UNIQUE INDEX adm_kod_rujukan_jenis_kod_kod_unique ON public.adm_kod_rujukan USING btree (jenis_kod, kod)`
- `adm_kod_rujukan_jenis_kod_status_susunan_index`: `CREATE INDEX adm_kod_rujukan_jenis_kod_status_susunan_index ON public.adm_kod_rujukan USING btree (jenis_kod, status, susunan)`
- `adm_kod_rujukan_pkey`: `CREATE UNIQUE INDEX adm_kod_rujukan_pkey ON public.adm_kod_rujukan USING btree (id)`

### `adm_kod_rujukan_sejarah`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('adm_kod_rujukan_sejarah_id_seq'::regclass) | TIDAK | PK |
| `id_kod_rujukan` | bigint | 64 |  | TIDAK | FK, UQ |
| `versi` | integer | 32 |  | TIDAK | UQ |
| `data` | json |  |  | TIDAK |  |
| `diubah_oleh` | bigint | 64 |  | YA | FK |
| `created_at` | timestamp without time zone |  | CURRENT_TIMESTAMP | TIDAK |  |

**Kekangan**

- `adm_kod_rujukan_sejarah_diubah_oleh_foreign` (f): `FOREIGN KEY (diubah_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `adm_kod_rujukan_sejarah_id_kod_rujukan_foreign` (f): `FOREIGN KEY (id_kod_rujukan) REFERENCES adm_kod_rujukan(id) ON DELETE CASCADE`
- `adm_kod_rujukan_sejarah_pkey` (p): `PRIMARY KEY (id)`
- `adm_kod_rujukan_sejarah_id_kod_rujukan_versi_unique` (u): `UNIQUE (id_kod_rujukan, versi)`

**Indeks**

- `adm_kod_rujukan_sejarah_id_kod_rujukan_versi_unique`: `CREATE UNIQUE INDEX adm_kod_rujukan_sejarah_id_kod_rujukan_versi_unique ON public.adm_kod_rujukan_sejarah USING btree (id_kod_rujukan, versi)`
- `adm_kod_rujukan_sejarah_pkey`: `CREATE UNIQUE INDEX adm_kod_rujukan_sejarah_pkey ON public.adm_kod_rujukan_sejarah USING btree (id)`

### `adm_konfig_kelulusan`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('adm_konfig_kelulusan_id_seq'::regclass) | TIDAK | PK |
| `jenis_konfig` | character varying | 30 |  | TIDAK |  |
| `kunci` | character varying | 120 |  | TIDAK |  |
| `nilai_sebelum` | json |  |  | YA |  |
| `nilai_selepas` | json |  |  | YA |  |
| `nilai_rahsia_tersulit` | text |  |  | YA |  |
| `justifikasi` | text |  |  | TIDAK |  |
| `id_pemohon` | bigint | 64 |  | TIDAK | FK |
| `alamat_ip_pemohon` | character varying | 45 |  | YA |  |
| `id_pelulus` | bigint | 64 |  | YA | FK |
| `catatan_pelulus` | text |  |  | YA |  |
| `status` | character varying | 20 | 'menunggu'::character varying | TIDAK |  |
| `id_instans_aliran` | character varying | 64 |  | YA |  |
| `diputuskan_pada` | timestamp without time zone |  |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `adm_konfig_kelulusan_id_pelulus_foreign` (f): `FOREIGN KEY (id_pelulus) REFERENCES users(id)`
- `adm_konfig_kelulusan_id_pemohon_foreign` (f): `FOREIGN KEY (id_pemohon) REFERENCES users(id)`
- `adm_konfig_kelulusan_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `adm_konfig_kelulusan_jenis_konfig_kunci_status_index`: `CREATE INDEX adm_konfig_kelulusan_jenis_konfig_kunci_status_index ON public.adm_konfig_kelulusan USING btree (jenis_konfig, kunci, status)`
- `adm_konfig_kelulusan_pkey`: `CREATE UNIQUE INDEX adm_konfig_kelulusan_pkey ON public.adm_konfig_kelulusan USING btree (id)`
- `adm_konfig_kelulusan_status_created_at_index`: `CREATE INDEX adm_konfig_kelulusan_status_created_at_index ON public.adm_konfig_kelulusan USING btree (status, created_at)`

### `adm_log_integrasi`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('adm_log_integrasi_id_seq'::regclass) | TIDAK | PK |
| `kod_integrasi` | character varying | 60 |  | TIDAK |  |
| `id_korelasi` | character varying | 64 |  | YA |  |
| `tarikh_masa` | timestamp without time zone |  | CURRENT_TIMESTAMP | TIDAK |  |
| `arah` | character varying | 10 | 'keluar'::character varying | TIDAK |  |
| `operasi` | character varying | 120 |  | YA |  |
| `berjaya` | boolean |  |  | TIDAK |  |
| `status_http` | smallint | 16 |  | YA |  |
| `tempoh_ms` | integer | 32 |  | YA |  |
| `bilangan_rekod` | integer | 32 |  | YA |  |
| `mesej` | text |  |  | YA |  |
| `konteks` | json |  |  | YA |  |

**Kekangan**

- `adm_log_integrasi_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `adm_log_integrasi_id_korelasi_index`: `CREATE INDEX adm_log_integrasi_id_korelasi_index ON public.adm_log_integrasi USING btree (id_korelasi)`
- `adm_log_integrasi_kod_integrasi_index`: `CREATE INDEX adm_log_integrasi_kod_integrasi_index ON public.adm_log_integrasi USING btree (kod_integrasi)`
- `adm_log_integrasi_pkey`: `CREATE UNIQUE INDEX adm_log_integrasi_pkey ON public.adm_log_integrasi USING btree (id)`
- `adm_log_integrasi_tarikh_masa_index`: `CREATE INDEX adm_log_integrasi_tarikh_masa_index ON public.adm_log_integrasi USING btree (tarikh_masa)`

### `adm_log_ralat`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('adm_log_ralat_id_seq'::regclass) | TIDAK | PK |
| `id_korelasi` | character varying | 64 |  | YA |  |
| `tarikh_masa` | timestamp without time zone |  | CURRENT_TIMESTAMP | TIDAK |  |
| `aras_keterukan` | character varying | 12 |  | TIDAK |  |
| `saluran` | character varying | 30 | 'aplikasi'::character varying | TIDAK |  |
| `modul` | character varying | 20 |  | YA |  |
| `kod_ralat` | character varying | 120 |  | YA |  |
| `mesej` | text |  |  | TIDAK |  |
| `jejak_tindanan` | text |  |  | YA |  |
| `id_pengguna` | bigint | 64 |  | YA |  |
| `alamat_ip` | character varying | 45 |  | YA |  |
| `konteks` | json |  |  | YA |  |

**Kekangan**

- `adm_log_ralat_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `adm_log_ralat_aras_keterukan_index`: `CREATE INDEX adm_log_ralat_aras_keterukan_index ON public.adm_log_ralat USING btree (aras_keterukan)`
- `adm_log_ralat_id_korelasi_index`: `CREATE INDEX adm_log_ralat_id_korelasi_index ON public.adm_log_ralat USING btree (id_korelasi)`
- `adm_log_ralat_id_pengguna_index`: `CREATE INDEX adm_log_ralat_id_pengguna_index ON public.adm_log_ralat USING btree (id_pengguna)`
- `adm_log_ralat_kod_ralat_index`: `CREATE INDEX adm_log_ralat_kod_ralat_index ON public.adm_log_ralat USING btree (kod_ralat)`
- `adm_log_ralat_modul_index`: `CREATE INDEX adm_log_ralat_modul_index ON public.adm_log_ralat USING btree (modul)`
- `adm_log_ralat_pkey`: `CREATE UNIQUE INDEX adm_log_ralat_pkey ON public.adm_log_ralat USING btree (id)`
- `adm_log_ralat_tarikh_masa_index`: `CREATE INDEX adm_log_ralat_tarikh_masa_index ON public.adm_log_ralat USING btree (tarikh_masa)`

### `adm_parameter`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('adm_parameter_id_seq'::regclass) | TIDAK | PK |
| `kunci` | character varying | 120 |  | TIDAK | UQ |
| `modul` | character varying | 20 |  | TIDAK |  |
| `nilai` | text |  |  | YA |  |
| `justifikasi` | text |  |  | YA |  |
| `dikemas_kini_oleh` | bigint | 64 |  | YA | FK |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `adm_parameter_dikemas_kini_oleh_foreign` (f): `FOREIGN KEY (dikemas_kini_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `adm_parameter_pkey` (p): `PRIMARY KEY (id)`
- `adm_parameter_kunci_unique` (u): `UNIQUE (kunci)`

**Indeks**

- `adm_parameter_kunci_unique`: `CREATE UNIQUE INDEX adm_parameter_kunci_unique ON public.adm_parameter USING btree (kunci)`
- `adm_parameter_modul_index`: `CREATE INDEX adm_parameter_modul_index ON public.adm_parameter USING btree (modul)`
- `adm_parameter_pkey`: `CREATE UNIQUE INDEX adm_parameter_pkey ON public.adm_parameter USING btree (id)`

### `adm_rahsia`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('adm_rahsia_id_seq'::regclass) | TIDAK | PK |
| `kunci` | character varying | 120 |  | TIDAK | UQ |
| `nilai_tersulit` | text |  |  | TIDAK |  |
| `keterangan` | character varying | 255 |  | YA |  |
| `dikemas_kini_oleh` | bigint | 64 |  | YA | FK |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `adm_rahsia_dikemas_kini_oleh_foreign` (f): `FOREIGN KEY (dikemas_kini_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `adm_rahsia_pkey` (p): `PRIMARY KEY (id)`
- `adm_rahsia_kunci_unique` (u): `UNIQUE (kunci)`

**Indeks**

- `adm_rahsia_kunci_unique`: `CREATE UNIQUE INDEX adm_rahsia_kunci_unique ON public.adm_rahsia USING btree (kunci)`
- `adm_rahsia_pkey`: `CREATE UNIQUE INDEX adm_rahsia_pkey ON public.adm_rahsia USING btree (id)`

### `adm_sinonim_carian`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('adm_sinonim_carian_id_seq'::regclass) | TIDAK | PK |
| `istilah` | character varying | 120 |  | TIDAK | UQ |
| `sinonim` | json |  |  | TIDAK |  |
| `dikemas_kini_oleh` | bigint | 64 |  | YA | FK |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `adm_sinonim_carian_dikemas_kini_oleh_foreign` (f): `FOREIGN KEY (dikemas_kini_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `adm_sinonim_carian_pkey` (p): `PRIMARY KEY (id)`
- `adm_sinonim_carian_istilah_unique` (u): `UNIQUE (istilah)`

**Indeks**

- `adm_sinonim_carian_istilah_unique`: `CREATE UNIQUE INDEX adm_sinonim_carian_istilah_unique ON public.adm_sinonim_carian USING btree (istilah)`
- `adm_sinonim_carian_pkey`: `CREATE UNIQUE INDEX adm_sinonim_carian_pkey ON public.adm_sinonim_carian USING btree (id)`

### `adm_templat`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('adm_templat_id_seq'::regclass) | TIDAK | PK |
| `kod` | character varying | 80 |  | TIDAK | UQ |
| `nama` | character varying | 200 |  | TIDAK |  |
| `jenis` | character varying | 30 | 'laporan'::character varying | TIDAK |  |
| `format_output` | character varying | 10 | 'pdf'::character varying | TIDAK |  |
| `kandungan` | text |  |  | TIDAK |  |
| `medan` | json |  |  | YA |  |
| `versi` | integer | 32 | 1 | TIDAK |  |
| `status` | character varying | 20 | 'aktif'::character varying | TIDAK |  |
| `justifikasi` | text |  |  | YA |  |
| `dikemas_kini_oleh` | bigint | 64 |  | YA | FK |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `adm_templat_dikemas_kini_oleh_foreign` (f): `FOREIGN KEY (dikemas_kini_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `adm_templat_pkey` (p): `PRIMARY KEY (id)`
- `adm_templat_kod_unique` (u): `UNIQUE (kod)`

**Indeks**

- `adm_templat_kod_unique`: `CREATE UNIQUE INDEX adm_templat_kod_unique ON public.adm_templat USING btree (kod)`
- `adm_templat_pkey`: `CREATE UNIQUE INDEX adm_templat_pkey ON public.adm_templat USING btree (id)`

### `adm_templat_versi`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('adm_templat_versi_id_seq'::regclass) | TIDAK | PK |
| `id_templat` | bigint | 64 |  | TIDAK | FK, UQ |
| `versi` | integer | 32 |  | TIDAK | UQ |
| `format_output` | character varying | 10 |  | TIDAK |  |
| `kandungan` | text |  |  | TIDAK |  |
| `medan` | json |  |  | YA |  |
| `dicipta_oleh` | bigint | 64 |  | YA | FK |
| `created_at` | timestamp without time zone |  | CURRENT_TIMESTAMP | TIDAK |  |

**Kekangan**

- `adm_templat_versi_dicipta_oleh_foreign` (f): `FOREIGN KEY (dicipta_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `adm_templat_versi_id_templat_foreign` (f): `FOREIGN KEY (id_templat) REFERENCES adm_templat(id) ON DELETE CASCADE`
- `adm_templat_versi_pkey` (p): `PRIMARY KEY (id)`
- `adm_templat_versi_id_templat_versi_unique` (u): `UNIQUE (id_templat, versi)`

**Indeks**

- `adm_templat_versi_id_templat_versi_unique`: `CREATE UNIQUE INDEX adm_templat_versi_id_templat_versi_unique ON public.adm_templat_versi USING btree (id_templat, versi)`
- `adm_templat_versi_pkey`: `CREATE UNIQUE INDEX adm_templat_versi_pkey ON public.adm_templat_versi USING btree (id)`

### `adm_tugasan_berjadual`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('adm_tugasan_berjadual_id_seq'::regclass) | TIDAK | PK |
| `kod` | character varying | 80 |  | TIDAK | UQ |
| `nama` | character varying | 200 |  | TIDAK |  |
| `keterangan` | text |  |  | YA |  |
| `kategori` | character varying | 30 |  | TIDAK |  |
| `arahan` | character varying | 200 |  | TIDAK |  |
| `argumen` | json |  |  | YA |  |
| `jenis_jadual` | character varying | 10 | 'cron'::character varying | TIDAK |  |
| `ungkapan_cron` | character varying | 100 |  | YA |  |
| `selang_minit` | integer | 32 |  | YA |  |
| `aktif` | boolean |  | true | TIDAK |  |
| `cubaan_semula` | smallint | 16 | '0'::smallint | TIDAK |  |
| `tempoh_tamat_masa_saat` | integer | 32 | 3600 | TIDAK |  |
| `toleransi_lewat_minit` | integer | 32 |  | YA |  |
| `sumber` | character varying | 10 | 'adm'::character varying | TIDAK |  |
| `kunci_sistem` | character varying | 200 |  | YA | UQ |
| `terakhir_mula_pada` | timestamp without time zone |  |  | YA |  |
| `terakhir_status` | character varying | 20 |  | YA |  |
| `justifikasi` | text |  |  | YA |  |
| `dikemas_kini_oleh` | bigint | 64 |  | YA | FK |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `adm_tugasan_berjadual_dikemas_kini_oleh_foreign` (f): `FOREIGN KEY (dikemas_kini_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `adm_tugasan_berjadual_pkey` (p): `PRIMARY KEY (id)`
- `adm_tugasan_berjadual_kod_unique` (u): `UNIQUE (kod)`
- `adm_tugasan_berjadual_kunci_sistem_unique` (u): `UNIQUE (kunci_sistem)`

**Indeks**

- `adm_tugasan_berjadual_kod_unique`: `CREATE UNIQUE INDEX adm_tugasan_berjadual_kod_unique ON public.adm_tugasan_berjadual USING btree (kod)`
- `adm_tugasan_berjadual_kunci_sistem_unique`: `CREATE UNIQUE INDEX adm_tugasan_berjadual_kunci_sistem_unique ON public.adm_tugasan_berjadual USING btree (kunci_sistem)`
- `adm_tugasan_berjadual_pkey`: `CREATE UNIQUE INDEX adm_tugasan_berjadual_pkey ON public.adm_tugasan_berjadual USING btree (id)`

### `adm_tugasan_larian`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('adm_tugasan_larian_id_seq'::regclass) | TIDAK | PK |
| `id_tugasan` | bigint | 64 |  | TIDAK | FK |
| `pencetus` | character varying | 10 | 'jadual'::character varying | TIDAK |  |
| `id_pengguna` | bigint | 64 |  | YA | FK |
| `cubaan` | smallint | 16 | '1'::smallint | TIDAK |  |
| `tarikh_mula` | timestamp without time zone |  |  | YA |  |
| `tarikh_tamat` | timestamp without time zone |  |  | YA |  |
| `tempoh_saat` | numeric | 10,3 |  | YA |  |
| `status` | character varying | 15 |  | TIDAK |  |
| `bilangan_rekod` | bigint | 64 |  | YA |  |
| `mesej_ralat` | text |  |  | YA |  |
| `output` | text |  |  | YA |  |
| `id_korelasi` | character varying | 64 |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `adm_tugasan_larian_id_pengguna_foreign` (f): `FOREIGN KEY (id_pengguna) REFERENCES users(id) ON DELETE SET NULL`
- `adm_tugasan_larian_id_tugasan_foreign` (f): `FOREIGN KEY (id_tugasan) REFERENCES adm_tugasan_berjadual(id) ON DELETE CASCADE`
- `adm_tugasan_larian_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `adm_tugasan_larian_id_tugasan_created_at_index`: `CREATE INDEX adm_tugasan_larian_id_tugasan_created_at_index ON public.adm_tugasan_larian USING btree (id_tugasan, created_at)`
- `adm_tugasan_larian_pkey`: `CREATE UNIQUE INDEX adm_tugasan_larian_pkey ON public.adm_tugasan_larian USING btree (id)`
- `adm_tugasan_larian_status_tarikh_mula_index`: `CREATE INDEX adm_tugasan_larian_status_tarikh_mula_index ON public.adm_tugasan_larian USING btree (status, tarikh_mula)`

### `aduan`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | uuid |  |  | TIDAK | PK |
| `lot_id` | uuid |  |  | YA | FK |
| `kategori` | character varying | 30 |  | YA |  |
| `tarikh_aduan` | date |  |  | YA |  |
| `status` | character varying | 30 | 'baharu'::character varying | TIDAK |  |
| `id_pegawai` | bigint | 64 |  | YA | FK |
| `tindakan_susulan` | text |  |  | YA |  |
| `klasifikasi` | character varying | 10 | 'dalaman'::character varying | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |
| `deleted_at` | timestamp without time zone |  |  | YA |  |
| `koordinat` | geometry(Point,4326) |  |  | YA |  |

**Kekangan**

- `aduan_id_pegawai_foreign` (f): `FOREIGN KEY (id_pegawai) REFERENCES users(id) ON DELETE SET NULL`
- `aduan_lot_id_foreign` (f): `FOREIGN KEY (lot_id) REFERENCES lot_tanah(id)`
- `aduan_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `aduan_lot_id_index`: `CREATE INDEX aduan_lot_id_index ON public.aduan USING btree (lot_id)`
- `aduan_pkey`: `CREATE UNIQUE INDEX aduan_pkey ON public.aduan USING btree (id)`

### `amaran_keselamatan`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('amaran_keselamatan_id_seq'::regclass) | TIDAK | PK |
| `jenis` | character varying | 40 |  | TIDAK |  |
| `user_id` | bigint | 64 |  | YA | FK |
| `alamat_ip` | character varying | 45 |  | YA |  |
| `butiran` | json |  |  | YA |  |
| `disemak_pada` | timestamp without time zone |  |  | YA |  |
| `disemak_oleh` | bigint | 64 |  | YA | FK |
| `created_at` | timestamp without time zone |  | CURRENT_TIMESTAMP | TIDAK |  |

**Kekangan**

- `amaran_keselamatan_disemak_oleh_foreign` (f): `FOREIGN KEY (disemak_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `amaran_keselamatan_user_id_foreign` (f): `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL`
- `amaran_keselamatan_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `amaran_keselamatan_created_at_index`: `CREATE INDEX amaran_keselamatan_created_at_index ON public.amaran_keselamatan USING btree (created_at)`
- `amaran_keselamatan_jenis_index`: `CREATE INDEX amaran_keselamatan_jenis_index ON public.amaran_keselamatan USING btree (jenis)`
- `amaran_keselamatan_pkey`: `CREATE UNIQUE INDEX amaran_keselamatan_pkey ON public.amaran_keselamatan USING btree (id)`

### `audit_arkib`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 |  | TIDAK | PK |
| `no_urutan` | bigint | 64 |  | TIDAK | UQ |
| `cincangan_sebelum` | character | 64 |  | TIDAK |  |
| `cincangan_rekod` | character | 64 |  | TIDAK | UQ |
| `tarikh_masa_utc` | timestamp with time zone |  |  | TIDAK |  |
| `tarikh_masa_tempatan` | timestamp without time zone |  |  | TIDAK |  |
| `id_pengguna` | bigint | 64 |  | YA |  |
| `pengecam_pengguna` | character varying | 255 |  | YA |  |
| `peranan_semasa` | json |  |  | TIDAK |  |
| `id_sesi` | character varying | 80 |  | YA |  |
| `alamat_ip` | character varying | 45 |  | YA |  |
| `user_agent` | text |  |  | YA |  |
| `jenis_peranti` | character varying | 20 |  | TIDAK |  |
| `lokasi_anggaran` | character varying | 255 |  | YA |  |
| `saluran_capaian` | character varying | 20 |  | TIDAK |  |
| `modul` | character varying | 20 |  | TIDAK |  |
| `nama_entiti` | character varying | 100 |  | YA |  |
| `id_rekod` | character varying | 100 |  | YA |  |
| `jenis_tindakan` | character varying | 40 |  | TIDAK |  |
| `status_transaksi` | character varying | 20 |  | TIDAK |  |
| `sebab_perubahan` | text |  |  | YA |  |
| `id_korelasi` | character varying | 64 |  | YA |  |
| `klasifikasi` | character varying | 20 |  | YA |  |
| `meta` | json |  |  | YA |  |
| `perubahan` | json |  |  | YA |  |
| `diarkib_pada` | timestamp with time zone |  |  | TIDAK |  |
| `id_kelompok_arkib` | character varying | 64 |  | TIDAK |  |

**Kekangan**

- `audit_arkib_pkey` (p): `PRIMARY KEY (id)`
- `audit_arkib_cincangan_rekod_unique` (u): `UNIQUE (cincangan_rekod)`
- `audit_arkib_no_urutan_unique` (u): `UNIQUE (no_urutan)`

**Indeks**

- `audit_arkib_alamat_ip_index`: `CREATE INDEX audit_arkib_alamat_ip_index ON public.audit_arkib USING btree (alamat_ip)`
- `audit_arkib_cincangan_rekod_unique`: `CREATE UNIQUE INDEX audit_arkib_cincangan_rekod_unique ON public.audit_arkib USING btree (cincangan_rekod)`
- `audit_arkib_id_korelasi_index`: `CREATE INDEX audit_arkib_id_korelasi_index ON public.audit_arkib USING btree (id_korelasi)`
- `audit_arkib_id_pengguna_index`: `CREATE INDEX audit_arkib_id_pengguna_index ON public.audit_arkib USING btree (id_pengguna)`
- `audit_arkib_modul_jenis_tindakan_index`: `CREATE INDEX audit_arkib_modul_jenis_tindakan_index ON public.audit_arkib USING btree (modul, jenis_tindakan)`
- `audit_arkib_nama_entiti_id_rekod_index`: `CREATE INDEX audit_arkib_nama_entiti_id_rekod_index ON public.audit_arkib USING btree (nama_entiti, id_rekod)`
- `audit_arkib_no_urutan_unique`: `CREATE UNIQUE INDEX audit_arkib_no_urutan_unique ON public.audit_arkib USING btree (no_urutan)`
- `audit_arkib_pkey`: `CREATE UNIQUE INDEX audit_arkib_pkey ON public.audit_arkib USING btree (id)`
- `audit_arkib_status_transaksi_index`: `CREATE INDEX audit_arkib_status_transaksi_index ON public.audit_arkib USING btree (status_transaksi)`
- `audit_arkib_tarikh_masa_utc_index`: `CREATE INDEX audit_arkib_tarikh_masa_utc_index ON public.audit_arkib USING btree (tarikh_masa_utc)`

### `audit_eksport`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('audit_eksport_id_seq'::regclass) | TIDAK | PK |
| `id_pengguna` | bigint | 64 |  | YA |  |
| `pengecam_pengguna` | character varying | 255 |  | YA |  |
| `kriteria_carian` | json |  |  | TIDAK |  |
| `format` | character varying | 10 |  | TIDAK |  |
| `bilangan_rekod` | integer | 32 |  | TIDAK |  |
| `checksum` | character | 64 |  | TIDAK |  |
| `checksum_data` | character | 64 |  | TIDAK |  |
| `nama_fail` | character varying | 255 |  | TIDAK |  |
| `saiz_bait` | bigint | 64 |  | TIDAK |  |
| `tarikh_masa_utc` | timestamp with time zone |  |  | TIDAK |  |
| `tarikh_masa_tempatan` | timestamp without time zone |  |  | TIDAK |  |

**Kekangan**

- `audit_eksport_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `audit_eksport_id_pengguna_index`: `CREATE INDEX audit_eksport_id_pengguna_index ON public.audit_eksport USING btree (id_pengguna)`
- `audit_eksport_pkey`: `CREATE UNIQUE INDEX audit_eksport_pkey ON public.audit_eksport USING btree (id)`
- `audit_eksport_tarikh_masa_utc_index`: `CREATE INDEX audit_eksport_tarikh_masa_utc_index ON public.audit_eksport USING btree (tarikh_masa_utc)`

### `audit_integriti_semakan`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('audit_integriti_semakan_id_seq'::regclass) | TIDAK | PK |
| `dimulakan_pada` | timestamp with time zone |  |  | TIDAK |  |
| `selesai_pada` | timestamp with time zone |  |  | TIDAK |  |
| `status` | character varying | 10 |  | TIDAK |  |
| `bilangan_disemak` | bigint | 64 |  | TIDAK |  |
| `no_urutan_terakhir` | bigint | 64 |  | YA |  |
| `no_urutan_rosak` | bigint | 64 |  | YA |  |
| `butiran` | text |  |  | YA |  |
| `dicetuskan_oleh` | character varying | 20 |  | TIDAK |  |
| `id_pengguna` | bigint | 64 |  | YA |  |

**Kekangan**

- `audit_integriti_semakan_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `audit_integriti_semakan_pkey`: `CREATE UNIQUE INDEX audit_integriti_semakan_pkey ON public.audit_integriti_semakan USING btree (id)`
- `audit_integriti_semakan_status_dimulakan_pada_index`: `CREATE INDEX audit_integriti_semakan_status_dimulakan_pada_index ON public.audit_integriti_semakan USING btree (status, dimulakan_pada)`

### `audit_perubahan_medan`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('audit_perubahan_medan_id_seq'::regclass) | TIDAK | PK |
| `id_audit` | bigint | 64 |  | TIDAK |  |
| `nama_medan` | character varying | 150 |  | TIDAK |  |
| `nilai_asal` | text |  |  | YA |  |
| `nilai_baharu` | text |  |  | YA |  |
| `sensitif_ditapis` | boolean |  | false | TIDAK |  |

**Kekangan**

- `audit_perubahan_medan_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `audit_perubahan_medan_id_audit_index`: `CREATE INDEX audit_perubahan_medan_id_audit_index ON public.audit_perubahan_medan USING btree (id_audit)`
- `audit_perubahan_medan_nama_medan_index`: `CREATE INDEX audit_perubahan_medan_nama_medan_index ON public.audit_perubahan_medan USING btree (nama_medan)`
- `audit_perubahan_medan_pkey`: `CREATE UNIQUE INDEX audit_perubahan_medan_pkey ON public.audit_perubahan_medan USING btree (id)`

### `audit_rekod`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('audit_rekod_id_seq'::regclass) | TIDAK | PK |
| `no_urutan` | bigint | 64 |  | TIDAK | UQ |
| `cincangan_sebelum` | character | 64 |  | TIDAK |  |
| `cincangan_rekod` | character | 64 |  | TIDAK | UQ |
| `tarikh_masa_utc` | timestamp with time zone |  |  | TIDAK |  |
| `tarikh_masa_tempatan` | timestamp without time zone |  |  | TIDAK |  |
| `id_pengguna` | bigint | 64 |  | YA |  |
| `pengecam_pengguna` | character varying | 255 |  | YA |  |
| `peranan_semasa` | json |  |  | TIDAK |  |
| `id_sesi` | character varying | 80 |  | YA |  |
| `alamat_ip` | character varying | 45 |  | YA |  |
| `user_agent` | text |  |  | YA |  |
| `jenis_peranti` | character varying | 20 |  | TIDAK |  |
| `lokasi_anggaran` | character varying | 255 |  | YA |  |
| `saluran_capaian` | character varying | 20 |  | TIDAK |  |
| `modul` | character varying | 20 |  | TIDAK |  |
| `nama_entiti` | character varying | 100 |  | YA |  |
| `id_rekod` | character varying | 100 |  | YA |  |
| `jenis_tindakan` | character varying | 40 |  | TIDAK |  |
| `status_transaksi` | character varying | 20 |  | TIDAK |  |
| `sebab_perubahan` | text |  |  | YA |  |
| `id_korelasi` | character varying | 64 |  | YA |  |
| `klasifikasi` | character varying | 20 |  | YA |  |
| `meta` | json |  |  | YA |  |

**Kekangan**

- `audit_rekod_pkey` (p): `PRIMARY KEY (id)`
- `audit_rekod_cincangan_rekod_unique` (u): `UNIQUE (cincangan_rekod)`
- `audit_rekod_no_urutan_unique` (u): `UNIQUE (no_urutan)`

**Indeks**

- `audit_rekod_alamat_ip_index`: `CREATE INDEX audit_rekod_alamat_ip_index ON public.audit_rekod USING btree (alamat_ip)`
- `audit_rekod_cincangan_rekod_unique`: `CREATE UNIQUE INDEX audit_rekod_cincangan_rekod_unique ON public.audit_rekod USING btree (cincangan_rekod)`
- `audit_rekod_id_korelasi_index`: `CREATE INDEX audit_rekod_id_korelasi_index ON public.audit_rekod USING btree (id_korelasi)`
- `audit_rekod_id_pengguna_index`: `CREATE INDEX audit_rekod_id_pengguna_index ON public.audit_rekod USING btree (id_pengguna)`
- `audit_rekod_modul_jenis_tindakan_index`: `CREATE INDEX audit_rekod_modul_jenis_tindakan_index ON public.audit_rekod USING btree (modul, jenis_tindakan)`
- `audit_rekod_nama_entiti_id_rekod_index`: `CREATE INDEX audit_rekod_nama_entiti_id_rekod_index ON public.audit_rekod USING btree (nama_entiti, id_rekod)`
- `audit_rekod_no_urutan_unique`: `CREATE UNIQUE INDEX audit_rekod_no_urutan_unique ON public.audit_rekod USING btree (no_urutan)`
- `audit_rekod_pkey`: `CREATE UNIQUE INDEX audit_rekod_pkey ON public.audit_rekod USING btree (id)`
- `audit_rekod_status_transaksi_index`: `CREATE INDEX audit_rekod_status_transaksi_index ON public.audit_rekod USING btree (status_transaksi)`
- `audit_rekod_tarikh_masa_utc_index`: `CREATE INDEX audit_rekod_tarikh_masa_utc_index ON public.audit_rekod USING btree (tarikh_masa_utc)`

### `audit_retensi_polisi`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('audit_retensi_polisi_id_seq'::regclass) | TIDAK | PK |
| `tempoh_dalam_talian_bulan` | smallint | 16 |  | TIDAK |  |
| `tempoh_arkib_tahun` | smallint | 16 |  | YA |  |
| `catatan` | text |  |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `audit_retensi_minimum` (c): `CHECK ((tempoh_dalam_talian_bulan >= 24))`
- `audit_retensi_polisi_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `audit_retensi_polisi_pkey`: `CREATE UNIQUE INDEX audit_retensi_polisi_pkey ON public.audit_retensi_polisi USING btree (id)`

### `baris_import`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('baris_import_id_seq'::regclass) | TIDAK | PK |
| `id_sesi` | uuid |  |  | TIDAK | FK, UQ |
| `no_baris` | integer | 32 |  | TIDAK | UQ |
| `data` | jsonb |  |  | TIDAK |  |
| `status` | character varying | 20 |  | TIDAK |  |
| `id_rekod` | character varying | 64 |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `baris_import_id_sesi_foreign` (f): `FOREIGN KEY (id_sesi) REFERENCES sesi_import(id) ON DELETE CASCADE`
- `baris_import_pkey` (p): `PRIMARY KEY (id)`
- `baris_import_id_sesi_no_baris_unique` (u): `UNIQUE (id_sesi, no_baris)`

**Indeks**

- `baris_import_id_sesi_no_baris_unique`: `CREATE UNIQUE INDEX baris_import_id_sesi_no_baris_unique ON public.baris_import USING btree (id_sesi, no_baris)`
- `baris_import_pkey`: `CREATE UNIQUE INDEX baris_import_pkey ON public.baris_import USING btree (id)`

### `cache`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `key` | character varying | 255 |  | TIDAK | PK |
| `value` | text |  |  | TIDAK |  |
| `expiration` | integer | 32 |  | TIDAK |  |

**Kekangan**

- `cache_pkey` (p): `PRIMARY KEY (key)`

**Indeks**

- `cache_expiration_index`: `CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration)`
- `cache_pkey`: `CREATE UNIQUE INDEX cache_pkey ON public.cache USING btree (key)`

### `cache_locks`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `key` | character varying | 255 |  | TIDAK | PK |
| `owner` | character varying | 255 |  | TIDAK |  |
| `expiration` | integer | 32 |  | TIDAK |  |

**Kekangan**

- `cache_locks_pkey` (p): `PRIMARY KEY (key)`

**Indeks**

- `cache_locks_expiration_index`: `CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration)`
- `cache_locks_pkey`: `CREATE UNIQUE INDEX cache_locks_pkey ON public.cache_locks USING btree (key)`

### `cadangan_duplikasi`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('cadangan_duplikasi_id_seq'::regclass) | TIDAK | PK |
| `entiti` | character varying | 64 | 'lot_tanah'::character varying | TIDAK | UQ |
| `id_rekod_a` | uuid |  |  | TIDAK | UQ |
| `id_rekod_b` | uuid |  |  | TIDAK | UQ |
| `kriteria_padanan` | jsonb |  |  | TIDAK |  |
| `skor_padanan` | numeric | 5,2 |  | TIDAK |  |
| `keputusan` | character varying | 20 | 'menunggu'::character varying | TIDAK |  |
| `catatan_keputusan` | text |  |  | YA |  |
| `id_data_steward` | bigint | 64 |  | YA | FK |
| `tarikh_keputusan` | timestamp without time zone |  |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `cadangan_duplikasi_id_data_steward_foreign` (f): `FOREIGN KEY (id_data_steward) REFERENCES users(id) ON DELETE SET NULL`
- `cadangan_duplikasi_pkey` (p): `PRIMARY KEY (id)`
- `cadangan_duplikasi_entiti_id_rekod_a_id_rekod_b_unique` (u): `UNIQUE (entiti, id_rekod_a, id_rekod_b)`

**Indeks**

- `cadangan_duplikasi_entiti_id_rekod_a_id_rekod_b_unique`: `CREATE UNIQUE INDEX cadangan_duplikasi_entiti_id_rekod_a_id_rekod_b_unique ON public.cadangan_duplikasi USING btree (entiti, id_rekod_a, id_rekod_b)`
- `cadangan_duplikasi_keputusan_index`: `CREATE INDEX cadangan_duplikasi_keputusan_index ON public.cadangan_duplikasi USING btree (keputusan)`
- `cadangan_duplikasi_pkey`: `CREATE UNIQUE INDEX cadangan_duplikasi_pkey ON public.cadangan_duplikasi USING btree (id)`

### `cukai`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | uuid |  |  | TIDAK | PK |
| `lot_id` | uuid |  |  | TIDAK | FK |
| `jenis` | character varying | 30 |  | TIDAK |  |
| `no_bil` | character varying | 50 |  | YA |  |
| `jumlah` | numeric | 14,2 |  | YA |  |
| `tarikh_akhir` | date |  |  | YA |  |
| `status_bayaran` | character varying | 30 | 'belum_bayar'::character varying | TIDAK |  |
| `kaedah_bayaran` | character varying | 30 |  | YA |  |
| `klasifikasi` | character varying | 10 | 'dalaman'::character varying | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |
| `deleted_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `cukai_lot_id_foreign` (f): `FOREIGN KEY (lot_id) REFERENCES lot_tanah(id)`
- `cukai_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `cukai_lot_id_index`: `CREATE INDEX cukai_lot_id_index ON public.cukai USING btree (lot_id)`
- `cukai_pkey`: `CREATE UNIQUE INDEX cukai_pkey ON public.cukai USING btree (id)`

### `daftar_stewardship_data`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('daftar_stewardship_data_id_seq'::regclass) | TIDAK | PK |
| `komponen_data` | character varying | 100 |  | TIDAK |  |
| `entiti_berkaitan` | character varying | 100 |  | TIDAK |  |
| `jawatan_data_owner` | character varying | 150 |  | TIDAK |  |
| `bahagian_data_owner` | character varying | 150 |  | TIDAK |  |
| `id_data_owner` | bigint | 64 |  | YA | FK |
| `jawatan_data_steward` | character varying | 150 |  | TIDAK |  |
| `bahagian_data_steward` | character varying | 150 |  | TIDAK |  |
| `id_data_steward` | bigint | 64 |  | YA | FK |
| `bahagian_pemilik` | character varying | 30 |  | YA |  |
| `kod_daerah` | character varying | 4 |  | YA |  |
| `peranan_tanggungjawab` | text |  |  | TIDAK |  |
| `tarikh_berkuat_kuasa` | date |  |  | TIDAK |  |
| `status` | character varying | 20 | 'aktif'::character varying | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `daftar_stewardship_data_id_data_owner_foreign` (f): `FOREIGN KEY (id_data_owner) REFERENCES users(id) ON DELETE SET NULL`
- `daftar_stewardship_data_id_data_steward_foreign` (f): `FOREIGN KEY (id_data_steward) REFERENCES users(id) ON DELETE SET NULL`
- `daftar_stewardship_data_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `daftar_stewardship_data_entiti_berkaitan_status_index`: `CREATE INDEX daftar_stewardship_data_entiti_berkaitan_status_index ON public.daftar_stewardship_data USING btree (entiti_berkaitan, status)`
- `daftar_stewardship_data_pkey`: `CREATE UNIQUE INDEX daftar_stewardship_data_pkey ON public.daftar_stewardship_data USING btree (id)`

### `data_spatial` — Data Spatial

Geometri sempadan lot beserta metadata sumber dan pengesahan (SRS-PRO-07).

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('data_spatial_id_seq'::regclass) | TIDAK | PK |
| `lot_id` | uuid |  |  | TIDAK | FK |
| `srid_asal` | integer | 32 | 4326 | TIDAK |  |
| `keluasan_dikira` | numeric | 16,4 |  | YA |  |
| `sumber_data` | character varying | 30 |  | TIDAK |  |
| `rujukan_fail_sumber` | character varying | 255 |  | YA |  |
| `tarikh_perolehan_data` | date |  |  | YA |  |
| `status_pengesahan` | character varying | 30 | 'belum_disahkan'::character varying | TIDAK |  |
| `id_pegawai_pengesah` | bigint | 64 |  | YA | FK |
| `tarikh_pengesahan` | timestamp without time zone |  |  | YA |  |
| `catatan_pertindihan` | text |  |  | YA |  |
| `semasa` | boolean |  | true | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |
| `geometri` | geometry(MultiPolygon,4326) |  |  | TIDAK |  |
| `versi` | integer | 32 | 1 | TIDAK |  |
| `format_asal` | character varying | 20 |  | YA |  |
| `id_dokumen_sumber` | character varying | 64 |  | YA |  |
| `peratus_beza_keluasan` | numeric | 10,2 |  | YA |  |
| `id_pemuat_naik` | bigint | 64 |  | YA | FK |
| `catatan_pengesahan` | text |  |  | YA |  |
| `sumber_sistem` | character varying | 100 |  | YA |  |
| `kunci_asal` | character varying | 100 |  | YA |  |

**Kekangan**

- `data_spatial_status_check` (c): `CHECK (((status_pengesahan)::text = ANY ((ARRAY['belum_disahkan'::character varying, 'disahkan_juruukur'::character varying, 'disahkan_ptg'::character varying, 'bertindih'::character varying])::text[])))`
- `data_spatial_sumber_check` (c): `CHECK (((sumber_data)::text = ANY ((ARRAY['jupem'::character varying, 'ptg'::character varying, 'pbt'::character varying, 'juruukur'::character varying, 'lakaran'::character varying, 'satelit'::character varying, 'sintetik'::character varying])::text[])))`
- `data_spatial_id_pegawai_pengesah_foreign` (f): `FOREIGN KEY (id_pegawai_pengesah) REFERENCES users(id) ON DELETE SET NULL`
- `data_spatial_id_pemuat_naik_foreign` (f): `FOREIGN KEY (id_pemuat_naik) REFERENCES users(id) ON DELETE SET NULL`
- `data_spatial_lot_id_foreign` (f): `FOREIGN KEY (lot_id) REFERENCES lot_tanah(id) ON DELETE CASCADE`
- `data_spatial_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `data_spatial_geometri_gix` *(spatial)*: `CREATE INDEX data_spatial_geometri_gix ON public.data_spatial USING gist (geometri)`
- `data_spatial_lot_id_semasa_index`: `CREATE INDEX data_spatial_lot_id_semasa_index ON public.data_spatial USING btree (lot_id, semasa)`
- `data_spatial_pkey`: `CREATE UNIQUE INDEX data_spatial_pkey ON public.data_spatial USING btree (id)`
- `data_spatial_satu_semasa`: `CREATE UNIQUE INDEX data_spatial_satu_semasa ON public.data_spatial USING btree (lot_id) WHERE semasa`
- `data_spatial_status_idx`: `CREATE INDEX data_spatial_status_idx ON public.data_spatial USING btree (status_pengesahan) WHERE semasa`

### `dokumen`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | uuid |  |  | TIDAK | PK |
| `no_rujukan` | character varying | 30 |  | TIDAK | UQ |
| `kategori_id` | bigint | 64 |  | TIDAK | FK |
| `modul_sumber` | character varying | 20 | 'dms'::character varying | TIDAK |  |
| `jenis_entiti_induk` | character varying | 64 |  | YA |  |
| `id_rekod_induk` | character varying | 64 |  | YA |  |
| `pautan_induk` | character varying | 255 |  | YA |  |
| `tajuk` | character varying | 255 |  | TIDAK |  |
| `nama_fail` | character varying | 255 |  | TIDAK |  |
| `jenis_mime` | character varying | 150 |  | TIDAK |  |
| `sambungan` | character varying | 16 |  | TIDAK |  |
| `saiz_bait` | bigint | 64 |  | TIDAK |  |
| `cincangan_fail` | character | 64 |  | TIDAK |  |
| `klasifikasi_keselamatan` | character varying | 20 | 'dalaman'::character varying | TIDAK |  |
| `status` | character varying | 20 | 'aktif'::character varying | TIDAK |  |
| `tarikh_dokumen` | date |  |  | YA |  |
| `tarikh_luput` | date |  |  | YA |  |
| `tahun` | smallint | 16 |  | YA |  |
| `kod_daerah` | character varying | 20 |  | YA |  |
| `kod_mukim` | character varying | 20 |  | YA |  |
| `pemilik_proses_id` | bigint | 64 |  | YA | FK |
| `id_pencipta` | bigint | 64 |  | YA | FK |
| `versi_semasa` | integer | 32 | 1 | TIDAK |  |
| `kata_kunci` | jsonb |  | '[]'::jsonb | TIDAK |  |
| `metadata` | jsonb |  | '{}'::jsonb | TIDAK |  |
| `teks_kandungan` | text |  |  | YA |  |
| `status_indeks` | character varying | 20 | 'menunggu'::character varying | TIDAK |  |
| `kaedah_indeks` | character varying | 20 |  | YA |  |
| `amaran_luput_pada` | timestamp without time zone |  |  | YA |  |
| `tarikh_arkib` | timestamp without time zone |  |  | YA |  |
| `tarikh_lupus` | timestamp without time zone |  |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |
| `deleted_at` | timestamp without time zone |  |  | YA |  |
| `carian` | tsvector |  |  | YA |  |

**Kekangan**

- `dokumen_id_pencipta_foreign` (f): `FOREIGN KEY (id_pencipta) REFERENCES users(id) ON DELETE SET NULL`
- `dokumen_kategori_id_foreign` (f): `FOREIGN KEY (kategori_id) REFERENCES dokumen_kategori(id)`
- `dokumen_pemilik_proses_id_foreign` (f): `FOREIGN KEY (pemilik_proses_id) REFERENCES users(id) ON DELETE SET NULL`
- `dokumen_pkey` (p): `PRIMARY KEY (id)`
- `dokumen_no_rujukan_unique` (u): `UNIQUE (no_rujukan)`

**Indeks**

- `dokumen_carian_gin`: `CREATE INDEX dokumen_carian_gin ON public.dokumen USING gin (carian)`
- `dokumen_jenis_entiti_induk_id_rekod_induk_index`: `CREATE INDEX dokumen_jenis_entiti_induk_id_rekod_induk_index ON public.dokumen USING btree (jenis_entiti_induk, id_rekod_induk)`
- `dokumen_kod_daerah_index`: `CREATE INDEX dokumen_kod_daerah_index ON public.dokumen USING btree (kod_daerah)`
- `dokumen_nama_fail_trgm`: `CREATE INDEX dokumen_nama_fail_trgm ON public.dokumen USING gin (nama_fail gin_trgm_ops)`
- `dokumen_no_rujukan_unique`: `CREATE UNIQUE INDEX dokumen_no_rujukan_unique ON public.dokumen USING btree (no_rujukan)`
- `dokumen_pkey`: `CREATE UNIQUE INDEX dokumen_pkey ON public.dokumen USING btree (id)`
- `dokumen_status_klasifikasi_keselamatan_index`: `CREATE INDEX dokumen_status_klasifikasi_keselamatan_index ON public.dokumen USING btree (status, klasifikasi_keselamatan)`
- `dokumen_tajuk_trgm`: `CREATE INDEX dokumen_tajuk_trgm ON public.dokumen USING gin (tajuk gin_trgm_ops)`

### `dokumen_arkib`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('dokumen_arkib_id_seq'::regclass) | TIDAK | PK |
| `dokumen_id` | uuid |  |  | TIDAK | FK |
| `tarikh_arkib` | timestamp without time zone |  |  | TIDAK |  |
| `tarikh_layak_lupus` | date |  |  | YA |  |
| `sebab` | character varying | 30 |  | TIDAK |  |
| `diarkib_oleh` | bigint | 64 |  | YA | FK |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `dokumen_arkib_diarkib_oleh_foreign` (f): `FOREIGN KEY (diarkib_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `dokumen_arkib_dokumen_id_foreign` (f): `FOREIGN KEY (dokumen_id) REFERENCES dokumen(id) ON DELETE CASCADE`
- `dokumen_arkib_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `dokumen_arkib_pkey`: `CREATE UNIQUE INDEX dokumen_arkib_pkey ON public.dokumen_arkib USING btree (id)`

### `dokumen_imbasan`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('dokumen_imbasan_id_seq'::regclass) | TIDAK | PK |
| `dokumen_id` | uuid |  |  | YA | FK |
| `kuarantin_id` | bigint | 64 |  | YA | FK |
| `nama_fail` | character varying | 255 |  | TIDAK |  |
| `cincangan_fail` | character | 64 |  | TIDAK |  |
| `enjin` | character varying | 50 |  | TIDAK |  |
| `versi_tandatangan` | character varying | 255 |  | YA |  |
| `keputusan` | character varying | 20 |  | TIDAK |  |
| `nama_ancaman` | character varying | 255 |  | YA |  |
| `tarikh_imbasan` | timestamp without time zone |  |  | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `dokumen_imbasan_dokumen_id_foreign` (f): `FOREIGN KEY (dokumen_id) REFERENCES dokumen(id) ON DELETE SET NULL`
- `dokumen_imbasan_kuarantin_id_foreign` (f): `FOREIGN KEY (kuarantin_id) REFERENCES dokumen_kuarantin(id) ON DELETE SET NULL`
- `dokumen_imbasan_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `dokumen_imbasan_pkey`: `CREATE UNIQUE INDEX dokumen_imbasan_pkey ON public.dokumen_imbasan USING btree (id)`

### `dokumen_kategori`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('dokumen_kategori_id_seq'::regclass) | TIDAK | PK |
| `kod` | character varying | 50 |  | TIDAK | UQ |
| `nama` | character varying | 255 |  | TIDAK |  |
| `keterangan` | text |  |  | YA |  |
| `jenis_fail_dibenarkan` | jsonb |  |  | YA |  |
| `had_saiz_mb` | integer | 32 |  | YA |  |
| `tempoh_retensi_bulan` | integer | 32 |  | YA |  |
| `tempoh_arkib_bulan` | integer | 32 |  | YA |  |
| `tindakan_selepas_arkib` | character varying | 20 | 'semak'::character varying | TIDAK |  |
| `tempoh_amaran_luput_hari` | integer | 32 |  | YA |  |
| `klasifikasi_lalai` | character varying | 20 | 'dalaman'::character varying | TIDAK |  |
| `aktif` | boolean |  | true | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `dokumen_kategori_pkey` (p): `PRIMARY KEY (id)`
- `dokumen_kategori_kod_unique` (u): `UNIQUE (kod)`

**Indeks**

- `dokumen_kategori_kod_unique`: `CREATE UNIQUE INDEX dokumen_kategori_kod_unique ON public.dokumen_kategori USING btree (kod)`
- `dokumen_kategori_pkey`: `CREATE UNIQUE INDEX dokumen_kategori_pkey ON public.dokumen_kategori USING btree (id)`

### `dokumen_kebenaran`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('dokumen_kebenaran_id_seq'::regclass) | TIDAK | PK |
| `dokumen_id` | uuid |  |  | TIDAK | FK, UQ |
| `jenis_penerima` | character varying | 20 |  | TIDAK | UQ |
| `penerima` | character varying | 64 |  | TIDAK | UQ |
| `diberi_oleh` | bigint | 64 |  | YA | FK |
| `tamat_pada` | timestamp without time zone |  |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `dokumen_kebenaran_diberi_oleh_foreign` (f): `FOREIGN KEY (diberi_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `dokumen_kebenaran_dokumen_id_foreign` (f): `FOREIGN KEY (dokumen_id) REFERENCES dokumen(id) ON DELETE CASCADE`
- `dokumen_kebenaran_pkey` (p): `PRIMARY KEY (id)`
- `dokumen_kebenaran_dokumen_id_jenis_penerima_penerima_unique` (u): `UNIQUE (dokumen_id, jenis_penerima, penerima)`

**Indeks**

- `dokumen_kebenaran_dokumen_id_jenis_penerima_penerima_unique`: `CREATE UNIQUE INDEX dokumen_kebenaran_dokumen_id_jenis_penerima_penerima_unique ON public.dokumen_kebenaran USING btree (dokumen_id, jenis_penerima, penerima)`
- `dokumen_kebenaran_pkey`: `CREATE UNIQUE INDEX dokumen_kebenaran_pkey ON public.dokumen_kebenaran USING btree (id)`

### `dokumen_kuarantin`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('dokumen_kuarantin_id_seq'::regclass) | TIDAK | PK |
| `nama_fail` | character varying | 255 |  | TIDAK |  |
| `saiz_bait` | bigint | 64 |  | TIDAK |  |
| `cincangan_fail` | character | 64 |  | TIDAK |  |
| `kunci_storan` | character varying | 255 |  | YA |  |
| `kunci_data_bersulit` | text |  |  | YA |  |
| `id_kunci_induk` | character varying | 16 |  | YA |  |
| `nama_ancaman` | character varying | 255 |  | YA |  |
| `jenis_entiti_induk` | character varying | 64 |  | YA |  |
| `id_rekod_induk` | character varying | 64 |  | YA |  |
| `dimuat_naik_oleh` | bigint | 64 |  | YA | FK |
| `dimusnahkan_pada` | timestamp without time zone |  |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `dokumen_kuarantin_dimuat_naik_oleh_foreign` (f): `FOREIGN KEY (dimuat_naik_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `dokumen_kuarantin_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `dokumen_kuarantin_pkey`: `CREATE UNIQUE INDEX dokumen_kuarantin_pkey ON public.dokumen_kuarantin USING btree (id)`

### `dokumen_medan_metadata`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('dokumen_medan_metadata_id_seq'::regclass) | TIDAK | PK |
| `kategori_id` | bigint | 64 |  | YA | FK, UQ |
| `kod` | character varying | 50 |  | TIDAK | UQ |
| `label` | character varying | 255 |  | TIDAK |  |
| `jenis` | character varying | 20 |  | TIDAK |  |
| `pilihan` | jsonb |  |  | YA |  |
| `wajib` | boolean |  | false | TIDAK |  |
| `aktif` | boolean |  | true | TIDAK |  |
| `susunan` | integer | 32 | 0 | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `dokumen_medan_metadata_kategori_id_foreign` (f): `FOREIGN KEY (kategori_id) REFERENCES dokumen_kategori(id) ON DELETE CASCADE`
- `dokumen_medan_metadata_pkey` (p): `PRIMARY KEY (id)`
- `dokumen_medan_metadata_kategori_id_kod_unique` (u): `UNIQUE (kategori_id, kod)`

**Indeks**

- `dokumen_medan_metadata_kategori_id_kod_unique`: `CREATE UNIQUE INDEX dokumen_medan_metadata_kategori_id_kod_unique ON public.dokumen_medan_metadata USING btree (kategori_id, kod)`
- `dokumen_medan_metadata_pkey`: `CREATE UNIQUE INDEX dokumen_medan_metadata_pkey ON public.dokumen_medan_metadata USING btree (id)`

### `dokumen_pelupusan`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('dokumen_pelupusan_id_seq'::regclass) | TIDAK | PK |
| `dokumen_id` | uuid |  |  | TIDAK | FK |
| `justifikasi` | text |  |  | TIDAK |  |
| `dimohon_oleh` | bigint | 64 |  | YA | FK |
| `id_aliran_kerja` | character varying | 64 |  | YA |  |
| `status` | character varying | 20 | 'menunggu'::character varying | TIDAK |  |
| `diputuskan_oleh` | bigint | 64 |  | YA |  |
| `catatan_keputusan` | text |  |  | YA |  |
| `tarikh_keputusan` | timestamp without time zone |  |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `dokumen_pelupusan_dimohon_oleh_foreign` (f): `FOREIGN KEY (dimohon_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `dokumen_pelupusan_dokumen_id_foreign` (f): `FOREIGN KEY (dokumen_id) REFERENCES dokumen(id) ON DELETE CASCADE`
- `dokumen_pelupusan_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `dokumen_pelupusan_pkey`: `CREATE UNIQUE INDEX dokumen_pelupusan_pkey ON public.dokumen_pelupusan USING btree (id)`

### `dokumen_versi`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('dokumen_versi_id_seq'::regclass) | TIDAK | PK |
| `dokumen_id` | uuid |  |  | TIDAK | FK, UQ |
| `no_versi` | integer | 32 |  | TIDAK | UQ |
| `nama_fail` | character varying | 255 |  | TIDAK |  |
| `jenis_mime` | character varying | 150 |  | TIDAK |  |
| `saiz_bait` | bigint | 64 |  | TIDAK |  |
| `cincangan_fail` | character | 64 |  | TIDAK |  |
| `kunci_storan` | character varying | 255 |  | YA |  |
| `kunci_data_bersulit` | text |  |  | YA |  |
| `id_kunci_induk` | character varying | 16 |  | YA |  |
| `algoritma` | character varying | 30 | 'AES-256-GCM'::character varying | TIDAK |  |
| `dimuat_naik_oleh` | bigint | 64 |  | YA | FK |
| `catatan` | text |  |  | YA |  |
| `metadata_snapshot` | jsonb |  | '{}'::jsonb | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `dokumen_versi_dimuat_naik_oleh_foreign` (f): `FOREIGN KEY (dimuat_naik_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `dokumen_versi_dokumen_id_foreign` (f): `FOREIGN KEY (dokumen_id) REFERENCES dokumen(id) ON DELETE CASCADE`
- `dokumen_versi_pkey` (p): `PRIMARY KEY (id)`
- `dokumen_versi_dokumen_id_no_versi_unique` (u): `UNIQUE (dokumen_id, no_versi)`

**Indeks**

- `dokumen_versi_dokumen_id_no_versi_unique`: `CREATE UNIQUE INDEX dokumen_versi_dokumen_id_no_versi_unique ON public.dokumen_versi USING btree (dokumen_id, no_versi)`
- `dokumen_versi_pkey`: `CREATE UNIQUE INDEX dokumen_versi_pkey ON public.dokumen_versi USING btree (id)`

### `failed_jobs`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('failed_jobs_id_seq'::regclass) | TIDAK | PK |
| `uuid` | character varying | 255 |  | TIDAK | UQ |
| `connection` | text |  |  | TIDAK |  |
| `queue` | text |  |  | TIDAK |  |
| `payload` | text |  |  | TIDAK |  |
| `exception` | text |  |  | TIDAK |  |
| `failed_at` | timestamp without time zone |  | CURRENT_TIMESTAMP | TIDAK |  |

**Kekangan**

- `failed_jobs_pkey` (p): `PRIMARY KEY (id)`
- `failed_jobs_uuid_unique` (u): `UNIQUE (uuid)`

**Indeks**

- `failed_jobs_pkey`: `CREATE UNIQUE INDEX failed_jobs_pkey ON public.failed_jobs USING btree (id)`
- `failed_jobs_uuid_unique`: `CREATE UNIQUE INDEX failed_jobs_uuid_unique ON public.failed_jobs USING btree (uuid)`

### `jadual_retensi`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('jadual_retensi_id_seq'::regclass) | TIDAK | PK |
| `kategori_rekod` | character varying | 50 |  | TIDAK | UQ |
| `keterangan` | character varying | 255 |  | TIDAK |  |
| `entiti` | character varying | 64 | 'lot_tanah'::character varying | TIDAK |  |
| `syarat` | jsonb |  | '{}'::jsonb | TIDAK |  |
| `tempoh_retensi_tahun` | smallint | 16 |  | TIDAK |  |
| `tindakan_selepas_retensi` | character varying | 20 | 'arkib'::character varying | TIDAK |  |
| `rujukan_dasar_mais` | character varying | 255 |  | YA |  |
| `memerlukan_kelulusan` | boolean |  | true | TIDAK |  |
| `id_pelulus` | bigint | 64 |  | YA | FK |
| `aktif` | boolean |  | true | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `jadual_retensi_id_pelulus_foreign` (f): `FOREIGN KEY (id_pelulus) REFERENCES users(id) ON DELETE SET NULL`
- `jadual_retensi_pkey` (p): `PRIMARY KEY (id)`
- `jadual_retensi_kategori_rekod_unique` (u): `UNIQUE (kategori_rekod)`

**Indeks**

- `jadual_retensi_kategori_rekod_unique`: `CREATE UNIQUE INDEX jadual_retensi_kategori_rekod_unique ON public.jadual_retensi USING btree (kategori_rekod)`
- `jadual_retensi_pkey`: `CREATE UNIQUE INDEX jadual_retensi_pkey ON public.jadual_retensi USING btree (id)`

### `job_batches`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | character varying | 255 |  | TIDAK | PK |
| `name` | character varying | 255 |  | TIDAK |  |
| `total_jobs` | integer | 32 |  | TIDAK |  |
| `pending_jobs` | integer | 32 |  | TIDAK |  |
| `failed_jobs` | integer | 32 |  | TIDAK |  |
| `failed_job_ids` | text |  |  | TIDAK |  |
| `options` | text |  |  | YA |  |
| `cancelled_at` | integer | 32 |  | YA |  |
| `created_at` | integer | 32 |  | TIDAK |  |
| `finished_at` | integer | 32 |  | YA |  |

**Kekangan**

- `job_batches_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `job_batches_pkey`: `CREATE UNIQUE INDEX job_batches_pkey ON public.job_batches USING btree (id)`

### `jobs`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('jobs_id_seq'::regclass) | TIDAK | PK |
| `queue` | character varying | 255 |  | TIDAK |  |
| `payload` | text |  |  | TIDAK |  |
| `attempts` | smallint | 16 |  | TIDAK |  |
| `reserved_at` | integer | 32 |  | YA |  |
| `available_at` | integer | 32 |  | TIDAK |  |
| `created_at` | integer | 32 |  | TIDAK |  |

**Kekangan**

- `jobs_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `jobs_pkey`: `CREATE UNIQUE INDEX jobs_pkey ON public.jobs USING btree (id)`
- `jobs_queue_index`: `CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue)`

### `kamus_data`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('kamus_data_id_seq'::regclass) | TIDAK | PK |
| `nama_jadual` | character varying | 64 |  | TIDAK | UQ |
| `nama_medan` | character varying | 64 |  | YA | UQ |
| `nama_perniagaan` | character varying | 150 |  | TIDAK |  |
| `definisi` | text |  |  | YA |  |
| `format` | character varying | 150 |  | YA |  |
| `nilai_dibenarkan` | character varying | 500 |  | YA |  |
| `wajib` | boolean |  |  | YA |  |
| `data_owner` | character varying | 150 |  | YA |  |
| `data_steward` | character varying | 150 |  | YA |  |
| `klasifikasi` | character varying | 10 | 'dalaman'::character varying | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `kamus_data_pkey` (p): `PRIMARY KEY (id)`
- `kamus_data_nama_jadual_nama_medan_unique` (u): `UNIQUE (nama_jadual, nama_medan)`

**Indeks**

- `kamus_data_nama_jadual_nama_medan_unique`: `CREATE UNIQUE INDEX kamus_data_nama_jadual_nama_medan_unique ON public.kamus_data USING btree (nama_jadual, nama_medan)`
- `kamus_data_pkey`: `CREATE UNIQUE INDEX kamus_data_pkey ON public.kamus_data USING btree (id)`

### `kata_laluan_sejarah`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('kata_laluan_sejarah_id_seq'::regclass) | TIDAK | PK |
| `user_id` | bigint | 64 |  | TIDAK | FK |
| `kata_laluan` | character varying | 255 |  | TIDAK |  |
| `created_at` | timestamp without time zone |  | CURRENT_TIMESTAMP | TIDAK |  |

**Kekangan**

- `kata_laluan_sejarah_user_id_foreign` (f): `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE`
- `kata_laluan_sejarah_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `kata_laluan_sejarah_pkey`: `CREATE UNIQUE INDEX kata_laluan_sejarah_pkey ON public.kata_laluan_sejarah USING btree (id)`
- `kata_laluan_sejarah_user_id_created_at_index`: `CREATE INDEX kata_laluan_sejarah_user_id_created_at_index ON public.kata_laluan_sejarah USING btree (user_id, created_at)`

### `kebenaran`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `kunci` | character varying | 150 |  | TIDAK | PK |
| `modul` | character varying | 30 |  | TIDAK |  |
| `fungsi` | character varying | 60 |  | TIDAK |  |
| `tindakan` | character varying | 30 |  | TIDAK |  |
| `label` | character varying | 255 |  | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `kebenaran_pkey` (p): `PRIMARY KEY (kunci)`

**Indeks**

- `kebenaran_modul_index`: `CREATE INDEX kebenaran_modul_index ON public.kebenaran USING btree (modul)`
- `kebenaran_pkey`: `CREATE UNIQUE INDEX kebenaran_pkey ON public.kebenaran USING btree (kunci)`

### `kes_mahkamah` — Kes Mahkamah

Kes perundangan berkaitan lot (N:N).

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | uuid |  |  | TIDAK | PK |
| `no_kes` | character varying | 80 |  | TIDAK |  |
| `jenis` | character varying | 50 |  | YA |  |
| `mahkamah` | character varying | 150 |  | YA |  |
| `status` | character varying | 30 | 'aktif'::character varying | TIDAK |  |
| `kos_guaman` | numeric | 14,2 |  | YA |  |
| `klasifikasi` | character varying | 10 | 'rahsia'::character varying | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |
| `deleted_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `kes_mahkamah_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `kes_mahkamah_pkey`: `CREATE UNIQUE INDEX kes_mahkamah_pkey ON public.kes_mahkamah USING btree (id)`

### `kes_mahkamah_lot`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `kes_mahkamah_id` | uuid |  |  | TIDAK | FK, PK |
| `lot_id` | uuid |  |  | TIDAK | FK, PK |

**Kekangan**

- `kes_mahkamah_lot_kes_mahkamah_id_foreign` (f): `FOREIGN KEY (kes_mahkamah_id) REFERENCES kes_mahkamah(id) ON DELETE CASCADE`
- `kes_mahkamah_lot_lot_id_foreign` (f): `FOREIGN KEY (lot_id) REFERENCES lot_tanah(id)`
- `kes_mahkamah_lot_pkey` (p): `PRIMARY KEY (kes_mahkamah_id, lot_id)`

**Indeks**

- `kes_mahkamah_lot_pkey`: `CREATE UNIQUE INDEX kes_mahkamah_lot_pkey ON public.kes_mahkamah_lot USING btree (kes_mahkamah_id, lot_id)`

### `klasifikasi_data`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('klasifikasi_data_id_seq'::regclass) | TIDAK | PK |
| `komponen_data` | character varying | 100 |  | TIDAK | UQ |
| `entiti` | character varying | 64 |  | TIDAK |  |
| `aras_kerahsiaan` | character varying | 10 |  | TIDAK |  |
| `keperluan_encryption` | character varying | 150 |  | TIDAK |  |
| `peranan_dibenarkan` | character varying | 255 |  | TIDAK |  |
| `kebenaran_diperlukan` | character varying | 100 |  | YA |  |
| `tarikh_semakan` | date |  |  | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `klasifikasi_data_pkey` (p): `PRIMARY KEY (id)`
- `klasifikasi_data_komponen_data_unique` (u): `UNIQUE (komponen_data)`

**Indeks**

- `klasifikasi_data_komponen_data_unique`: `CREATE UNIQUE INDEX klasifikasi_data_komponen_data_unique ON public.klasifikasi_data USING btree (komponen_data)`
- `klasifikasi_data_pkey`: `CREATE UNIQUE INDEX klasifikasi_data_pkey ON public.klasifikasi_data USING btree (id)`

### `kontrak_pajakan` — Kontrak Pajakan

Kontrak pajakan / sewaan bagi lot.

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | uuid |  |  | TIDAK | PK |
| `lot_id` | uuid |  |  | TIDAK | FK |
| `penyewa` | character varying | 200 |  | YA |  |
| `tarikh_mula` | date |  |  | YA |  |
| `tarikh_tamat` | date |  |  | YA |  |
| `kadar` | numeric | 14,2 |  | YA |  |
| `deposit` | numeric | 14,2 |  | YA |  |
| `terma` | text |  |  | YA |  |
| `status` | character varying | 30 | 'draf'::character varying | TIDAK |  |
| `klasifikasi` | character varying | 10 | 'sulit'::character varying | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |
| `deleted_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `kontrak_pajakan_lot_id_foreign` (f): `FOREIGN KEY (lot_id) REFERENCES lot_tanah(id)`
- `kontrak_pajakan_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `kontrak_pajakan_lot_id_index`: `CREATE INDEX kontrak_pajakan_lot_id_index ON public.kontrak_pajakan USING btree (lot_id)`
- `kontrak_pajakan_pkey`: `CREATE UNIQUE INDEX kontrak_pajakan_pkey ON public.kontrak_pajakan USING btree (id)`

### `kunci_api_pro`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('kunci_api_pro_id_seq'::regclass) | TIDAK | PK |
| `id_token` | bigint | 64 |  | TIDAK | UQ |
| `id_pemilik` | bigint | 64 |  | TIDAK | FK |
| `sistem_pemanggil` | character varying | 100 |  | TIDAK |  |
| `skop` | jsonb |  |  | TIDAK |  |
| `had_kadar_seminit` | integer | 32 | 60 | TIDAK |  |
| `tamat_pada` | timestamp without time zone |  |  | YA |  |
| `dibatalkan_pada` | timestamp without time zone |  |  | YA |  |
| `dibatalkan_oleh` | bigint | 64 |  | YA | FK |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `kunci_api_pro_dibatalkan_oleh_foreign` (f): `FOREIGN KEY (dibatalkan_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `kunci_api_pro_id_pemilik_foreign` (f): `FOREIGN KEY (id_pemilik) REFERENCES users(id) ON DELETE CASCADE`
- `kunci_api_pro_pkey` (p): `PRIMARY KEY (id)`
- `kunci_api_pro_id_token_unique` (u): `UNIQUE (id_token)`

**Indeks**

- `kunci_api_pro_id_token_unique`: `CREATE UNIQUE INDEX kunci_api_pro_id_token_unique ON public.kunci_api_pro USING btree (id_token)`
- `kunci_api_pro_pkey`: `CREATE UNIQUE INDEX kunci_api_pro_pkey ON public.kunci_api_pro USING btree (id)`

### `log_akses_profil`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('log_akses_profil_id_seq'::regclass) | TIDAK | PK |
| `lot_id` | uuid |  |  | TIDAK | FK |
| `id_pengguna` | bigint | 64 |  | YA | FK |
| `jenis_capaian` | character varying | 20 |  | TIDAK |  |
| `saluran` | character varying | 20 | 'web'::character varying | TIDAK |  |
| `alamat_ip` | character varying | 45 |  | YA |  |
| `butiran` | jsonb |  |  | YA |  |
| `tarikh_masa` | timestamp without time zone |  |  | TIDAK |  |

**Kekangan**

- `log_akses_profil_id_pengguna_foreign` (f): `FOREIGN KEY (id_pengguna) REFERENCES users(id) ON DELETE SET NULL`
- `log_akses_profil_lot_id_foreign` (f): `FOREIGN KEY (lot_id) REFERENCES lot_tanah(id) ON DELETE CASCADE`
- `log_akses_profil_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `log_akses_profil_lot_id_tarikh_masa_index`: `CREATE INDEX log_akses_profil_lot_id_tarikh_masa_index ON public.log_akses_profil USING btree (lot_id, tarikh_masa)`
- `log_akses_profil_pkey`: `CREATE UNIQUE INDEX log_akses_profil_pkey ON public.log_akses_profil USING btree (id)`

### `log_log_masuk`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('log_log_masuk_id_seq'::regclass) | TIDAK | PK |
| `user_id` | bigint | 64 |  | YA | FK |
| `pengecam` | character varying | 255 |  | YA |  |
| `kaedah` | character varying | 20 |  | TIDAK |  |
| `berjaya` | boolean |  |  | TIDAK |  |
| `sebab` | character varying | 60 |  | YA |  |
| `alamat_ip` | character varying | 45 |  | YA |  |
| `user_agent` | text |  |  | YA |  |
| `peranti` | character varying | 30 |  | YA |  |
| `pelayar` | character varying | 60 |  | YA |  |
| `platform` | character varying | 60 |  | YA |  |
| `sidik_peranti` | character varying | 64 |  | YA |  |
| `lokasi` | character varying | 255 |  | YA |  |
| `negara` | character varying | 60 |  | YA |  |
| `mencurigakan` | boolean |  | false | TIDAK |  |
| `created_at` | timestamp without time zone |  | CURRENT_TIMESTAMP | TIDAK |  |

**Kekangan**

- `log_log_masuk_user_id_foreign` (f): `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL`
- `log_log_masuk_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `log_log_masuk_alamat_ip_created_at_index`: `CREATE INDEX log_log_masuk_alamat_ip_created_at_index ON public.log_log_masuk USING btree (alamat_ip, created_at)`
- `log_log_masuk_created_at_index`: `CREATE INDEX log_log_masuk_created_at_index ON public.log_log_masuk USING btree (created_at)`
- `log_log_masuk_pkey`: `CREATE UNIQUE INDEX log_log_masuk_pkey ON public.log_log_masuk USING btree (id)`
- `log_log_masuk_user_id_created_at_index`: `CREATE INDEX log_log_masuk_user_id_created_at_index ON public.log_log_masuk USING btree (user_id, created_at)`

### `log_penggunaan_api`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('log_penggunaan_api_id_seq'::regclass) | TIDAK | PK |
| `id_token` | bigint | 64 |  | YA |  |
| `sistem_pemanggil` | character varying | 100 |  | YA |  |
| `kaedah` | character varying | 10 |  | TIDAK |  |
| `laluan` | character varying | 255 |  | TIDAK |  |
| `kod_status` | smallint | 16 |  | TIDAK |  |
| `alamat_ip` | character varying | 45 |  | YA |  |
| `tarikh_masa` | timestamp without time zone |  |  | TIDAK |  |

**Kekangan**

- `log_penggunaan_api_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `log_penggunaan_api_id_token_tarikh_masa_index`: `CREATE INDEX log_penggunaan_api_id_token_tarikh_masa_index ON public.log_penggunaan_api USING btree (id_token, tarikh_masa)`
- `log_penggunaan_api_pkey`: `CREATE UNIQUE INDEX log_penggunaan_api_pkey ON public.log_penggunaan_api USING btree (id)`

### `lot_tanah` — Lot Tanah

Rekod induk setiap lot tanah milik atau di bawah pengurusan MAIS. Semua rekod satelit memegang lot_id.

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | uuid |  |  | TIDAK | PK |
| `no_lot` | character varying | 50 |  | TIDAK |  |
| `kod_mukim` | character varying | 6 |  | TIDAK |  |
| `kod_daerah` | character varying | 4 |  | TIDAK |  |
| `kod_negeri` | character varying | 2 | '10'::character varying | TIDAK |  |
| `keluasan` | numeric | 16,4 |  | TIDAK |  |
| `unit_keluasan` | character varying | 10 | 'm2'::character varying | TIDAK |  |
| `kategori_guna_tanah` | character varying | 30 |  | YA |  |
| `zoning` | character varying | 50 |  | YA |  |
| `syarat_nyata` | text |  |  | YA |  |
| `bahagian_pemilik` | character varying | 30 |  | TIDAK |  |
| `status_lot` | character varying | 30 | 'aktif'::character varying | TIDAK |  |
| `klasifikasi` | character varying | 10 | 'dalaman'::character varying | TIDAK |  |
| `id_data_owner` | bigint | 64 |  | YA | FK |
| `id_data_steward` | bigint | 64 |  | YA | FK |
| `versi_rekod` | integer | 32 | 1 | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |
| `deleted_at` | timestamp without time zone |  |  | YA |  |
| `koordinat_pusat` | geometry(Point,4326) |  |  | YA |  |
| `no_lot_normal` | character varying | 50 |  | YA |  |
| `sintetik` | boolean |  | false | TIDAK |  |
| `sumber_sistem` | character varying | 100 |  | YA |  |
| `fail_asal` | character varying | 255 |  | YA |  |
| `kunci_asal` | character varying | 100 |  | YA |  |
| `id_migrasi_larian` | uuid |  |  | YA |  |
| `id_sesi_import` | uuid |  |  | YA |  |
| `id_lot_gabung` | uuid |  |  | YA |  |
| `status_sebelum_arkib` | character varying | 30 |  | YA |  |
| `diarkib_pada` | timestamp without time zone |  |  | YA |  |
| `tag` | jsonb |  | '[]'::jsonb | TIDAK |  |
| `catatan` | text |  |  | YA |  |
| `id_pencipta` | bigint | 64 |  | YA | FK |

**Kekangan**

- `lot_tanah_bahagian_check` (c): `CHECK (((bahagian_pemilik IS NULL) OR ((bahagian_pemilik)::text = ANY ((ARRAY['hartanah'::character varying, 'wakaf'::character varying, 'baitulmal'::character varying])::text[]))))`
- `lot_tanah_keluasan_check` (c): `CHECK (((keluasan IS NULL) OR (keluasan > (0)::numeric)))`
- `lot_tanah_klasifikasi_check` (c): `CHECK (((klasifikasi)::text = ANY ((ARRAY['awam'::character varying, 'dalaman'::character varying, 'sulit'::character varying, 'rahsia'::character varying])::text[])))`
- `lot_tanah_id_data_owner_foreign` (f): `FOREIGN KEY (id_data_owner) REFERENCES users(id) ON DELETE SET NULL`
- `lot_tanah_id_data_steward_foreign` (f): `FOREIGN KEY (id_data_steward) REFERENCES users(id) ON DELETE SET NULL`
- `lot_tanah_id_pencipta_foreign` (f): `FOREIGN KEY (id_pencipta) REFERENCES users(id) ON DELETE SET NULL`
- `lot_tanah_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `lot_tanah_bahagian_pemilik_index`: `CREATE INDEX lot_tanah_bahagian_pemilik_index ON public.lot_tanah USING btree (bahagian_pemilik)`
- `lot_tanah_klasifikasi_index`: `CREATE INDEX lot_tanah_klasifikasi_index ON public.lot_tanah USING btree (klasifikasi)`
- `lot_tanah_kod_daerah_kod_mukim_index`: `CREATE INDEX lot_tanah_kod_daerah_kod_mukim_index ON public.lot_tanah USING btree (kod_daerah, kod_mukim)`
- `lot_tanah_koordinat_pusat_gix` *(spatial)*: `CREATE INDEX lot_tanah_koordinat_pusat_gix ON public.lot_tanah USING gist (koordinat_pusat)`
- `lot_tanah_no_lot_mukim_unique`: `CREATE UNIQUE INDEX lot_tanah_no_lot_mukim_unique ON public.lot_tanah USING btree (kod_mukim, no_lot) WHERE (deleted_at IS NULL)`
- `lot_tanah_no_lot_normal_index`: `CREATE INDEX lot_tanah_no_lot_normal_index ON public.lot_tanah USING btree (no_lot_normal)`
- `lot_tanah_no_lot_normal_trgm`: `CREATE INDEX lot_tanah_no_lot_normal_trgm ON public.lot_tanah USING gin (no_lot_normal gin_trgm_ops)`
- `lot_tanah_pkey`: `CREATE UNIQUE INDEX lot_tanah_pkey ON public.lot_tanah USING btree (id)`
- `lot_tanah_salasilah_unique`: `CREATE UNIQUE INDEX lot_tanah_salasilah_unique ON public.lot_tanah USING btree (sumber_sistem, fail_asal, kunci_asal) WHERE ((sumber_sistem IS NOT NULL) AND (deleted_at IS NULL))`
- `lot_tanah_status_lot_index`: `CREATE INDEX lot_tanah_status_lot_index ON public.lot_tanah USING btree (status_lot)`

### `migrasi_larian`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | uuid |  |  | TIDAK | PK |
| `jenis` | character varying | 50 |  | TIDAK |  |
| `sumber_sistem` | character varying | 100 |  | TIDAK |  |
| `parameter` | json |  |  | YA |  |
| `status` | character varying | 20 | 'berjalan'::character varying | TIDAK |  |
| `bilangan_diterima` | integer | 32 | 0 | TIDAK |  |
| `bilangan_ditolak` | integer | 32 | 0 | TIDAK |  |
| `catatan` | text |  |  | YA |  |
| `mula_pada` | timestamp without time zone |  |  | TIDAK |  |
| `tamat_pada` | timestamp without time zone |  |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `migrasi_larian_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `migrasi_larian_pkey`: `CREATE UNIQUE INDEX migrasi_larian_pkey ON public.migrasi_larian USING btree (id)`

### `migrations`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | integer | 32 | nextval('migrations_id_seq'::regclass) | TIDAK | PK |
| `migration` | character varying | 255 |  | TIDAK |  |
| `batch` | integer | 32 |  | TIDAK |  |

**Kekangan**

- `migrations_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `migrations_pkey`: `CREATE UNIQUE INDEX migrations_pkey ON public.migrations USING btree (id)`

### `notifications`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | uuid |  |  | TIDAK | PK |
| `type` | character varying | 255 |  | TIDAK |  |
| `notifiable_type` | character varying | 255 |  | TIDAK |  |
| `notifiable_id` | bigint | 64 |  | TIDAK |  |
| `data` | text |  |  | TIDAK |  |
| `read_at` | timestamp without time zone |  |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `notifications_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `notifications_notifiable_type_notifiable_id_index`: `CREATE INDEX notifications_notifiable_type_notifiable_id_index ON public.notifications USING btree (notifiable_type, notifiable_id)`
- `notifications_pkey`: `CREATE UNIQUE INDEX notifications_pkey ON public.notifications USING btree (id)`

### `ntf_eskalasi_log`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('ntf_eskalasi_log_id_seq'::regclass) | TIDAK | PK |
| `status_pengguna_id` | bigint | 64 |  | YA | FK |
| `mesej_id` | bigint | 64 |  | TIDAK | FK |
| `penerima_asal_id` | bigint | 64 |  | YA | FK |
| `penyelia_id` | bigint | 64 |  | YA | FK |
| `mesej_eskalasi_id` | bigint | 64 |  | YA | FK |
| `tarikh_eskalasi` | timestamp without time zone |  |  | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `ntf_eskalasi_log_mesej_eskalasi_id_foreign` (f): `FOREIGN KEY (mesej_eskalasi_id) REFERENCES ntf_mesej(id) ON DELETE SET NULL`
- `ntf_eskalasi_log_mesej_id_foreign` (f): `FOREIGN KEY (mesej_id) REFERENCES ntf_mesej(id)`
- `ntf_eskalasi_log_penerima_asal_id_foreign` (f): `FOREIGN KEY (penerima_asal_id) REFERENCES users(id) ON DELETE SET NULL`
- `ntf_eskalasi_log_penyelia_id_foreign` (f): `FOREIGN KEY (penyelia_id) REFERENCES users(id) ON DELETE SET NULL`
- `ntf_eskalasi_log_status_pengguna_id_foreign` (f): `FOREIGN KEY (status_pengguna_id) REFERENCES ntf_status_pengguna(id) ON DELETE SET NULL`
- `ntf_eskalasi_log_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `ntf_eskalasi_log_pkey`: `CREATE UNIQUE INDEX ntf_eskalasi_log_pkey ON public.ntf_eskalasi_log USING btree (id)`

### `ntf_jenis`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('ntf_jenis_id_seq'::regclass) | TIDAK | PK |
| `kod` | character varying | 100 |  | TIDAK | UQ |
| `nama` | character varying | 255 |  | TIDAK |  |
| `modul` | character varying | 20 |  | TIDAK |  |
| `keutamaan` | character varying | 10 |  | TIDAK |  |
| `wajib` | boolean |  | false | TIDAK |  |
| `saluran_lalai` | jsonb |  |  | TIDAK |  |
| `kekerapan` | character varying | 20 | 'serta_merta'::character varying | TIDAK |  |
| `tempoh_eskalasi_minit` | integer | 32 |  | YA |  |
| `tempoh_pendiaman_minit` | integer | 32 | 0 | TIDAK |  |
| `hari_peringatan` | jsonb |  |  | YA |  |
| `medan` | jsonb |  | '[]'::jsonb | TIDAK |  |
| `medan_sensitif` | jsonb |  | '[]'::jsonb | TIDAK |  |
| `aktif` | boolean |  | true | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `ntf_jenis_pkey` (p): `PRIMARY KEY (id)`
- `ntf_jenis_kod_unique` (u): `UNIQUE (kod)`

**Indeks**

- `ntf_jenis_kod_unique`: `CREATE UNIQUE INDEX ntf_jenis_kod_unique ON public.ntf_jenis USING btree (kod)`
- `ntf_jenis_modul_index`: `CREATE INDEX ntf_jenis_modul_index ON public.ntf_jenis USING btree (modul)`
- `ntf_jenis_pkey`: `CREATE UNIQUE INDEX ntf_jenis_pkey ON public.ntf_jenis USING btree (id)`

### `ntf_keutamaan_pengguna`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('ntf_keutamaan_pengguna_id_seq'::regclass) | TIDAK | PK |
| `pengguna_id` | bigint | 64 |  | TIDAK | FK, UQ |
| `jenis_id` | bigint | 64 |  | TIDAK | FK, UQ |
| `aktif` | boolean |  | true | TIDAK |  |
| `saluran` | jsonb |  |  | YA |  |
| `ringkasan` | boolean |  | false | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `ntf_keutamaan_pengguna_jenis_id_foreign` (f): `FOREIGN KEY (jenis_id) REFERENCES ntf_jenis(id) ON DELETE CASCADE`
- `ntf_keutamaan_pengguna_pengguna_id_foreign` (f): `FOREIGN KEY (pengguna_id) REFERENCES users(id) ON DELETE CASCADE`
- `ntf_keutamaan_pengguna_pkey` (p): `PRIMARY KEY (id)`
- `ntf_keutamaan_pengguna_pengguna_id_jenis_id_unique` (u): `UNIQUE (pengguna_id, jenis_id)`

**Indeks**

- `ntf_keutamaan_pengguna_pengguna_id_jenis_id_unique`: `CREATE UNIQUE INDEX ntf_keutamaan_pengguna_pengguna_id_jenis_id_unique ON public.ntf_keutamaan_pengguna USING btree (pengguna_id, jenis_id)`
- `ntf_keutamaan_pengguna_pkey`: `CREATE UNIQUE INDEX ntf_keutamaan_pengguna_pkey ON public.ntf_keutamaan_pengguna USING btree (id)`

### `ntf_mesej`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('ntf_mesej_id_seq'::regclass) | TIDAK | PK |
| `jenis_id` | bigint | 64 |  | TIDAK | FK |
| `templat_id` | bigint | 64 |  | YA | FK |
| `keutamaan` | character varying | 10 |  | TIDAK |  |
| `modul` | character varying | 20 |  | TIDAK |  |
| `subjek` | character varying | 255 |  | TIDAK |  |
| `kandungan` | text |  |  | TIDAK |  |
| `pautan` | character varying | 255 |  | YA |  |
| `data` | jsonb |  | '{}'::jsonb | TIDAK |  |
| `kunci_dedupe` | character varying | 191 |  | YA |  |
| `dicipta_oleh` | bigint | 64 |  | YA | FK |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `ntf_mesej_dicipta_oleh_foreign` (f): `FOREIGN KEY (dicipta_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `ntf_mesej_jenis_id_foreign` (f): `FOREIGN KEY (jenis_id) REFERENCES ntf_jenis(id)`
- `ntf_mesej_templat_id_foreign` (f): `FOREIGN KEY (templat_id) REFERENCES ntf_templat(id) ON DELETE SET NULL`
- `ntf_mesej_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `ntf_mesej_keutamaan_created_at_index`: `CREATE INDEX ntf_mesej_keutamaan_created_at_index ON public.ntf_mesej USING btree (keutamaan, created_at)`
- `ntf_mesej_kunci_dedupe_index`: `CREATE INDEX ntf_mesej_kunci_dedupe_index ON public.ntf_mesej USING btree (kunci_dedupe)`
- `ntf_mesej_pkey`: `CREATE UNIQUE INDEX ntf_mesej_pkey ON public.ntf_mesej USING btree (id)`

### `ntf_penerima_peraturan`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('ntf_penerima_peraturan_id_seq'::regclass) | TIDAK | PK |
| `jenis_id` | bigint | 64 |  | TIDAK | FK, UQ |
| `sasaran` | character varying | 20 |  | TIDAK | UQ |
| `nilai` | character varying | 255 |  | TIDAK | UQ |
| `aktif` | boolean |  | true | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `ntf_penerima_peraturan_jenis_id_foreign` (f): `FOREIGN KEY (jenis_id) REFERENCES ntf_jenis(id) ON DELETE CASCADE`
- `ntf_penerima_peraturan_pkey` (p): `PRIMARY KEY (id)`
- `ntf_penerima_peraturan_jenis_id_sasaran_nilai_unique` (u): `UNIQUE (jenis_id, sasaran, nilai)`

**Indeks**

- `ntf_penerima_peraturan_jenis_id_sasaran_nilai_unique`: `CREATE UNIQUE INDEX ntf_penerima_peraturan_jenis_id_sasaran_nilai_unique ON public.ntf_penerima_peraturan USING btree (jenis_id, sasaran, nilai)`
- `ntf_penerima_peraturan_pkey`: `CREATE UNIQUE INDEX ntf_penerima_peraturan_pkey ON public.ntf_penerima_peraturan USING btree (id)`

### `ntf_penghantaran`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('ntf_penghantaran_id_seq'::regclass) | TIDAK | PK |
| `mesej_id` | bigint | 64 |  | TIDAK | FK |
| `penerima_id` | bigint | 64 |  | YA | FK |
| `saluran` | character varying | 20 |  | TIDAK |  |
| `status` | character varying | 20 |  | TIDAK |  |
| `bilangan_cubaan` | smallint | 16 | '0'::smallint | TIDAK |  |
| `kod_ralat` | character varying | 60 |  | YA |  |
| `mesej_ralat` | text |  |  | YA |  |
| `ringkasan` | boolean |  | false | TIDAK |  |
| `tarikh_beratur` | timestamp without time zone |  |  | YA |  |
| `tarikh_hantar` | timestamp without time zone |  |  | YA |  |
| `tarikh_terima` | timestamp without time zone |  |  | YA |  |
| `tarikh_baca` | timestamp without time zone |  |  | YA |  |
| `tarikh_gagal` | timestamp without time zone |  |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `ntf_penghantaran_mesej_id_foreign` (f): `FOREIGN KEY (mesej_id) REFERENCES ntf_mesej(id)`
- `ntf_penghantaran_penerima_id_foreign` (f): `FOREIGN KEY (penerima_id) REFERENCES users(id) ON DELETE SET NULL`
- `ntf_penghantaran_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `ntf_penghantaran_penerima_id_saluran_index`: `CREATE INDEX ntf_penghantaran_penerima_id_saluran_index ON public.ntf_penghantaran USING btree (penerima_id, saluran)`
- `ntf_penghantaran_pkey`: `CREATE UNIQUE INDEX ntf_penghantaran_pkey ON public.ntf_penghantaran USING btree (id)`
- `ntf_penghantaran_saluran_created_at_index`: `CREATE INDEX ntf_penghantaran_saluran_created_at_index ON public.ntf_penghantaran USING btree (saluran, created_at)`
- `ntf_penghantaran_status_index`: `CREATE INDEX ntf_penghantaran_status_index ON public.ntf_penghantaran USING btree (status)`

### `ntf_status_pengguna`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('ntf_status_pengguna_id_seq'::regclass) | TIDAK | PK |
| `mesej_id` | bigint | 64 |  | TIDAK | FK, UQ |
| `pengguna_id` | bigint | 64 |  | TIDAK | FK, UQ |
| `dibaca_pada` | timestamp without time zone |  |  | YA |  |
| `diarkib_pada` | timestamp without time zone |  |  | YA |  |
| `dipadam_pada` | timestamp without time zone |  |  | YA |  |
| `dieskalasi_pada` | timestamp without time zone |  |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `ntf_status_pengguna_mesej_id_foreign` (f): `FOREIGN KEY (mesej_id) REFERENCES ntf_mesej(id) ON DELETE CASCADE`
- `ntf_status_pengguna_pengguna_id_foreign` (f): `FOREIGN KEY (pengguna_id) REFERENCES users(id) ON DELETE CASCADE`
- `ntf_status_pengguna_pkey` (p): `PRIMARY KEY (id)`
- `ntf_status_pengguna_mesej_id_pengguna_id_unique` (u): `UNIQUE (mesej_id, pengguna_id)`

**Indeks**

- `ntf_status_pengguna_mesej_id_pengguna_id_unique`: `CREATE UNIQUE INDEX ntf_status_pengguna_mesej_id_pengguna_id_unique ON public.ntf_status_pengguna USING btree (mesej_id, pengguna_id)`
- `ntf_status_pengguna_pengguna_id_dipadam_pada_dibaca_pada_index`: `CREATE INDEX ntf_status_pengguna_pengguna_id_dipadam_pada_dibaca_pada_index ON public.ntf_status_pengguna USING btree (pengguna_id, dipadam_pada, dibaca_pada)`
- `ntf_status_pengguna_pkey`: `CREATE UNIQUE INDEX ntf_status_pengguna_pkey ON public.ntf_status_pengguna USING btree (id)`

### `ntf_templat`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('ntf_templat_id_seq'::regclass) | TIDAK | PK |
| `jenis_id` | bigint | 64 |  | TIDAK | FK, UQ |
| `versi` | integer | 32 |  | TIDAK | UQ |
| `subjek` | character varying | 255 |  | TIDAK |  |
| `kandungan` | text |  |  | TIDAK |  |
| `aktif` | boolean |  | false | TIDAK |  |
| `catatan` | character varying | 255 |  | YA |  |
| `dicipta_oleh` | bigint | 64 |  | YA | FK |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `ntf_templat_dicipta_oleh_foreign` (f): `FOREIGN KEY (dicipta_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `ntf_templat_jenis_id_foreign` (f): `FOREIGN KEY (jenis_id) REFERENCES ntf_jenis(id) ON DELETE CASCADE`
- `ntf_templat_pkey` (p): `PRIMARY KEY (id)`
- `ntf_templat_jenis_id_versi_unique` (u): `UNIQUE (jenis_id, versi)`

**Indeks**

- `ntf_templat_jenis_id_versi_unique`: `CREATE UNIQUE INDEX ntf_templat_jenis_id_versi_unique ON public.ntf_templat USING btree (jenis_id, versi)`
- `ntf_templat_pkey`: `CREATE UNIQUE INDEX ntf_templat_pkey ON public.ntf_templat USING btree (id)`

### `password_reset_tokens`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `email` | character varying | 255 |  | TIDAK | PK |
| `token` | character varying | 255 |  | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `password_reset_tokens_pkey` (p): `PRIMARY KEY (email)`

**Indeks**

- `password_reset_tokens_pkey`: `CREATE UNIQUE INDEX password_reset_tokens_pkey ON public.password_reset_tokens USING btree (email)`

### `pemetaan_import`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('pemetaan_import_id_seq'::regclass) | TIDAK | PK |
| `entiti_sasaran` | character varying | 64 |  | TIDAK | UQ |
| `nama` | character varying | 100 |  | TIDAK | UQ |
| `pemetaan` | jsonb |  |  | TIDAK |  |
| `id_pencipta` | bigint | 64 |  | YA | FK |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `pemetaan_import_id_pencipta_foreign` (f): `FOREIGN KEY (id_pencipta) REFERENCES users(id) ON DELETE SET NULL`
- `pemetaan_import_pkey` (p): `PRIMARY KEY (id)`
- `pemetaan_import_entiti_sasaran_nama_unique` (u): `UNIQUE (entiti_sasaran, nama)`

**Indeks**

- `pemetaan_import_entiti_sasaran_nama_unique`: `CREATE UNIQUE INDEX pemetaan_import_entiti_sasaran_nama_unique ON public.pemetaan_import USING btree (entiti_sasaran, nama)`
- `pemetaan_import_pkey`: `CREATE UNIQUE INDEX pemetaan_import_pkey ON public.pemetaan_import USING btree (id)`

### `pemetaan_tuntutan`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('pemetaan_tuntutan_id_seq'::regclass) | TIDAK | PK |
| `jenis_tuntutan` | character varying | 20 |  | TIDAK |  |
| `nilai` | character varying | 150 |  | TIDAK |  |
| `peranan_id` | bigint | 64 |  | YA | FK |
| `skop_jenis` | character varying | 30 |  | YA |  |
| `skop_nilai` | character varying | 100 |  | YA |  |
| `aktif` | boolean |  | true | TIDAK |  |
| `keutamaan` | integer | 32 | 100 | TIDAK |  |
| `keterangan` | character varying | 255 |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `pemetaan_tuntutan_peranan_id_foreign` (f): `FOREIGN KEY (peranan_id) REFERENCES peranan(id) ON DELETE CASCADE`
- `pemetaan_tuntutan_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `pemetaan_tuntutan_pkey`: `CREATE UNIQUE INDEX pemetaan_tuntutan_pkey ON public.pemetaan_tuntutan USING btree (id)`

### `pengguna_peranan`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('pengguna_peranan_id_seq'::regclass) | TIDAK | PK |
| `user_id` | bigint | 64 |  | TIDAK | FK, UQ |
| `peranan_id` | bigint | 64 |  | TIDAK | FK, UQ |
| `sumber` | character varying | 10 | 'manual'::character varying | TIDAK |  |
| `diberi_oleh` | bigint | 64 |  | YA | FK |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `pengguna_peranan_diberi_oleh_foreign` (f): `FOREIGN KEY (diberi_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `pengguna_peranan_peranan_id_foreign` (f): `FOREIGN KEY (peranan_id) REFERENCES peranan(id) ON DELETE CASCADE`
- `pengguna_peranan_user_id_foreign` (f): `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE`
- `pengguna_peranan_pkey` (p): `PRIMARY KEY (id)`
- `pengguna_peranan_user_id_peranan_id_unique` (u): `UNIQUE (user_id, peranan_id)`

**Indeks**

- `pengguna_peranan_pkey`: `CREATE UNIQUE INDEX pengguna_peranan_pkey ON public.pengguna_peranan USING btree (id)`
- `pengguna_peranan_user_id_peranan_id_unique`: `CREATE UNIQUE INDEX pengguna_peranan_user_id_peranan_id_unique ON public.pengguna_peranan USING btree (user_id, peranan_id)`

### `pengguna_skop`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('pengguna_skop_id_seq'::regclass) | TIDAK | PK |
| `user_id` | bigint | 64 |  | TIDAK | FK, UQ |
| `jenis_skop` | character varying | 30 |  | TIDAK | UQ |
| `nilai_skop` | character varying | 100 |  | TIDAK | UQ |
| `sumber` | character varying | 10 | 'manual'::character varying | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `pengguna_skop_user_id_foreign` (f): `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE`
- `pengguna_skop_pkey` (p): `PRIMARY KEY (id)`
- `pengguna_skop_user_id_jenis_skop_nilai_skop_unique` (u): `UNIQUE (user_id, jenis_skop, nilai_skop)`

**Indeks**

- `pengguna_skop_pkey`: `CREATE UNIQUE INDEX pengguna_skop_pkey ON public.pengguna_skop USING btree (id)`
- `pengguna_skop_user_id_jenis_skop_nilai_skop_unique`: `CREATE UNIQUE INDEX pengguna_skop_user_id_jenis_skop_nilai_skop_unique ON public.pengguna_skop USING btree (user_id, jenis_skop, nilai_skop)`

### `penilaian`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | uuid |  |  | TIDAK | PK |
| `lot_id` | uuid |  |  | TIDAK | FK |
| `tarikh_penilaian` | date |  |  | TIDAK |  |
| `nilai_pasaran` | numeric | 16,2 |  | YA |  |
| `nilai_setara` | numeric | 16,2 |  | YA |  |
| `kaedah` | character varying | 50 |  | YA |  |
| `penilai` | character varying | 150 |  | YA |  |
| `versi` | integer | 32 | 1 | TIDAK |  |
| `klasifikasi` | character varying | 10 | 'rahsia'::character varying | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |
| `deleted_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `penilaian_lot_id_foreign` (f): `FOREIGN KEY (lot_id) REFERENCES lot_tanah(id)`
- `penilaian_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `penilaian_lot_id_index`: `CREATE INDEX penilaian_lot_id_index ON public.penilaian USING btree (lot_id)`
- `penilaian_pkey`: `CREATE UNIQUE INDEX penilaian_pkey ON public.penilaian USING btree (id)`

### `peranan`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('peranan_id_seq'::regclass) | TIDAK | PK |
| `kod` | character varying | 60 |  | TIDAK | UQ |
| `nama` | character varying | 255 |  | TIDAK |  |
| `kategori_rfp` | character varying | 60 |  | YA |  |
| `keterangan` | text |  |  | YA |  |
| `status` | character varying | 20 | 'aktif'::character varying | TIDAK |  |
| `boleh_padam` | boolean |  | true | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `peranan_pkey` (p): `PRIMARY KEY (id)`
- `peranan_kod_unique` (u): `UNIQUE (kod)`

**Indeks**

- `peranan_kod_unique`: `CREATE UNIQUE INDEX peranan_kod_unique ON public.peranan USING btree (kod)`
- `peranan_pkey`: `CREATE UNIQUE INDEX peranan_pkey ON public.peranan USING btree (id)`

### `peranan_kebenaran`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('peranan_kebenaran_id_seq'::regclass) | TIDAK | PK |
| `peranan_id` | bigint | 64 |  | TIDAK | FK, UQ |
| `kebenaran` | character varying | 150 |  | TIDAK | UQ |
| `modul` | character varying | 30 |  | TIDAK |  |
| `fungsi` | character varying | 60 |  | TIDAK |  |
| `tindakan` | character varying | 30 |  | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `peranan_kebenaran_peranan_id_foreign` (f): `FOREIGN KEY (peranan_id) REFERENCES peranan(id) ON DELETE CASCADE`
- `peranan_kebenaran_pkey` (p): `PRIMARY KEY (id)`
- `peranan_kebenaran_peranan_id_kebenaran_unique` (u): `UNIQUE (peranan_id, kebenaran)`

**Indeks**

- `peranan_kebenaran_kebenaran_index`: `CREATE INDEX peranan_kebenaran_kebenaran_index ON public.peranan_kebenaran USING btree (kebenaran)`
- `peranan_kebenaran_peranan_id_kebenaran_unique`: `CREATE UNIQUE INDEX peranan_kebenaran_peranan_id_kebenaran_unique ON public.peranan_kebenaran USING btree (peranan_id, kebenaran)`
- `peranan_kebenaran_pkey`: `CREATE UNIQUE INDEX peranan_kebenaran_pkey ON public.peranan_kebenaran USING btree (id)`

### `peranan_skop`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('peranan_skop_id_seq'::regclass) | TIDAK | PK |
| `peranan_id` | bigint | 64 |  | TIDAK | FK, UQ |
| `jenis_skop` | character varying | 30 |  | TIDAK | UQ |
| `nilai_skop` | character varying | 100 |  | TIDAK | UQ |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `peranan_skop_peranan_id_foreign` (f): `FOREIGN KEY (peranan_id) REFERENCES peranan(id) ON DELETE CASCADE`
- `peranan_skop_pkey` (p): `PRIMARY KEY (id)`
- `peranan_skop_peranan_id_jenis_skop_nilai_skop_unique` (u): `UNIQUE (peranan_id, jenis_skop, nilai_skop)`

**Indeks**

- `peranan_skop_peranan_id_jenis_skop_nilai_skop_unique`: `CREATE UNIQUE INDEX peranan_skop_peranan_id_jenis_skop_nilai_skop_unique ON public.peranan_skop USING btree (peranan_id, jenis_skop, nilai_skop)`
- `peranan_skop_pkey`: `CREATE UNIQUE INDEX peranan_skop_pkey ON public.peranan_skop USING btree (id)`

### `peraturan_kualiti_data`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('peraturan_kualiti_data_id_seq'::regclass) | TIDAK | PK |
| `entiti` | character varying | 64 |  | TIDAK |  |
| `medan` | character varying | 64 |  | TIDAK |  |
| `jenis_peraturan` | character varying | 20 |  | TIDAK |  |
| `ungkapan_peraturan` | jsonb |  | '{}'::jsonb | TIDAK |  |
| `mesej_ralat` | character varying | 255 |  | TIDAK |  |
| `tahap_keterukan` | character varying | 10 | 'ralat'::character varying | TIDAK |  |
| `status_aktif` | boolean |  | true | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `peraturan_kualiti_data_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `peraturan_kualiti_data_entiti_status_aktif_index`: `CREATE INDEX peraturan_kualiti_data_entiti_status_aktif_index ON public.peraturan_kualiti_data USING btree (entiti, status_aktif)`
- `peraturan_kualiti_data_pkey`: `CREATE UNIQUE INDEX peraturan_kualiti_data_pkey ON public.peraturan_kualiti_data USING btree (id)`

### `personal_access_tokens`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('personal_access_tokens_id_seq'::regclass) | TIDAK | PK |
| `tokenable_type` | character varying | 255 |  | TIDAK |  |
| `tokenable_id` | bigint | 64 |  | TIDAK |  |
| `name` | text |  |  | TIDAK |  |
| `token` | character varying | 64 |  | TIDAK | UQ |
| `abilities` | text |  |  | YA |  |
| `last_used_at` | timestamp without time zone |  |  | YA |  |
| `expires_at` | timestamp without time zone |  |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `personal_access_tokens_pkey` (p): `PRIMARY KEY (id)`
- `personal_access_tokens_token_unique` (u): `UNIQUE (token)`

**Indeks**

- `personal_access_tokens_expires_at_index`: `CREATE INDEX personal_access_tokens_expires_at_index ON public.personal_access_tokens USING btree (expires_at)`
- `personal_access_tokens_pkey`: `CREATE UNIQUE INDEX personal_access_tokens_pkey ON public.personal_access_tokens USING btree (id)`
- `personal_access_tokens_token_unique`: `CREATE UNIQUE INDEX personal_access_tokens_token_unique ON public.personal_access_tokens USING btree (token)`
- `personal_access_tokens_tokenable_type_tokenable_id_index`: `CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id)`

### `polisi_keselamatan`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `kunci` | character varying | 60 |  | TIDAK | PK |
| `nilai` | json |  |  | TIDAK |  |
| `dikemas_kini_oleh` | bigint | 64 |  | YA | FK |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `polisi_keselamatan_dikemas_kini_oleh_foreign` (f): `FOREIGN KEY (dikemas_kini_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `polisi_keselamatan_pkey` (p): `PRIMARY KEY (kunci)`

**Indeks**

- `polisi_keselamatan_pkey`: `CREATE UNIQUE INDEX polisi_keselamatan_pkey ON public.polisi_keselamatan USING btree (kunci)`

### `ralat_import`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('ralat_import_id_seq'::regclass) | TIDAK | PK |
| `id_sesi` | uuid |  |  | TIDAK | FK |
| `no_baris` | integer | 32 |  | TIDAK |  |
| `medan` | character varying | 64 |  | YA |  |
| `nilai_diberi` | text |  |  | YA |  |
| `kod_ralat` | character varying | 50 |  | TIDAK |  |
| `sebab_penolakan` | character varying | 500 |  | TIDAK |  |

**Kekangan**

- `ralat_import_id_sesi_foreign` (f): `FOREIGN KEY (id_sesi) REFERENCES sesi_import(id) ON DELETE CASCADE`
- `ralat_import_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `ralat_import_id_sesi_no_baris_index`: `CREATE INDEX ralat_import_id_sesi_no_baris_index ON public.ralat_import USING btree (id_sesi, no_baris)`
- `ralat_import_pkey`: `CREATE UNIQUE INDEX ralat_import_pkey ON public.ralat_import USING btree (id)`

### `recert_keputusan`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('recert_keputusan_id_seq'::regclass) | TIDAK | PK |
| `kitaran_id` | bigint | 64 |  | TIDAK | FK, UQ |
| `user_id` | bigint | 64 |  | TIDAK | FK, UQ |
| `peranan_id` | bigint | 64 |  | TIDAK | FK, UQ |
| `keputusan` | character varying | 20 | 'belum'::character varying | TIDAK |  |
| `catatan` | character varying | 255 |  | YA |  |
| `disemak_oleh` | bigint | 64 |  | YA | FK |
| `disemak_pada` | timestamp without time zone |  |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `recert_keputusan_disemak_oleh_foreign` (f): `FOREIGN KEY (disemak_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `recert_keputusan_kitaran_id_foreign` (f): `FOREIGN KEY (kitaran_id) REFERENCES recert_kitaran(id) ON DELETE CASCADE`
- `recert_keputusan_peranan_id_foreign` (f): `FOREIGN KEY (peranan_id) REFERENCES peranan(id) ON DELETE CASCADE`
- `recert_keputusan_user_id_foreign` (f): `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE`
- `recert_keputusan_pkey` (p): `PRIMARY KEY (id)`
- `recert_keputusan_kitaran_id_user_id_peranan_id_unique` (u): `UNIQUE (kitaran_id, user_id, peranan_id)`

**Indeks**

- `recert_keputusan_kitaran_id_user_id_peranan_id_unique`: `CREATE UNIQUE INDEX recert_keputusan_kitaran_id_user_id_peranan_id_unique ON public.recert_keputusan USING btree (kitaran_id, user_id, peranan_id)`
- `recert_keputusan_pkey`: `CREATE UNIQUE INDEX recert_keputusan_pkey ON public.recert_keputusan USING btree (id)`

### `recert_kitaran`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('recert_kitaran_id_seq'::regclass) | TIDAK | PK |
| `nama` | character varying | 255 |  | TIDAK |  |
| `bahagian` | character varying | 100 |  | YA |  |
| `tarikh_mula` | date |  |  | TIDAK |  |
| `tarikh_akhir` | date |  |  | TIDAK |  |
| `status` | character varying | 20 | 'dibuka'::character varying | TIDAK |  |
| `dicipta_oleh` | bigint | 64 |  | YA | FK |
| `ditutup_pada` | timestamp without time zone |  |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `recert_kitaran_dicipta_oleh_foreign` (f): `FOREIGN KEY (dicipta_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `recert_kitaran_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `recert_kitaran_pkey`: `CREATE UNIQUE INDEX recert_kitaran_pkey ON public.recert_kitaran USING btree (id)`

### `ref_daerah`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('ref_daerah_id_seq'::regclass) | TIDAK | PK |
| `kod` | character varying | 20 |  | TIDAK | UQ |
| `nama` | character varying | 150 |  | TIDAK |  |
| `keterangan` | text |  |  | YA |  |
| `susunan` | smallint | 16 | '0'::smallint | TIDAK |  |
| `aktif` | boolean |  | true | TIDAK |  |
| `versi` | integer | 32 | 1 | TIDAK | UQ |
| `sah_dari` | date |  |  | TIDAK |  |
| `sah_hingga` | date |  |  | YA |  |
| `sumber` | character varying | 255 |  | YA |  |
| `sumber_url` | character varying | 500 |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |
| `negeri_kod` | character varying | 20 |  | TIDAK |  |
| `kod_pendek` | character varying | 4 |  | TIDAK |  |
| `kod_disahkan` | boolean |  | false | TIDAK |  |
| `nama_lain` | json |  |  | YA |  |
| `wikidata` | character varying | 20 |  | YA |  |
| `osm_relation_id` | bigint | 64 |  | YA |  |

**Kekangan**

- `ref_daerah_tarikh_sah_check` (c): `CHECK (((sah_hingga IS NULL) OR (sah_hingga >= sah_dari)))`
- `ref_daerah_pkey` (p): `PRIMARY KEY (id)`
- `ref_daerah_kod_versi_unique` (u): `UNIQUE (kod, versi)`

**Indeks**

- `ref_daerah_kod_sah_dari_index`: `CREATE INDEX ref_daerah_kod_sah_dari_index ON public.ref_daerah USING btree (kod, sah_dari)`
- `ref_daerah_kod_semasa_unique`: `CREATE UNIQUE INDEX ref_daerah_kod_semasa_unique ON public.ref_daerah USING btree (kod) WHERE (sah_hingga IS NULL)`
- `ref_daerah_kod_versi_unique`: `CREATE UNIQUE INDEX ref_daerah_kod_versi_unique ON public.ref_daerah USING btree (kod, versi)`
- `ref_daerah_nama_semasa_unique`: `CREATE UNIQUE INDEX ref_daerah_nama_semasa_unique ON public.ref_daerah USING btree (negeri_kod, lower((nama)::text)) WHERE (sah_hingga IS NULL)`
- `ref_daerah_negeri_kod_index`: `CREATE INDEX ref_daerah_negeri_kod_index ON public.ref_daerah USING btree (negeri_kod)`
- `ref_daerah_pkey`: `CREATE UNIQUE INDEX ref_daerah_pkey ON public.ref_daerah USING btree (id)`

### `ref_jenis_dokumen`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('ref_jenis_dokumen_id_seq'::regclass) | TIDAK | PK |
| `kod` | character varying | 20 |  | TIDAK | UQ |
| `nama` | character varying | 150 |  | TIDAK |  |
| `keterangan` | text |  |  | YA |  |
| `susunan` | smallint | 16 | '0'::smallint | TIDAK |  |
| `aktif` | boolean |  | true | TIDAK |  |
| `versi` | integer | 32 | 1 | TIDAK | UQ |
| `sah_dari` | date |  |  | TIDAK |  |
| `sah_hingga` | date |  |  | YA |  |
| `sumber` | character varying | 255 |  | YA |  |
| `sumber_url` | character varying | 500 |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |
| `klasifikasi_lalai` | character varying | 20 |  | YA |  |

**Kekangan**

- `ref_jenis_dokumen_tarikh_sah_check` (c): `CHECK (((sah_hingga IS NULL) OR (sah_hingga >= sah_dari)))`
- `ref_jenis_dokumen_pkey` (p): `PRIMARY KEY (id)`
- `ref_jenis_dokumen_kod_versi_unique` (u): `UNIQUE (kod, versi)`

**Indeks**

- `ref_jenis_dokumen_kod_sah_dari_index`: `CREATE INDEX ref_jenis_dokumen_kod_sah_dari_index ON public.ref_jenis_dokumen USING btree (kod, sah_dari)`
- `ref_jenis_dokumen_kod_semasa_unique`: `CREATE UNIQUE INDEX ref_jenis_dokumen_kod_semasa_unique ON public.ref_jenis_dokumen USING btree (kod) WHERE (sah_hingga IS NULL)`
- `ref_jenis_dokumen_kod_versi_unique`: `CREATE UNIQUE INDEX ref_jenis_dokumen_kod_versi_unique ON public.ref_jenis_dokumen USING btree (kod, versi)`
- `ref_jenis_dokumen_nama_semasa_unique`: `CREATE UNIQUE INDEX ref_jenis_dokumen_nama_semasa_unique ON public.ref_jenis_dokumen USING btree (lower((nama)::text)) WHERE (sah_hingga IS NULL)`
- `ref_jenis_dokumen_pkey`: `CREATE UNIQUE INDEX ref_jenis_dokumen_pkey ON public.ref_jenis_dokumen USING btree (id)`

### `ref_jenis_hak_milik`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('ref_jenis_hak_milik_id_seq'::regclass) | TIDAK | PK |
| `kod` | character varying | 20 |  | TIDAK | UQ |
| `nama` | character varying | 150 |  | TIDAK |  |
| `keterangan` | text |  |  | YA |  |
| `susunan` | smallint | 16 | '0'::smallint | TIDAK |  |
| `aktif` | boolean |  | true | TIDAK |  |
| `versi` | integer | 32 | 1 | TIDAK | UQ |
| `sah_dari` | date |  |  | TIDAK |  |
| `sah_hingga` | date |  |  | YA |  |
| `sumber` | character varying | 255 |  | YA |  |
| `sumber_url` | character varying | 500 |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |
| `singkatan` | character varying | 10 |  | TIDAK |  |
| `peringkat` | character varying | 20 |  | TIDAK |  |
| `sementara` | boolean |  | false | TIDAK |  |
| `hak_milik` | boolean |  | true | TIDAK |  |
| `rujukan_undang` | character varying | 100 |  | YA |  |

**Kekangan**

- `ref_jenis_hak_milik_tarikh_sah_check` (c): `CHECK (((sah_hingga IS NULL) OR (sah_hingga >= sah_dari)))`
- `ref_jenis_hak_milik_pkey` (p): `PRIMARY KEY (id)`
- `ref_jenis_hak_milik_kod_versi_unique` (u): `UNIQUE (kod, versi)`

**Indeks**

- `ref_jenis_hak_milik_kod_sah_dari_index`: `CREATE INDEX ref_jenis_hak_milik_kod_sah_dari_index ON public.ref_jenis_hak_milik USING btree (kod, sah_dari)`
- `ref_jenis_hak_milik_kod_semasa_unique`: `CREATE UNIQUE INDEX ref_jenis_hak_milik_kod_semasa_unique ON public.ref_jenis_hak_milik USING btree (kod) WHERE (sah_hingga IS NULL)`
- `ref_jenis_hak_milik_kod_versi_unique`: `CREATE UNIQUE INDEX ref_jenis_hak_milik_kod_versi_unique ON public.ref_jenis_hak_milik USING btree (kod, versi)`
- `ref_jenis_hak_milik_nama_semasa_unique`: `CREATE UNIQUE INDEX ref_jenis_hak_milik_nama_semasa_unique ON public.ref_jenis_hak_milik USING btree (lower((nama)::text)) WHERE (sah_hingga IS NULL)`
- `ref_jenis_hak_milik_pkey`: `CREATE UNIQUE INDEX ref_jenis_hak_milik_pkey ON public.ref_jenis_hak_milik USING btree (id)`

### `ref_kategori_aduan`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('ref_kategori_aduan_id_seq'::regclass) | TIDAK | PK |
| `kod` | character varying | 20 |  | TIDAK | UQ |
| `nama` | character varying | 150 |  | TIDAK |  |
| `keterangan` | text |  |  | YA |  |
| `susunan` | smallint | 16 | '0'::smallint | TIDAK |  |
| `aktif` | boolean |  | true | TIDAK |  |
| `versi` | integer | 32 | 1 | TIDAK | UQ |
| `sah_dari` | date |  |  | TIDAK |  |
| `sah_hingga` | date |  |  | YA |  |
| `sumber` | character varying | 255 |  | YA |  |
| `sumber_url` | character varying | 500 |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `ref_kategori_aduan_tarikh_sah_check` (c): `CHECK (((sah_hingga IS NULL) OR (sah_hingga >= sah_dari)))`
- `ref_kategori_aduan_pkey` (p): `PRIMARY KEY (id)`
- `ref_kategori_aduan_kod_versi_unique` (u): `UNIQUE (kod, versi)`

**Indeks**

- `ref_kategori_aduan_kod_sah_dari_index`: `CREATE INDEX ref_kategori_aduan_kod_sah_dari_index ON public.ref_kategori_aduan USING btree (kod, sah_dari)`
- `ref_kategori_aduan_kod_semasa_unique`: `CREATE UNIQUE INDEX ref_kategori_aduan_kod_semasa_unique ON public.ref_kategori_aduan USING btree (kod) WHERE (sah_hingga IS NULL)`
- `ref_kategori_aduan_kod_versi_unique`: `CREATE UNIQUE INDEX ref_kategori_aduan_kod_versi_unique ON public.ref_kategori_aduan USING btree (kod, versi)`
- `ref_kategori_aduan_nama_semasa_unique`: `CREATE UNIQUE INDEX ref_kategori_aduan_nama_semasa_unique ON public.ref_kategori_aduan USING btree (lower((nama)::text)) WHERE (sah_hingga IS NULL)`
- `ref_kategori_aduan_pkey`: `CREATE UNIQUE INDEX ref_kategori_aduan_pkey ON public.ref_kategori_aduan USING btree (id)`

### `ref_kategori_guna_tanah`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('ref_kategori_guna_tanah_id_seq'::regclass) | TIDAK | PK |
| `kod` | character varying | 20 |  | TIDAK | UQ |
| `nama` | character varying | 150 |  | TIDAK |  |
| `keterangan` | text |  |  | YA |  |
| `susunan` | smallint | 16 | '0'::smallint | TIDAK |  |
| `aktif` | boolean |  | true | TIDAK |  |
| `versi` | integer | 32 | 1 | TIDAK | UQ |
| `sah_dari` | date |  |  | TIDAK |  |
| `sah_hingga` | date |  |  | YA |  |
| `sumber` | character varying | 255 |  | YA |  |
| `sumber_url` | character varying | 500 |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |
| `rujukan_undang` | character varying | 100 |  | YA |  |

**Kekangan**

- `ref_kategori_guna_tanah_tarikh_sah_check` (c): `CHECK (((sah_hingga IS NULL) OR (sah_hingga >= sah_dari)))`
- `ref_kategori_guna_tanah_pkey` (p): `PRIMARY KEY (id)`
- `ref_kategori_guna_tanah_kod_versi_unique` (u): `UNIQUE (kod, versi)`

**Indeks**

- `ref_kategori_guna_tanah_kod_sah_dari_index`: `CREATE INDEX ref_kategori_guna_tanah_kod_sah_dari_index ON public.ref_kategori_guna_tanah USING btree (kod, sah_dari)`
- `ref_kategori_guna_tanah_kod_semasa_unique`: `CREATE UNIQUE INDEX ref_kategori_guna_tanah_kod_semasa_unique ON public.ref_kategori_guna_tanah USING btree (kod) WHERE (sah_hingga IS NULL)`
- `ref_kategori_guna_tanah_kod_versi_unique`: `CREATE UNIQUE INDEX ref_kategori_guna_tanah_kod_versi_unique ON public.ref_kategori_guna_tanah USING btree (kod, versi)`
- `ref_kategori_guna_tanah_nama_semasa_unique`: `CREATE UNIQUE INDEX ref_kategori_guna_tanah_nama_semasa_unique ON public.ref_kategori_guna_tanah USING btree (lower((nama)::text)) WHERE (sah_hingga IS NULL)`
- `ref_kategori_guna_tanah_pkey`: `CREATE UNIQUE INDEX ref_kategori_guna_tanah_pkey ON public.ref_kategori_guna_tanah USING btree (id)`

### `ref_klasifikasi_data`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('ref_klasifikasi_data_id_seq'::regclass) | TIDAK | PK |
| `kod` | character varying | 20 |  | TIDAK | UQ |
| `nama` | character varying | 150 |  | TIDAK |  |
| `keterangan` | text |  |  | YA |  |
| `susunan` | smallint | 16 | '0'::smallint | TIDAK |  |
| `aktif` | boolean |  | true | TIDAK |  |
| `versi` | integer | 32 | 1 | TIDAK | UQ |
| `sah_dari` | date |  |  | TIDAK |  |
| `sah_hingga` | date |  |  | YA |  |
| `sumber` | character varying | 255 |  | YA |  |
| `sumber_url` | character varying | 500 |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |
| `aras` | smallint | 16 |  | TIDAK |  |

**Kekangan**

- `ref_klasifikasi_data_tarikh_sah_check` (c): `CHECK (((sah_hingga IS NULL) OR (sah_hingga >= sah_dari)))`
- `ref_klasifikasi_data_pkey` (p): `PRIMARY KEY (id)`
- `ref_klasifikasi_data_kod_versi_unique` (u): `UNIQUE (kod, versi)`

**Indeks**

- `ref_klasifikasi_data_kod_sah_dari_index`: `CREATE INDEX ref_klasifikasi_data_kod_sah_dari_index ON public.ref_klasifikasi_data USING btree (kod, sah_dari)`
- `ref_klasifikasi_data_kod_semasa_unique`: `CREATE UNIQUE INDEX ref_klasifikasi_data_kod_semasa_unique ON public.ref_klasifikasi_data USING btree (kod) WHERE (sah_hingga IS NULL)`
- `ref_klasifikasi_data_kod_versi_unique`: `CREATE UNIQUE INDEX ref_klasifikasi_data_kod_versi_unique ON public.ref_klasifikasi_data USING btree (kod, versi)`
- `ref_klasifikasi_data_nama_semasa_unique`: `CREATE UNIQUE INDEX ref_klasifikasi_data_nama_semasa_unique ON public.ref_klasifikasi_data USING btree (lower((nama)::text)) WHERE (sah_hingga IS NULL)`
- `ref_klasifikasi_data_pkey`: `CREATE UNIQUE INDEX ref_klasifikasi_data_pkey ON public.ref_klasifikasi_data USING btree (id)`

### `ref_mukim`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('ref_mukim_id_seq'::regclass) | TIDAK | PK |
| `kod` | character varying | 20 |  | TIDAK | UQ |
| `nama` | character varying | 150 |  | TIDAK |  |
| `keterangan` | text |  |  | YA |  |
| `susunan` | smallint | 16 | '0'::smallint | TIDAK |  |
| `aktif` | boolean |  | true | TIDAK |  |
| `versi` | integer | 32 | 1 | TIDAK | UQ |
| `sah_dari` | date |  |  | TIDAK |  |
| `sah_hingga` | date |  |  | YA |  |
| `sumber` | character varying | 255 |  | YA |  |
| `sumber_url` | character varying | 500 |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |
| `daerah_kod` | character varying | 20 |  | TIDAK |  |
| `kod_pendek` | character varying | 4 |  | TIDAK |  |
| `kod_disahkan` | boolean |  | false | TIDAK |  |
| `nama_lain` | json |  |  | YA |  |

**Kekangan**

- `ref_mukim_tarikh_sah_check` (c): `CHECK (((sah_hingga IS NULL) OR (sah_hingga >= sah_dari)))`
- `ref_mukim_pkey` (p): `PRIMARY KEY (id)`
- `ref_mukim_kod_versi_unique` (u): `UNIQUE (kod, versi)`

**Indeks**

- `ref_mukim_daerah_kod_index`: `CREATE INDEX ref_mukim_daerah_kod_index ON public.ref_mukim USING btree (daerah_kod)`
- `ref_mukim_kod_sah_dari_index`: `CREATE INDEX ref_mukim_kod_sah_dari_index ON public.ref_mukim USING btree (kod, sah_dari)`
- `ref_mukim_kod_semasa_unique`: `CREATE UNIQUE INDEX ref_mukim_kod_semasa_unique ON public.ref_mukim USING btree (kod) WHERE (sah_hingga IS NULL)`
- `ref_mukim_kod_versi_unique`: `CREATE UNIQUE INDEX ref_mukim_kod_versi_unique ON public.ref_mukim USING btree (kod, versi)`
- `ref_mukim_nama_semasa_unique`: `CREATE UNIQUE INDEX ref_mukim_nama_semasa_unique ON public.ref_mukim USING btree (daerah_kod, lower((nama)::text)) WHERE (sah_hingga IS NULL)`
- `ref_mukim_pkey`: `CREATE UNIQUE INDEX ref_mukim_pkey ON public.ref_mukim USING btree (id)`

### `ref_negeri`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('ref_negeri_id_seq'::regclass) | TIDAK | PK |
| `kod` | character varying | 20 |  | TIDAK | UQ |
| `nama` | character varying | 150 |  | TIDAK |  |
| `keterangan` | text |  |  | YA |  |
| `susunan` | smallint | 16 | '0'::smallint | TIDAK |  |
| `aktif` | boolean |  | true | TIDAK |  |
| `versi` | integer | 32 | 1 | TIDAK | UQ |
| `sah_dari` | date |  |  | TIDAK |  |
| `sah_hingga` | date |  |  | YA |  |
| `sumber` | character varying | 255 |  | YA |  |
| `sumber_url` | character varying | 500 |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |
| `kod_iso` | character varying | 10 |  | YA |  |
| `jenis` | character varying | 30 | 'negeri'::character varying | TIDAK |  |

**Kekangan**

- `ref_negeri_tarikh_sah_check` (c): `CHECK (((sah_hingga IS NULL) OR (sah_hingga >= sah_dari)))`
- `ref_negeri_pkey` (p): `PRIMARY KEY (id)`
- `ref_negeri_kod_versi_unique` (u): `UNIQUE (kod, versi)`

**Indeks**

- `ref_negeri_kod_sah_dari_index`: `CREATE INDEX ref_negeri_kod_sah_dari_index ON public.ref_negeri USING btree (kod, sah_dari)`
- `ref_negeri_kod_semasa_unique`: `CREATE UNIQUE INDEX ref_negeri_kod_semasa_unique ON public.ref_negeri USING btree (kod) WHERE (sah_hingga IS NULL)`
- `ref_negeri_kod_versi_unique`: `CREATE UNIQUE INDEX ref_negeri_kod_versi_unique ON public.ref_negeri USING btree (kod, versi)`
- `ref_negeri_nama_semasa_unique`: `CREATE UNIQUE INDEX ref_negeri_nama_semasa_unique ON public.ref_negeri USING btree (lower((nama)::text)) WHERE (sah_hingga IS NULL)`
- `ref_negeri_pkey`: `CREATE UNIQUE INDEX ref_negeri_pkey ON public.ref_negeri USING btree (id)`

### `ref_pbt`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('ref_pbt_id_seq'::regclass) | TIDAK | PK |
| `kod` | character varying | 20 |  | TIDAK | UQ |
| `nama` | character varying | 150 |  | TIDAK |  |
| `keterangan` | text |  |  | YA |  |
| `susunan` | smallint | 16 | '0'::smallint | TIDAK |  |
| `aktif` | boolean |  | true | TIDAK |  |
| `versi` | integer | 32 | 1 | TIDAK | UQ |
| `sah_dari` | date |  |  | TIDAK |  |
| `sah_hingga` | date |  |  | YA |  |
| `sumber` | character varying | 255 |  | YA |  |
| `sumber_url` | character varying | 500 |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |
| `singkatan` | character varying | 20 |  | TIDAK |  |
| `jenis` | character varying | 30 |  | TIDAK |  |
| `negeri_kod` | character varying | 20 |  | TIDAK |  |
| `daerah_kod` | json |  |  | YA |  |
| `wikidata` | character varying | 20 |  | YA |  |
| `osm_relation_id` | bigint | 64 |  | YA |  |

**Kekangan**

- `ref_pbt_tarikh_sah_check` (c): `CHECK (((sah_hingga IS NULL) OR (sah_hingga >= sah_dari)))`
- `ref_pbt_pkey` (p): `PRIMARY KEY (id)`
- `ref_pbt_kod_versi_unique` (u): `UNIQUE (kod, versi)`

**Indeks**

- `ref_pbt_kod_sah_dari_index`: `CREATE INDEX ref_pbt_kod_sah_dari_index ON public.ref_pbt USING btree (kod, sah_dari)`
- `ref_pbt_kod_semasa_unique`: `CREATE UNIQUE INDEX ref_pbt_kod_semasa_unique ON public.ref_pbt USING btree (kod) WHERE (sah_hingga IS NULL)`
- `ref_pbt_kod_versi_unique`: `CREATE UNIQUE INDEX ref_pbt_kod_versi_unique ON public.ref_pbt USING btree (kod, versi)`
- `ref_pbt_nama_semasa_unique`: `CREATE UNIQUE INDEX ref_pbt_nama_semasa_unique ON public.ref_pbt USING btree (lower((nama)::text)) WHERE (sah_hingga IS NULL)`
- `ref_pbt_negeri_kod_index`: `CREATE INDEX ref_pbt_negeri_kod_index ON public.ref_pbt USING btree (negeri_kod)`
- `ref_pbt_pkey`: `CREATE UNIQUE INDEX ref_pbt_pkey ON public.ref_pbt USING btree (id)`

### `ref_sempadan`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('ref_sempadan_id_seq'::regclass) | TIDAK | PK |
| `jenis` | character varying | 20 |  | TIDAK | UQ |
| `kod` | character varying | 20 |  | TIDAK | UQ |
| `nama` | character varying | 150 |  | TIDAK |  |
| `geometri` | geometry(MultiPolygon,4326) |  |  | TIDAK |  |
| `anggaran` | boolean |  | false | TIDAK |  |
| `toleransi_permudah` | numeric | 10,6 |  | YA |  |
| `luas_km2` | numeric | 12,3 |  | YA |  |
| `sumber` | character varying | 255 |  | TIDAK |  |
| `sumber_url` | character varying | 500 |  | YA |  |
| `lesen` | character varying | 100 |  | YA |  |
| `osm_id` | bigint | 64 |  | YA |  |
| `diambil_pada` | date |  |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `ref_sempadan_pkey` (p): `PRIMARY KEY (id)`
- `ref_sempadan_jenis_kod_unique` (u): `UNIQUE (jenis, kod)`

**Indeks**

- `ref_sempadan_geometri_spatialindex` *(spatial)*: `CREATE INDEX ref_sempadan_geometri_spatialindex ON public.ref_sempadan USING gist (geometri)`
- `ref_sempadan_jenis_kod_unique`: `CREATE UNIQUE INDEX ref_sempadan_jenis_kod_unique ON public.ref_sempadan USING btree (jenis, kod)`
- `ref_sempadan_pkey`: `CREATE UNIQUE INDEX ref_sempadan_pkey ON public.ref_sempadan USING btree (id)`

### `ref_tempat_ibadat`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('ref_tempat_ibadat_id_seq'::regclass) | TIDAK | PK |
| `osm_jenis` | character varying | 10 |  | TIDAK | UQ |
| `osm_id` | bigint | 64 |  | TIDAK | UQ |
| `nama` | character varying | 255 |  | YA |  |
| `jenis` | character varying | 20 |  | TIDAK |  |
| `daerah_kod` | character varying | 20 |  | YA |  |
| `mukim_kod` | character varying | 20 |  | YA |  |
| `geometri` | geometry(Point,4326) |  |  | TIDAK |  |
| `tag` | json |  |  | YA |  |
| `sumber` | character varying | 255 |  | TIDAK |  |
| `lesen` | character varying | 100 |  | TIDAK |  |
| `diambil_pada` | date |  |  | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `ref_tempat_ibadat_pkey` (p): `PRIMARY KEY (id)`
- `ref_tempat_ibadat_osm_jenis_osm_id_unique` (u): `UNIQUE (osm_jenis, osm_id)`

**Indeks**

- `ref_tempat_ibadat_daerah_kod_index`: `CREATE INDEX ref_tempat_ibadat_daerah_kod_index ON public.ref_tempat_ibadat USING btree (daerah_kod)`
- `ref_tempat_ibadat_geometri_spatialindex` *(spatial)*: `CREATE INDEX ref_tempat_ibadat_geometri_spatialindex ON public.ref_tempat_ibadat USING gist (geometri)`
- `ref_tempat_ibadat_mukim_kod_index`: `CREATE INDEX ref_tempat_ibadat_mukim_kod_index ON public.ref_tempat_ibadat USING btree (mukim_kod)`
- `ref_tempat_ibadat_osm_jenis_osm_id_unique`: `CREATE UNIQUE INDEX ref_tempat_ibadat_osm_jenis_osm_id_unique ON public.ref_tempat_ibadat USING btree (osm_jenis, osm_id)`
- `ref_tempat_ibadat_pkey`: `CREATE UNIQUE INDEX ref_tempat_ibadat_pkey ON public.ref_tempat_ibadat USING btree (id)`

### `rekod_arkib`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | uuid |  |  | TIDAK | PK |
| `entiti` | character varying | 64 |  | TIDAK |  |
| `id_rekod` | character varying | 64 |  | TIDAK |  |
| `id_jadual_retensi` | bigint | 64 |  | YA | FK |
| `status_arkib` | character varying | 20 |  | TIDAK |  |
| `id_pemohon` | bigint | 64 |  | YA | FK |
| `id_pelulus_arkib` | bigint | 64 |  | YA | FK |
| `id_aliran_kerja` | character varying | 64 |  | YA |  |
| `tarikh_arkib` | timestamp without time zone |  |  | YA |  |
| `lokasi_storan_arkib` | character varying | 100 | 'pangkalan_data:lot_tanah(status_lot=arkib)'::character varying | TIDAK |  |
| `tarikh_layak_lupus` | date |  |  | YA |  |
| `id_pemulih` | bigint | 64 |  | YA | FK |
| `tarikh_pulih` | timestamp without time zone |  |  | YA |  |
| `sebab` | text |  |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `rekod_arkib_id_jadual_retensi_foreign` (f): `FOREIGN KEY (id_jadual_retensi) REFERENCES jadual_retensi(id) ON DELETE SET NULL`
- `rekod_arkib_id_pelulus_arkib_foreign` (f): `FOREIGN KEY (id_pelulus_arkib) REFERENCES users(id) ON DELETE SET NULL`
- `rekod_arkib_id_pemohon_foreign` (f): `FOREIGN KEY (id_pemohon) REFERENCES users(id) ON DELETE SET NULL`
- `rekod_arkib_id_pemulih_foreign` (f): `FOREIGN KEY (id_pemulih) REFERENCES users(id) ON DELETE SET NULL`
- `rekod_arkib_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `rekod_arkib_entiti_id_rekod_index`: `CREATE INDEX rekod_arkib_entiti_id_rekod_index ON public.rekod_arkib USING btree (entiti, id_rekod)`
- `rekod_arkib_pkey`: `CREATE UNIQUE INDEX rekod_arkib_pkey ON public.rekod_arkib USING btree (id)`
- `rekod_arkib_status_arkib_index`: `CREATE INDEX rekod_arkib_status_arkib_index ON public.rekod_arkib USING btree (status_arkib)`

### `sesi`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | uuid |  |  | TIDAK | PK |
| `session_id` | character varying | 255 |  | TIDAK |  |
| `user_id` | bigint | 64 |  | TIDAK | FK |
| `kaedah_log_masuk` | character varying | 20 |  | YA |  |
| `alamat_ip` | character varying | 45 |  | YA |  |
| `user_agent` | text |  |  | YA |  |
| `sidik_peranti` | character varying | 64 |  | YA |  |
| `tarikh_mula` | timestamp without time zone |  |  | TIDAK |  |
| `tarikh_aktiviti_akhir` | timestamp without time zone |  |  | TIDAK |  |
| `tarikh_tamat` | timestamp without time zone |  |  | YA |  |
| `kaedah_tamat` | character varying | 20 |  | YA |  |
| `ditamatkan_oleh` | bigint | 64 |  | YA | FK |

**Kekangan**

- `sesi_ditamatkan_oleh_foreign` (f): `FOREIGN KEY (ditamatkan_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `sesi_user_id_foreign` (f): `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE`
- `sesi_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `sesi_pkey`: `CREATE UNIQUE INDEX sesi_pkey ON public.sesi USING btree (id)`
- `sesi_session_id_index`: `CREATE INDEX sesi_session_id_index ON public.sesi USING btree (session_id)`
- `sesi_user_id_tarikh_tamat_index`: `CREATE INDEX sesi_user_id_tarikh_tamat_index ON public.sesi USING btree (user_id, tarikh_tamat)`

### `sesi_import`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | uuid |  |  | TIDAK | PK |
| `entiti_sasaran` | character varying | 64 |  | TIDAK |  |
| `nama_fail` | character varying | 255 |  | TIDAK |  |
| `format_fail` | character varying | 10 |  | TIDAK |  |
| `id_pengguna` | bigint | 64 |  | YA | FK |
| `pemetaan` | jsonb |  |  | YA |  |
| `lajur_fail` | jsonb |  |  | YA |  |
| `bil_baris_dibaca` | integer | 32 | 0 | TIDAK |  |
| `bil_sah` | integer | 32 | 0 | TIDAK |  |
| `bil_berjaya` | integer | 32 | 0 | TIDAK |  |
| `bil_ditolak` | integer | 32 | 0 | TIDAK |  |
| `bil_diabaikan` | integer | 32 | 0 | TIDAK |  |
| `lokasi_fail_asal` | character varying | 64 |  | YA |  |
| `catatan_fail_asal` | character varying | 255 |  | YA |  |
| `status_sesi` | character varying | 20 | 'pratonton'::character varying | TIDAK |  |
| `tarikh_komit` | timestamp without time zone |  |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `sesi_import_id_pengguna_foreign` (f): `FOREIGN KEY (id_pengguna) REFERENCES users(id) ON DELETE SET NULL`
- `sesi_import_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `sesi_import_entiti_sasaran_created_at_index`: `CREATE INDEX sesi_import_entiti_sasaran_created_at_index ON public.sesi_import USING btree (entiti_sasaran, created_at)`
- `sesi_import_pkey`: `CREATE UNIQUE INDEX sesi_import_pkey ON public.sesi_import USING btree (id)`

### `sessions`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | character varying | 255 |  | TIDAK | PK |
| `user_id` | bigint | 64 |  | YA |  |
| `ip_address` | character varying | 45 |  | YA |  |
| `user_agent` | text |  |  | YA |  |
| `payload` | text |  |  | TIDAK |  |
| `last_activity` | integer | 32 |  | TIDAK |  |

**Kekangan**

- `sessions_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `sessions_last_activity_index`: `CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity)`
- `sessions_pkey`: `CREATE UNIQUE INDEX sessions_pkey ON public.sessions USING btree (id)`
- `sessions_user_id_index`: `CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id)`

### `settings`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('settings_id_seq'::regclass) | TIDAK | PK |
| `key` | character varying | 255 |  | TIDAK | UQ |
| `value` | text |  |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `settings_pkey` (p): `PRIMARY KEY (id)`
- `settings_key_unique` (u): `UNIQUE (key)`

**Indeks**

- `settings_key_unique`: `CREATE UNIQUE INDEX settings_key_unique ON public.settings USING btree (key)`
- `settings_pkey`: `CREATE UNIQUE INDEX settings_pkey ON public.settings USING btree (id)`

### `staging_hak_milik`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | uuid |  |  | TIDAK | PK |
| `migrasi_larian_id` | uuid |  |  | TIDAK | FK |
| `staging_lot_tanah_id` | uuid |  |  | TIDAK | FK |
| `no_hak_milik` | character varying | 30 |  | TIDAK |  |
| `jenis_hak_milik_kod` | character varying | 20 |  | TIDAK |  |
| `tarikh_daftar` | date |  |  | YA |  |
| `tarikh_tamat` | date |  |  | YA |  |
| `tempoh_pajakan_tahun` | smallint | 16 |  | YA |  |
| `sekatan_kepentingan` | text |  |  | YA |  |
| `lokasi_simpanan_fizikal` | character varying | 150 |  | YA |  |
| `sintetik` | boolean |  | false | TIDAK |  |
| `sumber_sistem` | character varying | 100 |  | TIDAK | UQ |
| `fail_asal` | character varying | 255 |  | TIDAK | UQ |
| `kunci_asal` | character varying | 100 |  | TIDAK | UQ |
| `dimuat_pada` | timestamp without time zone |  |  | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `staging_hak_milik_migrasi_larian_id_foreign` (f): `FOREIGN KEY (migrasi_larian_id) REFERENCES migrasi_larian(id)`
- `staging_hak_milik_staging_lot_tanah_id_foreign` (f): `FOREIGN KEY (staging_lot_tanah_id) REFERENCES staging_lot_tanah(id) ON DELETE CASCADE`
- `staging_hak_milik_pkey` (p): `PRIMARY KEY (id)`
- `staging_hak_milik_sumber_sistem_fail_asal_kunci_asal_unique` (u): `UNIQUE (sumber_sistem, fail_asal, kunci_asal)`

**Indeks**

- `staging_hak_milik_pkey`: `CREATE UNIQUE INDEX staging_hak_milik_pkey ON public.staging_hak_milik USING btree (id)`
- `staging_hak_milik_sumber_sistem_fail_asal_kunci_asal_unique`: `CREATE UNIQUE INDEX staging_hak_milik_sumber_sistem_fail_asal_kunci_asal_unique ON public.staging_hak_milik USING btree (sumber_sistem, fail_asal, kunci_asal)`

### `staging_lot_tanah`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | uuid |  |  | TIDAK | PK |
| `migrasi_larian_id` | uuid |  |  | TIDAK | FK |
| `no_lot` | character varying | 30 |  | TIDAK |  |
| `jenis_lot` | character varying | 10 |  | TIDAK |  |
| `negeri_kod` | character varying | 20 |  | TIDAK |  |
| `daerah_kod` | character varying | 20 |  | TIDAK |  |
| `mukim_kod` | character varying | 20 |  | TIDAK |  |
| `keluasan` | numeric | 14,4 |  | TIDAK |  |
| `unit_keluasan` | character varying | 20 |  | TIDAK |  |
| `kategori_guna_tanah_kod` | character varying | 20 |  | YA |  |
| `zoning` | character varying | 100 |  | YA |  |
| `syarat_nyata` | text |  |  | YA |  |
| `sekatan_kepentingan` | text |  |  | YA |  |
| `wakaf` | boolean |  | false | TIDAK |  |
| `jenis_wakaf` | character varying | 10 |  | YA |  |
| `baitulmal` | boolean |  | false | TIDAK |  |
| `geometri` | geometry(Polygon,4326) |  |  | YA |  |
| `sintetik` | boolean |  | false | TIDAK |  |
| `sumber_sistem` | character varying | 100 |  | TIDAK | UQ |
| `fail_asal` | character varying | 255 |  | TIDAK | UQ |
| `kunci_asal` | character varying | 100 |  | TIDAK | UQ |
| `dimuat_pada` | timestamp without time zone |  |  | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `staging_lot_tanah_keluasan_check` (c): `CHECK ((keluasan > (0)::numeric))`
- `staging_lot_tanah_migrasi_larian_id_foreign` (f): `FOREIGN KEY (migrasi_larian_id) REFERENCES migrasi_larian(id)`
- `staging_lot_tanah_pkey` (p): `PRIMARY KEY (id)`
- `staging_lot_tanah_sumber_sistem_fail_asal_kunci_asal_unique` (u): `UNIQUE (sumber_sistem, fail_asal, kunci_asal)`

**Indeks**

- `staging_lot_tanah_daerah_kod_index`: `CREATE INDEX staging_lot_tanah_daerah_kod_index ON public.staging_lot_tanah USING btree (daerah_kod)`
- `staging_lot_tanah_geometri_spatialindex` *(spatial)*: `CREATE INDEX staging_lot_tanah_geometri_spatialindex ON public.staging_lot_tanah USING gist (geometri)`
- `staging_lot_tanah_mukim_kod_no_lot_index`: `CREATE INDEX staging_lot_tanah_mukim_kod_no_lot_index ON public.staging_lot_tanah USING btree (mukim_kod, no_lot)`
- `staging_lot_tanah_pkey`: `CREATE UNIQUE INDEX staging_lot_tanah_pkey ON public.staging_lot_tanah USING btree (id)`
- `staging_lot_tanah_sumber_sistem_fail_asal_kunci_asal_unique`: `CREATE UNIQUE INDEX staging_lot_tanah_sumber_sistem_fail_asal_kunci_asal_unique ON public.staging_lot_tanah USING btree (sumber_sistem, fail_asal, kunci_asal)`

### `staging_pemilik`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | uuid |  |  | TIDAK | PK |
| `migrasi_larian_id` | uuid |  |  | TIDAK | FK |
| `staging_hak_milik_id` | uuid |  |  | TIDAK | FK |
| `peranan` | character varying | 30 |  | TIDAK |  |
| `jenis_pemilik` | character varying | 20 |  | TIDAK |  |
| `nama` | character varying | 200 |  | TIDAK |  |
| `no_pengenalan` | character varying | 20 |  | YA |  |
| `bahagian` | character varying | 20 |  | YA |  |
| `sintetik` | boolean |  | false | TIDAK |  |
| `sumber_sistem` | character varying | 100 |  | TIDAK | UQ |
| `fail_asal` | character varying | 255 |  | TIDAK | UQ |
| `kunci_asal` | character varying | 100 |  | TIDAK | UQ |
| `dimuat_pada` | timestamp without time zone |  |  | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `staging_pemilik_ic_sintetik_check` (c): `CHECK (((sintetik = false) OR (no_pengenalan IS NULL) OR ((no_pengenalan)::text ~ '^[0-9]{6}-00-[0-9]{4}$'::text)))`
- `staging_pemilik_migrasi_larian_id_foreign` (f): `FOREIGN KEY (migrasi_larian_id) REFERENCES migrasi_larian(id)`
- `staging_pemilik_staging_hak_milik_id_foreign` (f): `FOREIGN KEY (staging_hak_milik_id) REFERENCES staging_hak_milik(id) ON DELETE CASCADE`
- `staging_pemilik_pkey` (p): `PRIMARY KEY (id)`
- `staging_pemilik_sumber_sistem_fail_asal_kunci_asal_unique` (u): `UNIQUE (sumber_sistem, fail_asal, kunci_asal)`

**Indeks**

- `staging_pemilik_pkey`: `CREATE UNIQUE INDEX staging_pemilik_pkey ON public.staging_pemilik USING btree (id)`
- `staging_pemilik_sumber_sistem_fail_asal_kunci_asal_unique`: `CREATE UNIQUE INDEX staging_pemilik_sumber_sistem_fail_asal_kunci_asal_unique ON public.staging_pemilik USING btree (sumber_sistem, fail_asal, kunci_asal)`

### `status_akaun_log`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('status_akaun_log_id_seq'::regclass) | TIDAK | PK |
| `user_id` | bigint | 64 |  | TIDAK | FK |
| `status_lama` | character varying | 20 |  | TIDAK |  |
| `status_baru` | character varying | 20 |  | TIDAK |  |
| `sebab` | character varying | 255 |  | YA |  |
| `dilakukan_oleh` | bigint | 64 |  | YA | FK |
| `created_at` | timestamp without time zone |  | CURRENT_TIMESTAMP | TIDAK |  |

**Kekangan**

- `status_akaun_log_dilakukan_oleh_foreign` (f): `FOREIGN KEY (dilakukan_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `status_akaun_log_user_id_foreign` (f): `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE`
- `status_akaun_log_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `status_akaun_log_pkey`: `CREATE UNIQUE INDEX status_akaun_log_pkey ON public.status_akaun_log USING btree (id)`

### `tugasan_kualiti_data`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('tugasan_kualiti_data_id_seq'::regclass) | TIDAK | PK |
| `entiti` | character varying | 64 |  | TIDAK |  |
| `id_rekod` | character varying | 64 |  | TIDAK |  |
| `kod_isu` | character varying | 50 |  | TIDAK |  |
| `keterangan` | text |  |  | TIDAK |  |
| `tahap_keterukan` | character varying | 10 | 'ralat'::character varying | TIDAK |  |
| `id_stewardship` | bigint | 64 |  | YA | FK |
| `id_data_steward` | bigint | 64 |  | YA | FK |
| `kod_daerah` | character varying | 4 |  | YA |  |
| `status` | character varying | 20 | 'terbuka'::character varying | TIDAK |  |
| `tindakan_pembetulan` | text |  |  | YA |  |
| `diselesaikan_oleh` | bigint | 64 |  | YA | FK |
| `diselesaikan_pada` | timestamp without time zone |  |  | YA |  |
| `sumber` | character varying | 30 | 'semakan'::character varying | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `tugasan_kualiti_data_diselesaikan_oleh_foreign` (f): `FOREIGN KEY (diselesaikan_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `tugasan_kualiti_data_id_data_steward_foreign` (f): `FOREIGN KEY (id_data_steward) REFERENCES users(id) ON DELETE SET NULL`
- `tugasan_kualiti_data_id_stewardship_foreign` (f): `FOREIGN KEY (id_stewardship) REFERENCES daftar_stewardship_data(id) ON DELETE SET NULL`
- `tugasan_kualiti_data_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `tugasan_kualiti_data_entiti_id_rekod_kod_isu_status_index`: `CREATE INDEX tugasan_kualiti_data_entiti_id_rekod_kod_isu_status_index ON public.tugasan_kualiti_data USING btree (entiti, id_rekod, kod_isu, status)`
- `tugasan_kualiti_data_id_data_steward_status_index`: `CREATE INDEX tugasan_kualiti_data_id_data_steward_status_index ON public.tugasan_kualiti_data USING btree (id_data_steward, status)`
- `tugasan_kualiti_data_pkey`: `CREATE UNIQUE INDEX tugasan_kualiti_data_pkey ON public.tugasan_kualiti_data USING btree (id)`

### `users`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('users_id_seq'::regclass) | TIDAK | PK |
| `name` | character varying | 255 |  | TIDAK |  |
| `email` | character varying | 255 |  | TIDAK | UQ |
| `email_verified_at` | timestamp without time zone |  |  | YA |  |
| `password` | character varying | 255 |  | YA |  |
| `remember_token` | character varying | 100 |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |
| `role` | character varying | 255 | 'user'::character varying | TIDAK |  |
| `is_active` | boolean |  | true | TIDAK |  |
| `oidc_sub` | character varying | 255 |  | YA | UQ |
| `status_akaun` | character varying | 20 | 'aktif'::character varying | TIDAK |  |
| `jenis_pengesahan` | character varying | 10 | 'dalaman'::character varying | TIDAK |  |
| `no_pekerja` | character varying | 50 |  | YA |  |
| `bahagian` | character varying | 100 |  | YA |  |
| `unit` | character varying | 100 |  | YA |  |
| `jawatan` | character varying | 150 |  | YA |  |
| `tarikh_mula_capaian` | date |  |  | YA |  |
| `tarikh_tamat_capaian` | date |  |  | YA |  |
| `tarikh_log_masuk_akhir` | timestamp without time zone |  |  | YA |  |
| `tarikh_diaktifkan_semula` | timestamp without time zone |  |  | YA |  |
| `mfa_didaftar` | boolean |  | false | TIDAK |  |
| `kata_laluan_ditukar_pada` | timestamp without time zone |  |  | YA |  |
| `wajib_tukar_kata_laluan` | boolean |  | false | TIDAK |  |
| `cubaan_gagal` | smallint | 16 | '0'::smallint | TIDAK |  |
| `cubaan_gagal_pertama` | timestamp without time zone |  |  | YA |  |
| `dikunci_hingga` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `users_pkey` (p): `PRIMARY KEY (id)`
- `users_email_unique` (u): `UNIQUE (email)`
- `users_oidc_sub_unique` (u): `UNIQUE (oidc_sub)`

**Indeks**

- `users_bahagian_index`: `CREATE INDEX users_bahagian_index ON public.users USING btree (bahagian)`
- `users_email_unique`: `CREATE UNIQUE INDEX users_email_unique ON public.users USING btree (email)`
- `users_oidc_sub_unique`: `CREATE UNIQUE INDEX users_oidc_sub_unique ON public.users USING btree (oidc_sub)`
- `users_pkey`: `CREATE UNIQUE INDEX users_pkey ON public.users USING btree (id)`
- `users_status_akaun_index`: `CREATE INDEX users_status_akaun_index ON public.users USING btree (status_akaun)`

### `versi_rekod_induk`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | uuid |  |  | TIDAK | PK |
| `entiti` | character varying | 64 |  | TIDAK | UQ |
| `id_rekod` | character varying | 64 |  | TIDAK | UQ |
| `no_versi` | integer | 32 |  | YA | UQ |
| `nilai_sebelum` | jsonb |  |  | YA |  |
| `nilai_selepas` | jsonb |  |  | TIDAK |  |
| `kritikal` | boolean |  | false | TIDAK |  |
| `id_pengguna` | bigint | 64 |  | YA | FK |
| `justifikasi` | text |  |  | YA |  |
| `id_pengesah_kedua` | bigint | 64 |  | YA | FK |
| `status_pengesahan` | character varying | 20 |  | TIDAK |  |
| `id_aliran_kerja` | character varying | 64 |  | YA |  |
| `catatan_pengesahan` | text |  |  | YA |  |
| `tarikh_pengesahan` | timestamp without time zone |  |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `versi_rekod_induk_id_pengesah_kedua_foreign` (f): `FOREIGN KEY (id_pengesah_kedua) REFERENCES users(id) ON DELETE SET NULL`
- `versi_rekod_induk_id_pengguna_foreign` (f): `FOREIGN KEY (id_pengguna) REFERENCES users(id) ON DELETE SET NULL`
- `versi_rekod_induk_pkey` (p): `PRIMARY KEY (id)`
- `versi_rekod_induk_entiti_id_rekod_no_versi_unique` (u): `UNIQUE (entiti, id_rekod, no_versi)`

**Indeks**

- `versi_rekod_induk_entiti_id_rekod_index`: `CREATE INDEX versi_rekod_induk_entiti_id_rekod_index ON public.versi_rekod_induk USING btree (entiti, id_rekod)`
- `versi_rekod_induk_entiti_id_rekod_no_versi_unique`: `CREATE UNIQUE INDEX versi_rekod_induk_entiti_id_rekod_no_versi_unique ON public.versi_rekod_induk USING btree (entiti, id_rekod, no_versi)`
- `versi_rekod_induk_pkey`: `CREATE UNIQUE INDEX versi_rekod_induk_pkey ON public.versi_rekod_induk USING btree (id)`

### `wf_cuti_umum`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('wf_cuti_umum_id_seq'::regclass) | TIDAK | PK |
| `tarikh` | date |  |  | TIDAK | UQ |
| `nama` | character varying | 255 |  | TIDAK | UQ |
| `negeri` | character varying | 30 | 'selangor'::character varying | TIDAK | UQ |
| `jenis` | character varying | 20 | 'persekutuan'::character varying | TIDAK |  |
| `sumber` | character varying | 255 |  | YA |  |
| `catatan` | character varying | 255 |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `wf_cuti_umum_pkey` (p): `PRIMARY KEY (id)`
- `wf_cuti_umum_tarikh_negeri_nama_unique` (u): `UNIQUE (tarikh, negeri, nama)`

**Indeks**

- `wf_cuti_umum_pkey`: `CREATE UNIQUE INDEX wf_cuti_umum_pkey ON public.wf_cuti_umum USING btree (id)`
- `wf_cuti_umum_tarikh_negeri_nama_unique`: `CREATE UNIQUE INDEX wf_cuti_umum_tarikh_negeri_nama_unique ON public.wf_cuti_umum USING btree (tarikh, negeri, nama)`

### `wf_definisi`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('wf_definisi_id_seq'::regclass) | TIDAK | PK |
| `kod_aliran` | character varying | 100 |  | TIDAK | UQ |
| `nama` | character varying | 255 |  | TIDAK |  |
| `keterangan` | text |  |  | YA |  |
| `modul` | character varying | 20 |  | TIDAK |  |
| `jenis_transaksi` | character varying | 100 |  | TIDAK |  |
| `no_versi` | integer | 32 |  | TIDAK | UQ |
| `status` | character varying | 30 | 'draf'::character varying | TIDAK |  |
| `templat` | boolean |  | false | TIDAK |  |
| `tarikh_berkuat_kuasa` | timestamp without time zone |  |  | YA |  |
| `id_pencipta` | bigint | 64 |  | YA | FK |
| `kaedah_cipta` | character varying | 20 | 'manual'::character varying | TIDAK |  |
| `id_asal` | bigint | 64 |  | YA | FK |
| `diminta_aktif_oleh` | bigint | 64 |  | YA | FK |
| `diminta_aktif_pada` | timestamp without time zone |  |  | YA |  |
| `diluluskan_oleh` | bigint | 64 |  | YA | FK |
| `diluluskan_pada` | timestamp without time zone |  |  | YA |  |
| `susun_atur` | jsonb |  |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `wf_definisi_diluluskan_oleh_foreign` (f): `FOREIGN KEY (diluluskan_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `wf_definisi_diminta_aktif_oleh_foreign` (f): `FOREIGN KEY (diminta_aktif_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `wf_definisi_id_asal_foreign` (f): `FOREIGN KEY (id_asal) REFERENCES wf_definisi(id) ON DELETE SET NULL`
- `wf_definisi_id_pencipta_foreign` (f): `FOREIGN KEY (id_pencipta) REFERENCES users(id) ON DELETE SET NULL`
- `wf_definisi_pkey` (p): `PRIMARY KEY (id)`
- `wf_definisi_kod_aliran_no_versi_unique` (u): `UNIQUE (kod_aliran, no_versi)`

**Indeks**

- `wf_definisi_kod_aliran_index`: `CREATE INDEX wf_definisi_kod_aliran_index ON public.wf_definisi USING btree (kod_aliran)`
- `wf_definisi_kod_aliran_no_versi_unique`: `CREATE UNIQUE INDEX wf_definisi_kod_aliran_no_versi_unique ON public.wf_definisi USING btree (kod_aliran, no_versi)`
- `wf_definisi_pkey`: `CREATE UNIQUE INDEX wf_definisi_pkey ON public.wf_definisi USING btree (id)`

### `wf_definisi_log`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('wf_definisi_log_id_seq'::regclass) | TIDAK | PK |
| `id_definisi` | bigint | 64 |  | TIDAK | FK |
| `id_pengguna` | bigint | 64 |  | YA | FK |
| `tindakan` | character varying | 40 |  | TIDAK |  |
| `kaedah` | character varying | 30 |  | TIDAK |  |
| `arahan` | text |  |  | YA |  |
| `butiran` | jsonb |  |  | YA |  |
| `created_at` | timestamp without time zone |  | CURRENT_TIMESTAMP | TIDAK |  |

**Kekangan**

- `wf_definisi_log_id_definisi_foreign` (f): `FOREIGN KEY (id_definisi) REFERENCES wf_definisi(id) ON DELETE CASCADE`
- `wf_definisi_log_id_pengguna_foreign` (f): `FOREIGN KEY (id_pengguna) REFERENCES users(id) ON DELETE SET NULL`
- `wf_definisi_log_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `wf_definisi_log_pkey`: `CREATE UNIQUE INDEX wf_definisi_log_pkey ON public.wf_definisi_log USING btree (id)`

### `wf_eskalasi`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('wf_eskalasi_id_seq'::regclass) | TIDAK | PK |
| `id_peringkat` | bigint | 64 |  | TIDAK | FK |
| `aras` | integer | 32 |  | TIDAK |  |
| `tempoh_nilai` | integer | 32 | 0 | TIDAK |  |
| `tempoh_unit` | character varying | 20 | 'hari_bekerja'::character varying | TIDAK |  |
| `jenis_penerima` | character varying | 20 |  | TIDAK |  |
| `nilai_penerima` | character varying | 100 |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `wf_eskalasi_id_peringkat_foreign` (f): `FOREIGN KEY (id_peringkat) REFERENCES wf_peringkat(id) ON DELETE CASCADE`
- `wf_eskalasi_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `wf_eskalasi_pkey`: `CREATE UNIQUE INDEX wf_eskalasi_pkey ON public.wf_eskalasi USING btree (id)`

### `wf_eskalasi_log`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('wf_eskalasi_log_id_seq'::regclass) | TIDAK | PK |
| `id_tugasan` | bigint | 64 |  | TIDAK | FK |
| `aras` | integer | 32 |  | TIDAK |  |
| `penerima` | jsonb |  |  | TIDAK |  |
| `created_at` | timestamp without time zone |  | CURRENT_TIMESTAMP | TIDAK |  |

**Kekangan**

- `wf_eskalasi_log_id_tugasan_foreign` (f): `FOREIGN KEY (id_tugasan) REFERENCES wf_tugasan(id) ON DELETE CASCADE`
- `wf_eskalasi_log_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `wf_eskalasi_log_pkey`: `CREATE UNIQUE INDEX wf_eskalasi_log_pkey ON public.wf_eskalasi_log USING btree (id)`

### `wf_instans`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | uuid |  |  | TIDAK | PK |
| `id_definisi` | bigint | 64 |  | TIDAK | FK |
| `no_versi_definisi` | integer | 32 |  | TIDAK |  |
| `kod_aliran` | character varying | 100 |  | TIDAK |  |
| `modul` | character varying | 20 |  | TIDAK |  |
| `jenis_subjek` | character varying | 100 |  | TIDAK |  |
| `id_rekod` | character varying | 100 |  | TIDAK |  |
| `tajuk` | character varying | 255 |  | YA |  |
| `konteks` | jsonb |  |  | YA |  |
| `kod_daerah` | character varying | 20 |  | YA |  |
| `pautan` | character varying | 255 |  | YA |  |
| `id_pemohon` | bigint | 64 |  | TIDAK | FK |
| `status` | character varying | 20 | 'berjalan'::character varying | TIDAK |  |
| `keadaan` | character varying | 30 |  | YA |  |
| `peringkat_semasa` | bigint | 64 |  | YA | FK |
| `kitaran` | integer | 32 | 0 | TIDAK |  |
| `laluan` | jsonb |  |  | YA |  |
| `tarikh_mula` | timestamp without time zone |  |  | TIDAK |  |
| `tarikh_tamat` | timestamp without time zone |  |  | YA |  |
| `ulasan_keputusan` | text |  |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `wf_instans_id_definisi_foreign` (f): `FOREIGN KEY (id_definisi) REFERENCES wf_definisi(id)`
- `wf_instans_id_pemohon_foreign` (f): `FOREIGN KEY (id_pemohon) REFERENCES users(id)`
- `wf_instans_peringkat_semasa_foreign` (f): `FOREIGN KEY (peringkat_semasa) REFERENCES wf_peringkat(id) ON DELETE SET NULL`
- `wf_instans_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `wf_instans_jenis_subjek_id_rekod_index`: `CREATE INDEX wf_instans_jenis_subjek_id_rekod_index ON public.wf_instans USING btree (jenis_subjek, id_rekod)`
- `wf_instans_kod_aliran_index`: `CREATE INDEX wf_instans_kod_aliran_index ON public.wf_instans USING btree (kod_aliran)`
- `wf_instans_kod_daerah_index`: `CREATE INDEX wf_instans_kod_daerah_index ON public.wf_instans USING btree (kod_daerah)`
- `wf_instans_pkey`: `CREATE UNIQUE INDEX wf_instans_pkey ON public.wf_instans USING btree (id)`
- `wf_instans_status_index`: `CREATE INDEX wf_instans_status_index ON public.wf_instans USING btree (status)`

### `wf_kumpulan`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('wf_kumpulan_id_seq'::regclass) | TIDAK | PK |
| `kod` | character varying | 50 |  | TIDAK | UQ |
| `nama` | character varying | 255 |  | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `wf_kumpulan_pkey` (p): `PRIMARY KEY (id)`
- `wf_kumpulan_kod_unique` (u): `UNIQUE (kod)`

**Indeks**

- `wf_kumpulan_kod_unique`: `CREATE UNIQUE INDEX wf_kumpulan_kod_unique ON public.wf_kumpulan USING btree (kod)`
- `wf_kumpulan_pkey`: `CREATE UNIQUE INDEX wf_kumpulan_pkey ON public.wf_kumpulan USING btree (id)`

### `wf_kumpulan_ahli`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id_kumpulan` | bigint | 64 |  | TIDAK | FK, PK |
| `id_pengguna` | bigint | 64 |  | TIDAK | FK, PK |

**Kekangan**

- `wf_kumpulan_ahli_id_kumpulan_foreign` (f): `FOREIGN KEY (id_kumpulan) REFERENCES wf_kumpulan(id) ON DELETE CASCADE`
- `wf_kumpulan_ahli_id_pengguna_foreign` (f): `FOREIGN KEY (id_pengguna) REFERENCES users(id) ON DELETE CASCADE`
- `wf_kumpulan_ahli_pkey` (p): `PRIMARY KEY (id_kumpulan, id_pengguna)`

**Indeks**

- `wf_kumpulan_ahli_pkey`: `CREATE UNIQUE INDEX wf_kumpulan_ahli_pkey ON public.wf_kumpulan_ahli USING btree (id_kumpulan, id_pengguna)`

### `wf_pelulus`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('wf_pelulus_id_seq'::regclass) | TIDAK | PK |
| `id_peringkat` | bigint | 64 |  | TIDAK | FK |
| `jenis` | character varying | 20 |  | TIDAK |  |
| `nilai` | character varying | 100 |  | YA |  |
| `label` | character varying | 255 |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `wf_pelulus_id_peringkat_foreign` (f): `FOREIGN KEY (id_peringkat) REFERENCES wf_peringkat(id) ON DELETE CASCADE`
- `wf_pelulus_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `wf_pelulus_pkey`: `CREATE UNIQUE INDEX wf_pelulus_pkey ON public.wf_pelulus USING btree (id)`

### `wf_pemangku`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('wf_pemangku_id_seq'::regclass) | TIDAK | PK |
| `id_pengguna` | bigint | 64 |  | TIDAK | FK |
| `id_pemangku` | bigint | 64 |  | TIDAK | FK |
| `tarikh_mula` | date |  |  | TIDAK |  |
| `tarikh_tamat` | date |  |  | TIDAK |  |
| `sebab` | character varying | 255 |  | YA |  |
| `dicipta_oleh` | bigint | 64 |  | YA | FK |
| `dibatalkan_pada` | timestamp without time zone |  |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `wf_pemangku_dicipta_oleh_foreign` (f): `FOREIGN KEY (dicipta_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `wf_pemangku_id_pemangku_foreign` (f): `FOREIGN KEY (id_pemangku) REFERENCES users(id) ON DELETE CASCADE`
- `wf_pemangku_id_pengguna_foreign` (f): `FOREIGN KEY (id_pengguna) REFERENCES users(id) ON DELETE CASCADE`
- `wf_pemangku_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `wf_pemangku_pkey`: `CREATE UNIQUE INDEX wf_pemangku_pkey ON public.wf_pemangku USING btree (id)`

### `wf_peralihan`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('wf_peralihan_id_seq'::regclass) | TIDAK | PK |
| `id_definisi` | bigint | 64 |  | TIDAK | FK |
| `dari` | character varying | 50 |  | TIDAK |  |
| `ke` | character varying | 50 |  | TIDAK |  |
| `keutamaan` | integer | 32 | 0 | TIDAK |  |
| `logik` | character varying | 10 | 'semua'::character varying | TIDAK |  |
| `label` | character varying | 255 |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `wf_peralihan_id_definisi_foreign` (f): `FOREIGN KEY (id_definisi) REFERENCES wf_definisi(id) ON DELETE CASCADE`
- `wf_peralihan_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `wf_peralihan_pkey`: `CREATE UNIQUE INDEX wf_peralihan_pkey ON public.wf_peralihan USING btree (id)`

### `wf_peringkat`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('wf_peringkat_id_seq'::regclass) | TIDAK | PK |
| `id_definisi` | bigint | 64 |  | TIDAK | FK, UQ |
| `kod` | character varying | 50 |  | TIDAK | UQ |
| `no_urutan` | integer | 32 |  | TIDAK |  |
| `nama_peringkat` | character varying | 255 |  | TIDAK |  |
| `mod_kelulusan` | character varying | 20 | 'mana_mana'::character varying | TIDAK |  |
| `kuorum` | integer | 32 |  | YA |  |
| `sla_nilai` | integer | 32 |  | YA |  |
| `sla_unit` | character varying | 20 | 'hari_bekerja'::character varying | TIDAK |  |
| `perlu_mfa` | boolean |  | false | TIDAK |  |
| `perlu_justifikasi` | boolean |  | false | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `wf_peringkat_id_definisi_foreign` (f): `FOREIGN KEY (id_definisi) REFERENCES wf_definisi(id) ON DELETE CASCADE`
- `wf_peringkat_pkey` (p): `PRIMARY KEY (id)`
- `wf_peringkat_id_definisi_kod_unique` (u): `UNIQUE (id_definisi, kod)`

**Indeks**

- `wf_peringkat_id_definisi_kod_unique`: `CREATE UNIQUE INDEX wf_peringkat_id_definisi_kod_unique ON public.wf_peringkat USING btree (id_definisi, kod)`
- `wf_peringkat_pkey`: `CREATE UNIQUE INDEX wf_peringkat_pkey ON public.wf_peringkat USING btree (id)`

### `wf_pindah_versi`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('wf_pindah_versi_id_seq'::regclass) | TIDAK | PK |
| `id_instans` | uuid |  |  | TIDAK | FK |
| `id_definisi_asal` | bigint | 64 |  | TIDAK | FK |
| `id_definisi_baru` | bigint | 64 |  | TIDAK | FK |
| `sebab` | character varying | 255 |  | TIDAK |  |
| `status` | character varying | 20 | 'menunggu'::character varying | TIDAK |  |
| `diminta_oleh` | bigint | 64 |  | TIDAK | FK |
| `diputuskan_oleh` | bigint | 64 |  | YA | FK |
| `diputuskan_pada` | timestamp without time zone |  |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `wf_pindah_versi_diminta_oleh_foreign` (f): `FOREIGN KEY (diminta_oleh) REFERENCES users(id)`
- `wf_pindah_versi_diputuskan_oleh_foreign` (f): `FOREIGN KEY (diputuskan_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `wf_pindah_versi_id_definisi_asal_foreign` (f): `FOREIGN KEY (id_definisi_asal) REFERENCES wf_definisi(id)`
- `wf_pindah_versi_id_definisi_baru_foreign` (f): `FOREIGN KEY (id_definisi_baru) REFERENCES wf_definisi(id)`
- `wf_pindah_versi_id_instans_foreign` (f): `FOREIGN KEY (id_instans) REFERENCES wf_instans(id) ON DELETE CASCADE`
- `wf_pindah_versi_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `wf_pindah_versi_pkey`: `CREATE UNIQUE INDEX wf_pindah_versi_pkey ON public.wf_pindah_versi USING btree (id)`

### `wf_sejarah`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('wf_sejarah_id_seq'::regclass) | TIDAK | PK |
| `id_instans` | uuid |  |  | TIDAK | FK |
| `id_tugasan` | bigint | 64 |  | YA | FK |
| `id_peringkat` | bigint | 64 |  | YA | FK |
| `nama_peringkat` | character varying | 255 |  | YA |  |
| `peristiwa` | character varying | 30 |  | TIDAK |  |
| `id_pengguna` | bigint | 64 |  | YA | FK |
| `id_bagi_pihak` | bigint | 64 |  | YA | FK |
| `peranan_semasa` | jsonb |  |  | YA |  |
| `justifikasi` | text |  |  | YA |  |
| `alamat_ip` | character varying | 45 |  | YA |  |
| `mfa_pada` | timestamp without time zone |  |  | YA |  |
| `butiran` | jsonb |  |  | YA |  |
| `tarikh_masa` | timestamp without time zone |  | CURRENT_TIMESTAMP | TIDAK |  |

**Kekangan**

- `wf_sejarah_id_bagi_pihak_foreign` (f): `FOREIGN KEY (id_bagi_pihak) REFERENCES users(id) ON DELETE SET NULL`
- `wf_sejarah_id_instans_foreign` (f): `FOREIGN KEY (id_instans) REFERENCES wf_instans(id) ON DELETE CASCADE`
- `wf_sejarah_id_pengguna_foreign` (f): `FOREIGN KEY (id_pengguna) REFERENCES users(id) ON DELETE SET NULL`
- `wf_sejarah_id_peringkat_foreign` (f): `FOREIGN KEY (id_peringkat) REFERENCES wf_peringkat(id) ON DELETE SET NULL`
- `wf_sejarah_id_tugasan_foreign` (f): `FOREIGN KEY (id_tugasan) REFERENCES wf_tugasan(id) ON DELETE SET NULL`
- `wf_sejarah_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `wf_sejarah_id_instans_id_index`: `CREATE INDEX wf_sejarah_id_instans_id_index ON public.wf_sejarah USING btree (id_instans, id)`
- `wf_sejarah_pkey`: `CREATE UNIQUE INDEX wf_sejarah_pkey ON public.wf_sejarah USING btree (id)`

### `wf_susulan`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('wf_susulan_id_seq'::regclass) | TIDAK | PK |
| `id_definisi` | bigint | 64 |  | TIDAK | FK |
| `bila` | character varying | 20 | 'lulus'::character varying | TIDAK |  |
| `tajuk` | character varying | 255 |  | TIDAK |  |
| `jenis_penerima` | character varying | 20 |  | TIDAK |  |
| `nilai_penerima` | character varying | 100 |  | YA |  |
| `sla_nilai` | integer | 32 |  | YA |  |
| `sla_unit` | character varying | 20 | 'hari_bekerja'::character varying | TIDAK |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `wf_susulan_id_definisi_foreign` (f): `FOREIGN KEY (id_definisi) REFERENCES wf_definisi(id) ON DELETE CASCADE`
- `wf_susulan_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `wf_susulan_pkey`: `CREATE UNIQUE INDEX wf_susulan_pkey ON public.wf_susulan USING btree (id)`

### `wf_syarat`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('wf_syarat_id_seq'::regclass) | TIDAK | PK |
| `id_peralihan` | bigint | 64 |  | TIDAK | FK |
| `medan` | character varying | 100 |  | TIDAK |  |
| `operator` | character varying | 20 |  | TIDAK |  |
| `nilai` | jsonb |  |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `wf_syarat_id_peralihan_foreign` (f): `FOREIGN KEY (id_peralihan) REFERENCES wf_peralihan(id) ON DELETE CASCADE`
- `wf_syarat_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `wf_syarat_pkey`: `CREATE UNIQUE INDEX wf_syarat_pkey ON public.wf_syarat USING btree (id)`

### `wf_tugasan`

| Medan | Jenis data | Saiz | Lalai | Null | Kunci |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint | 64 | nextval('wf_tugasan_id_seq'::regclass) | TIDAK | PK |
| `id_instans` | uuid |  |  | TIDAK | FK |
| `id_peringkat` | bigint | 64 |  | YA | FK |
| `jenis` | character varying | 20 | 'kelulusan'::character varying | TIDAK |  |
| `tajuk` | character varying | 255 |  | YA |  |
| `kitaran` | integer | 32 | 0 | TIDAK |  |
| `id_pelulus` | bigint | 64 |  | YA | FK |
| `jenis_penerima` | character varying | 20 |  | TIDAK |  |
| `nilai_penerima` | character varying | 100 |  | YA |  |
| `penerima_kunci` | character varying | 130 |  | TIDAK |  |
| `id_pelulus_asal` | bigint | 64 |  | YA | FK |
| `status` | character varying | 20 | 'menunggu'::character varying | TIDAK |  |
| `keputusan` | character varying | 20 |  | YA |  |
| `tarikh_akhir_sla` | timestamp without time zone |  |  | YA |  |
| `ditangguh_hingga` | timestamp without time zone |  |  | YA |  |
| `aras_eskalasi` | integer | 32 | 0 | TIDAK |  |
| `amaran_sla_pada` | timestamp without time zone |  |  | YA |  |
| `diselesaikan_oleh` | bigint | 64 |  | YA | FK |
| `diselesaikan_pada` | timestamp without time zone |  |  | YA |  |
| `created_at` | timestamp without time zone |  |  | YA |  |
| `updated_at` | timestamp without time zone |  |  | YA |  |

**Kekangan**

- `wf_tugasan_diselesaikan_oleh_foreign` (f): `FOREIGN KEY (diselesaikan_oleh) REFERENCES users(id) ON DELETE SET NULL`
- `wf_tugasan_id_instans_foreign` (f): `FOREIGN KEY (id_instans) REFERENCES wf_instans(id) ON DELETE CASCADE`
- `wf_tugasan_id_pelulus_asal_foreign` (f): `FOREIGN KEY (id_pelulus_asal) REFERENCES users(id) ON DELETE SET NULL`
- `wf_tugasan_id_pelulus_foreign` (f): `FOREIGN KEY (id_pelulus) REFERENCES wf_pelulus(id) ON DELETE SET NULL`
- `wf_tugasan_id_peringkat_foreign` (f): `FOREIGN KEY (id_peringkat) REFERENCES wf_peringkat(id)`
- `wf_tugasan_pkey` (p): `PRIMARY KEY (id)`

**Indeks**

- `wf_tugasan_penerima_kunci_index`: `CREATE INDEX wf_tugasan_penerima_kunci_index ON public.wf_tugasan USING btree (penerima_kunci)`
- `wf_tugasan_pkey`: `CREATE UNIQUE INDEX wf_tugasan_pkey ON public.wf_tugasan USING btree (id)`
- `wf_tugasan_status_index`: `CREATE INDEX wf_tugasan_status_index ON public.wf_tugasan USING btree (status)`

## View dan materialized view

### `vw_kualiti_data_rekod_tidak_lengkap` (view)

```sql
SELECT lot_id,
    no_lot,
    kod_mukim,
    kod_daerah,
    bahagian_pemilik,
    id_data_steward,
    medan_tidak_lengkap
   FROM ( SELECT l.id AS lot_id,
            l.no_lot,
            l.kod_mukim,
            l.kod_daerah,
            l.bahagian_pemilik,
            l.id_data_steward,
            array_remove(ARRAY[
                CASE
                    WHEN l.keluasan IS NULL THEN 'keluasan'::text
                    ELSE NULL::text
                END,
                CASE
                    WHEN l.kategori_guna_tanah IS NULL THEN 'kategori_guna_tanah'::text
                    ELSE NULL::text
                END,
                CASE
                    WHEN l.bahagian_pemilik IS NULL THEN 'bahagian_pemilik'::text
                    ELSE NULL::text
                END,
                CASE
                    WHEN l.id_data_owner IS NULL THEN 'id_data_owner'::text
                    ELSE NULL::text
                END,
                CASE
                    WHEN l.id_data_steward IS NULL THEN 'id_data_steward'::text
                    ELSE NULL::text
                END,
                CASE
                    WHEN l.koordinat_pusat IS NULL THEN 'koordinat_pusat'::text
                    ELSE NULL::text
                END], NULL::text) AS medan_tidak_lengkap
           FROM lot_tanah l
          WHERE l.deleted_at IS NULL AND l.status_lot::text <> 'arkib'::text) t
  WHERE cardinality(medan_tidak_lengkap) > 0;
```

### `vw_lot_spatial_belum_disahkan` (view)

```sql
SELECT l.id AS lot_id,
    l.no_lot,
    l.kod_mukim,
    l.kod_daerah,
    l.bahagian_pemilik,
    l.id_data_steward,
    ds.id AS id_spatial,
    COALESCE(ds.status_pengesahan, 'tiada_geometri'::character varying) AS status_pengesahan,
    ds.sumber_data,
    ds.tarikh_perolehan_data
   FROM lot_tanah l
     LEFT JOIN data_spatial ds ON ds.lot_id = l.id AND ds.semasa
  WHERE l.deleted_at IS NULL AND l.status_lot::text <> 'arkib'::text AND (ds.id IS NULL OR (ds.status_pengesahan::text = ANY (ARRAY['belum_disahkan'::character varying, 'bertindih'::character varying]::text[])));
```

### `vw_profil_hartanah_360` (view)

```sql
SELECT l.id AS lot_id,
    l.no_lot,
    l.kod_negeri,
    l.kod_daerah,
    l.kod_mukim,
    l.keluasan,
    l.unit_keluasan,
    l.kategori_guna_tanah,
    l.zoning,
    l.bahagian_pemilik,
    l.status_lot,
    l.klasifikasi,
    l.versi_rekod,
    st_y(l.koordinat_pusat) AS lat,
    st_x(l.koordinat_pusat) AS lng,
    ds.status_pengesahan AS status_pengesahan_spatial,
    ds.keluasan_dikira,
    ds.sumber_data AS sumber_data_spatial,
    ( SELECT count(*) AS count
           FROM penilaian p
          WHERE p.lot_id = l.id AND p.deleted_at IS NULL) AS bil_penilaian,
    ( SELECT count(*) AS count
           FROM kontrak_pajakan k
          WHERE k.lot_id = l.id AND k.deleted_at IS NULL) AS bil_kontrak_pajakan,
    ( SELECT count(*) AS count
           FROM cukai c
          WHERE c.lot_id = l.id AND c.deleted_at IS NULL) AS bil_cukai,
    ( SELECT count(*) AS count
           FROM aduan a
          WHERE a.lot_id = l.id AND a.deleted_at IS NULL) AS bil_aduan,
    ( SELECT count(*) AS count
           FROM kes_mahkamah_lot km
          WHERE km.lot_id = l.id) AS bil_kes_mahkamah,
    l.updated_at
   FROM lot_tanah l
     LEFT JOIN data_spatial ds ON ds.lot_id = l.id AND ds.semasa
  WHERE l.deleted_at IS NULL;
```

## Fungsi dan stored procedure

### `aud_arkib_pindah(p_had timestamp with time zone, p_kelompok text)` → integer (plpgsql)

```sql
CREATE OR REPLACE FUNCTION public.aud_arkib_pindah(p_had timestamp with time zone, p_kelompok text)
 RETURNS integer
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public', 'pg_temp'
AS $function$
    DECLARE
        v_jumlah integer;
    BEGIN
        -- SRS-AUD-20 floor: never archive anything younger than 24 months.
        IF p_had > clock_timestamp() - interval '24 months' THEN
            RAISE EXCEPTION 'Had arkib % kurang daripada tempoh minimum 24 bulan', p_had;
        END IF;

        PERFORM pg_advisory_xact_lock(7319019);
        PERFORM set_config('sisdata.aud_arkib', 'on', true);

        INSERT INTO audit_arkib (id, no_urutan, cincangan_sebelum, cincangan_rekod, tarikh_masa_utc, tarikh_masa_tempatan, id_pengguna, pengecam_pengguna, peranan_semasa, id_sesi, alamat_ip, user_agent, jenis_peranti, lokasi_anggaran, saluran_capaian, modul, nama_entiti, id_rekod, jenis_tindakan, status_transaksi, sebab_perubahan, id_korelasi, klasifikasi, meta, perubahan, diarkib_pada, id_kelompok_arkib)
        SELECT r.id, r.no_urutan, r.cincangan_sebelum, r.cincangan_rekod, r.tarikh_masa_utc, r.tarikh_masa_tempatan, r.id_pengguna, r.pengecam_pengguna, r.peranan_semasa, r.id_sesi, r.alamat_ip, r.user_agent, r.jenis_peranti, r.lokasi_anggaran, r.saluran_capaian, r.modul, r.nama_entiti, r.id_rekod, r.jenis_tindakan, r.status_transaksi, r.sebab_perubahan, r.id_korelasi, r.klasifikasi, r.meta,
               (SELECT json_agg(json_build_object('nama_medan', p.nama_medan, 'nilai_asal', p.nilai_asal,
                        'nilai_baharu', p.nilai_baharu, 'sensitif_ditapis', p.sensitif_ditapis) ORDER BY p.id)
                  FROM audit_perubahan_medan p WHERE p.id_audit = r.id),
               now(), p_kelompok
          FROM audit_rekod r
         WHERE r.tarikh_masa_utc < p_had
         ORDER BY r.no_urutan;
        GET DIAGNOSTICS v_jumlah = ROW_COUNT;

        DELETE FROM audit_perubahan_medan p USING audit_rekod r
         WHERE p.id_audit = r.id AND r.tarikh_masa_utc < p_had;
        DELETE FROM audit_rekod r WHERE r.tarikh_masa_utc < p_had;

        PERFORM set_config('sisdata.aud_arkib', 'off', true);

        RETURN v_jumlah;
    END;
    $function$
```

### `aud_tolak_ubah()` → trigger (plpgsql)

```sql
CREATE OR REPLACE FUNCTION public.aud_tolak_ubah()
 RETURNS trigger
 LANGUAGE plpgsql
AS $function$
    BEGIN
        -- Archive move (aud_arkib_pindah) only: the row must be old enough
        -- and already copied, byte-identical hash, into audit_arkib.
        IF TG_OP = 'DELETE' AND current_setting('sisdata.aud_arkib', true) = 'on' THEN
            -- Nested IFs: plpgsql resolves every OLD.<field> in an expression,
            -- so table-specific fields must not share one condition.
            IF TG_TABLE_NAME = 'audit_rekod' THEN
                IF OLD.tarikh_masa_utc < clock_timestamp() - interval '24 months'
                   AND EXISTS (SELECT 1 FROM audit_arkib a WHERE a.id = OLD.id AND a.cincangan_rekod = OLD.cincangan_rekod) THEN
                    RETURN OLD;
                END IF;
            ELSIF TG_TABLE_NAME = 'audit_perubahan_medan' THEN
                IF EXISTS (SELECT 1 FROM audit_arkib a WHERE a.id = OLD.id_audit) THEN
                    RETURN OLD;
                END IF;
            END IF;
        END IF;

        RAISE EXCEPTION 'Rekod audit hanya-tambah: % ke atas % ditolak', TG_OP, TG_TABLE_NAME
            USING ERRCODE = 'insufficient_privilege';
    END;
    $function$
```

### `sp_kesan_duplikasi_lot(p_ambang_nama real, p_lot uuid)` → TABLE(id_a uuid, id_b uuid, no_lot_a text, no_lot_b text, skor_nama real, sama_mukim boolean, beza_keluasan_peratus numeric, pertindihan_peratus numeric) (sql)

```sql
CREATE OR REPLACE FUNCTION public.sp_kesan_duplikasi_lot(p_ambang_nama real DEFAULT 0.6, p_lot uuid DEFAULT NULL::uuid)
 RETURNS TABLE(id_a uuid, id_b uuid, no_lot_a text, no_lot_b text, skor_nama real, sama_mukim boolean, beza_keluasan_peratus numeric, pertindihan_peratus numeric)
 LANGUAGE sql
 STABLE
AS $function$
    SELECT a.id, b.id, a.no_lot_normal::text, b.no_lot_normal::text,
           similarity(a.no_lot_normal, b.no_lot_normal),
           a.kod_mukim = b.kod_mukim,
           CASE WHEN a.keluasan_m2 > 0 AND b.keluasan_m2 > 0
                THEN round(abs(a.keluasan_m2 - b.keluasan_m2) / greatest(a.keluasan_m2, b.keluasan_m2) * 100, 2) END,
           CASE WHEN ga.geometri IS NOT NULL AND gb.geometri IS NOT NULL AND ST_Intersects(ga.geometri, gb.geometri)
                THEN round((ST_Area(ST_Intersection(ga.geometri, gb.geometri)::geography)
                     / NULLIF(least(ST_Area(ga.geometri::geography), ST_Area(gb.geometri::geography)), 0) * 100)::numeric, 2)
                ELSE 0 END
    FROM (SELECT l.*, CASE l.unit_keluasan WHEN 'hektar' THEN l.keluasan * 10000 WHEN 'ekar' THEN l.keluasan * 4046.8564 ELSE l.keluasan END AS keluasan_m2
          FROM lot_tanah l WHERE l.deleted_at IS NULL AND l.status_lot NOT IN ('arkib', 'digabung')) a
    JOIN (SELECT l.*, CASE l.unit_keluasan WHEN 'hektar' THEN l.keluasan * 10000 WHEN 'ekar' THEN l.keluasan * 4046.8564 ELSE l.keluasan END AS keluasan_m2
          FROM lot_tanah l WHERE l.deleted_at IS NULL AND l.status_lot NOT IN ('arkib', 'digabung')) b
      ON a.id < b.id AND a.kod_daerah = b.kod_daerah
    LEFT JOIN data_spatial ga ON ga.lot_id = a.id AND ga.semasa
    LEFT JOIN data_spatial gb ON gb.lot_id = b.id AND gb.semasa
    WHERE (p_lot IS NULL OR a.id = p_lot OR b.id = p_lot)
      AND (
          similarity(a.no_lot_normal, b.no_lot_normal) >= p_ambang_nama
          OR (a.kod_mukim = b.kod_mukim AND regexp_replace(a.no_lot_normal, '[^0-9]', '', 'g') = regexp_replace(b.no_lot_normal, '[^0-9]', '', 'g'))
          OR (ga.geometri IS NOT NULL AND gb.geometri IS NOT NULL AND ST_Intersects(ga.geometri, gb.geometri)
              AND NOT ST_Touches(ga.geometri, gb.geometri))
      )
$function$
```

