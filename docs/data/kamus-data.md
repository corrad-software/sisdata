# Kamus Data — SISDATA

> Dijana secara automatik oleh `php artisan pro:jana-dokumen-data` daripada skema pangkalan data `myds_laravel_test_pro` pada 24/10/2026 15:16. Jangan sunting dengan tangan — jana semula.

Setiap medan: nama perniagaan, nama teknikal, definisi, jenis data, format, nilai dibenarkan, status wajib, data owner, data steward dan klasifikasi (SRS-PRO-05). Metadata perniagaan disunting dalam sistem (Kamus Data → Sunting); medan tanpa metadata dikurasi memaparkan komen skema.

## `adm_indeks_carian`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `entiti` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `label` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `medan` |  | searchable fields, passed to the provider | json |  |  | Tidak |  |  | dalaman |
| `pemberat` |  |  | numeric |  |  | Ya |  |  | dalaman |
| `aktif` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `status_indeks` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `diindeks_semula_pada` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `justifikasi` |  |  | text |  |  | Tidak |  |  | dalaman |
| `dikemas_kini_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `adm_integrasi`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `kod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nama` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `jenis` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `endpoint` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `id_rahsia` |  | adm_rahsia.kunci — never the secret itself | character varying |  |  | Tidak |  |  | dalaman |
| `tempoh_tamat_masa` |  | seconds | integer |  |  | Ya |  |  | dalaman |
| `kadar_capaian` |  | max calls per minute | integer |  |  | Tidak |  |  | dalaman |
| `jadual` |  | cron expression for scheduled exchange | character varying |  |  | Tidak |  |  | dalaman |
| `status` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `kegagalan_berturut` |  |  | integer |  |  | Ya |  |  | dalaman |
| `terakhir_berjaya_pada` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `terakhir_gagal_pada` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `justifikasi` |  |  | text |  |  | Tidak |  |  | dalaman |
| `dikemas_kini_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `adm_kesihatan`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `komponen` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `status` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `masa_respons_ms` |  |  | integer |  |  | Tidak |  |  | dalaman |
| `mesej` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `butiran` |  |  | json |  |  | Tidak |  |  | dalaman |
| `dicatat_pada` |  |  | timestamp without time zone |  |  | Ya |  |  | dalaman |

## `adm_kod_rujukan`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `jenis_kod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `kod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `keterangan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `kod_induk` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `susunan` |  |  | integer |  |  | Ya |  |  | dalaman |
| `status` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `tarikh_mula` |  |  | date |  |  | Ya |  |  | dalaman |
| `tarikh_tamat` |  |  | date |  |  | Tidak |  |  | dalaman |
| `versi` |  |  | integer |  |  | Ya |  |  | dalaman |
| `justifikasi` |  |  | text |  |  | Tidak |  |  | dalaman |
| `dikemas_kini_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `adm_kod_rujukan_sejarah`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `id_kod_rujukan` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `versi` |  |  | integer |  |  | Ya |  |  | dalaman |
| `data` |  |  | json |  |  | Ya |  |  | dalaman |
| `diubah_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Ya |  |  | dalaman |

## `adm_konfig_kelulusan`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `jenis_konfig` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `kunci` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nilai_sebelum` |  |  | json |  |  | Tidak |  |  | dalaman |
| `nilai_selepas` |  |  | json |  |  | Tidak |  |  | dalaman |
| `nilai_rahsia_tersulit` |  |  | text |  |  | Tidak |  |  | dalaman |
| `justifikasi` |  |  | text |  |  | Ya |  |  | dalaman |
| `id_pemohon` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `alamat_ip_pemohon` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `id_pelulus` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `catatan_pelulus` |  |  | text |  |  | Tidak |  |  | dalaman |
| `status` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `id_instans_aliran` |  | MOD-WFE instance; null = built-in two-person approval | character varying |  |  | Tidak |  |  | dalaman |
| `diputuskan_pada` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `adm_log_integrasi`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `kod_integrasi` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `id_korelasi` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `tarikh_masa` |  |  | timestamp without time zone |  |  | Ya |  |  | dalaman |
| `arah` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `operasi` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `berjaya` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `status_http` |  |  | smallint |  |  | Tidak |  |  | dalaman |
| `tempoh_ms` |  |  | integer |  |  | Tidak |  |  | dalaman |
| `bilangan_rekod` |  |  | integer |  |  | Tidak |  |  | dalaman |
| `mesej` |  |  | text |  |  | Tidak |  |  | dalaman |
| `konteks` |  |  | json |  |  | Tidak |  |  | dalaman |

## `adm_log_ralat`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `id_korelasi` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `tarikh_masa` |  |  | timestamp without time zone |  |  | Ya |  |  | dalaman |
| `aras_keterukan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `saluran` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `modul` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `kod_ralat` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `mesej` |  |  | text |  |  | Ya |  |  | dalaman |
| `jejak_tindanan` |  |  | text |  |  | Tidak |  |  | dalaman |
| `id_pengguna` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `alamat_ip` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `konteks` |  |  | json |  |  | Tidak |  |  | dalaman |

## `adm_parameter`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `kunci` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `modul` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nilai` |  | JSON-encoded value | text |  |  | Tidak |  |  | dalaman |
| `justifikasi` |  |  | text |  |  | Tidak |  |  | dalaman |
| `dikemas_kini_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `adm_rahsia`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `kunci` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nilai_tersulit` |  |  | text |  |  | Ya |  |  | dalaman |
| `keterangan` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `dikemas_kini_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `adm_sinonim_carian`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `istilah` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `sinonim` |  |  | json |  |  | Ya |  |  | dalaman |
| `dikemas_kini_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `adm_templat`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `kod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nama` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `jenis` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `format_output` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `kandungan` |  |  | text |  |  | Ya |  |  | dalaman |
| `medan` |  |  | json |  |  | Tidak |  |  | dalaman |
| `versi` |  |  | integer |  |  | Ya |  |  | dalaman |
| `status` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `justifikasi` |  |  | text |  |  | Tidak |  |  | dalaman |
| `dikemas_kini_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `adm_templat_versi`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `id_templat` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `versi` |  |  | integer |  |  | Ya |  |  | dalaman |
| `format_output` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `kandungan` |  |  | text |  |  | Ya |  |  | dalaman |
| `medan` |  |  | json |  |  | Tidak |  |  | dalaman |
| `dicipta_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Ya |  |  | dalaman |

## `adm_tugasan_berjadual`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `kod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nama` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `keterangan` |  |  | text |  |  | Tidak |  |  | dalaman |
| `kategori` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `arahan` |  | artisan command name | character varying |  |  | Ya |  |  | dalaman |
| `argumen` |  |  | json |  |  | Tidak |  |  | dalaman |
| `jenis_jadual` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `ungkapan_cron` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `selang_minit` |  |  | integer |  |  | Tidak |  |  | dalaman |
| `aktif` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `cubaan_semula` |  |  | smallint |  |  | Ya |  |  | dalaman |
| `tempoh_tamat_masa_saat` |  |  | integer |  |  | Ya |  |  | dalaman |
| `toleransi_lewat_minit` |  |  | integer |  |  | Tidak |  |  | dalaman |
| `sumber` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `kunci_sistem` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `terakhir_mula_pada` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `terakhir_status` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `justifikasi` |  |  | text |  |  | Tidak |  |  | dalaman |
| `dikemas_kini_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `adm_tugasan_larian`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `id_tugasan` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `pencetus` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `id_pengguna` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `cubaan` |  |  | smallint |  |  | Ya |  |  | dalaman |
| `tarikh_mula` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `tarikh_tamat` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `tempoh_saat` |  |  | numeric |  |  | Tidak |  |  | dalaman |
| `status` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `bilangan_rekod` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `mesej_ralat` |  |  | text |  |  | Tidak |  |  | dalaman |
| `output` |  |  | text |  |  | Tidak |  |  | dalaman |
| `id_korelasi` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `aduan`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `lot_id` |  |  | uuid |  |  | Tidak |  |  | dalaman |
| `kategori` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `tarikh_aduan` |  |  | date |  |  | Tidak |  |  | dalaman |
| `status` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `id_pegawai` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `tindakan_susulan` |  |  | text |  |  | Tidak |  |  | dalaman |
| `klasifikasi` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `deleted_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `koordinat` |  |  | geometry(Point,4326) |  |  | Tidak |  |  | dalaman |

## `amaran_keselamatan`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `jenis` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `user_id` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `alamat_ip` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `butiran` |  |  | json |  |  | Tidak |  |  | dalaman |
| `disemak_pada` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `disemak_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Ya |  |  | dalaman |

## `audit_arkib`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `no_urutan` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `cincangan_sebelum` |  |  | character |  |  | Ya |  |  | dalaman |
| `cincangan_rekod` |  |  | character |  |  | Ya |  |  | dalaman |
| `tarikh_masa_utc` |  |  | timestamp with time zone |  |  | Ya |  |  | dalaman |
| `tarikh_masa_tempatan` |  |  | timestamp without time zone |  |  | Ya |  |  | dalaman |
| `id_pengguna` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `pengecam_pengguna` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `peranan_semasa` |  |  | json |  |  | Ya |  |  | dalaman |
| `id_sesi` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `alamat_ip` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `user_agent` |  |  | text |  |  | Tidak |  |  | dalaman |
| `jenis_peranti` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `lokasi_anggaran` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `saluran_capaian` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `modul` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nama_entiti` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `id_rekod` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `jenis_tindakan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `status_transaksi` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `sebab_perubahan` |  |  | text |  |  | Tidak |  |  | dalaman |
| `id_korelasi` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `klasifikasi` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `meta` |  |  | json |  |  | Tidak |  |  | dalaman |
| `perubahan` |  |  | json |  |  | Tidak |  |  | dalaman |
| `diarkib_pada` |  |  | timestamp with time zone |  |  | Ya |  |  | dalaman |
| `id_kelompok_arkib` |  |  | character varying |  |  | Ya |  |  | dalaman |

## `audit_eksport`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `id_pengguna` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `pengecam_pengguna` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `kriteria_carian` |  |  | json |  |  | Ya |  |  | dalaman |
| `format` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `bilangan_rekod` |  |  | integer |  |  | Ya |  |  | dalaman |
| `checksum` |  |  | character |  |  | Ya |  |  | dalaman |
| `checksum_data` |  |  | character |  |  | Ya |  |  | dalaman |
| `nama_fail` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `saiz_bait` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `tarikh_masa_utc` |  |  | timestamp with time zone |  |  | Ya |  |  | dalaman |
| `tarikh_masa_tempatan` |  |  | timestamp without time zone |  |  | Ya |  |  | dalaman |

## `audit_integriti_semakan`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `dimulakan_pada` |  |  | timestamp with time zone |  |  | Ya |  |  | dalaman |
| `selesai_pada` |  |  | timestamp with time zone |  |  | Ya |  |  | dalaman |
| `status` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `bilangan_disemak` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `no_urutan_terakhir` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `no_urutan_rosak` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `butiran` |  |  | text |  |  | Tidak |  |  | dalaman |
| `dicetuskan_oleh` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `id_pengguna` |  |  | bigint |  |  | Tidak |  |  | dalaman |

## `audit_perubahan_medan`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `id_audit` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `nama_medan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nilai_asal` |  |  | text |  |  | Tidak |  |  | dalaman |
| `nilai_baharu` |  |  | text |  |  | Tidak |  |  | dalaman |
| `sensitif_ditapis` |  |  | boolean |  |  | Ya |  |  | dalaman |

## `audit_rekod`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `no_urutan` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `cincangan_sebelum` |  |  | character |  |  | Ya |  |  | dalaman |
| `cincangan_rekod` |  |  | character |  |  | Ya |  |  | dalaman |
| `tarikh_masa_utc` |  |  | timestamp with time zone |  |  | Ya |  |  | dalaman |
| `tarikh_masa_tempatan` |  |  | timestamp without time zone |  |  | Ya |  |  | dalaman |
| `id_pengguna` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `pengecam_pengguna` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `peranan_semasa` |  |  | json |  |  | Ya |  |  | dalaman |
| `id_sesi` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `alamat_ip` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `user_agent` |  |  | text |  |  | Tidak |  |  | dalaman |
| `jenis_peranti` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `lokasi_anggaran` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `saluran_capaian` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `modul` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nama_entiti` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `id_rekod` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `jenis_tindakan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `status_transaksi` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `sebab_perubahan` |  |  | text |  |  | Tidak |  |  | dalaman |
| `id_korelasi` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `klasifikasi` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `meta` |  |  | json |  |  | Tidak |  |  | dalaman |

## `audit_retensi_polisi`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `tempoh_dalam_talian_bulan` |  |  | smallint |  |  | Ya |  |  | dalaman |
| `tempoh_arkib_tahun` |  |  | smallint |  |  | Tidak |  |  | dalaman |
| `catatan` |  |  | text |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `baris_import`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `id_sesi` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `no_baris` |  |  | integer |  |  | Ya |  |  | dalaman |
| `data` |  |  | jsonb |  |  | Ya |  |  | dalaman |
| `status` |  | sah \| ditolak \| diabaikan \| dikomit | character varying |  |  | Ya |  |  | dalaman |
| `id_rekod` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `cadangan_duplikasi`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `entiti` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `id_rekod_a` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `id_rekod_b` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `kriteria_padanan` |  |  | jsonb |  |  | Ya |  |  | dalaman |
| `skor_padanan` |  |  | numeric |  |  | Ya |  |  | dalaman |
| `keputusan` |  | menunggu \| sah \| gabung \| tolak | character varying |  |  | Ya |  |  | dalaman |
| `catatan_keputusan` |  |  | text |  |  | Tidak |  |  | dalaman |
| `id_data_steward` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `tarikh_keputusan` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `cukai`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | uuid |  |  | Ya | Bahagian Kewangan | Pegawai Kewangan (Bahagian Kewangan) | dalaman |
| `lot_id` |  |  | uuid |  |  | Ya | Bahagian Kewangan | Pegawai Kewangan (Bahagian Kewangan) | dalaman |
| `jenis` |  | tanah \| petak \| taksiran_pbt | character varying |  |  | Ya | Bahagian Kewangan | Pegawai Kewangan (Bahagian Kewangan) | dalaman |
| `no_bil` |  |  | character varying |  |  | Tidak | Bahagian Kewangan | Pegawai Kewangan (Bahagian Kewangan) | dalaman |
| `jumlah` |  |  | numeric |  |  | Tidak | Bahagian Kewangan | Pegawai Kewangan (Bahagian Kewangan) | dalaman |
| `tarikh_akhir` |  |  | date |  |  | Tidak | Bahagian Kewangan | Pegawai Kewangan (Bahagian Kewangan) | dalaman |
| `status_bayaran` |  |  | character varying |  |  | Ya | Bahagian Kewangan | Pegawai Kewangan (Bahagian Kewangan) | dalaman |
| `kaedah_bayaran` |  |  | character varying |  |  | Tidak | Bahagian Kewangan | Pegawai Kewangan (Bahagian Kewangan) | dalaman |
| `klasifikasi` |  |  | character varying |  |  | Ya | Bahagian Kewangan | Pegawai Kewangan (Bahagian Kewangan) | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak | Bahagian Kewangan | Pegawai Kewangan (Bahagian Kewangan) | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak | Bahagian Kewangan | Pegawai Kewangan (Bahagian Kewangan) | dalaman |
| `deleted_at` |  |  | timestamp without time zone |  |  | Tidak | Bahagian Kewangan | Pegawai Kewangan (Bahagian Kewangan) | dalaman |

## `daftar_stewardship_data`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `komponen_data` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `entiti_berkaitan` |  | Jadual, dipisah koma | character varying |  |  | Ya |  |  | dalaman |
| `jawatan_data_owner` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `bahagian_data_owner` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `id_data_owner` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `jawatan_data_steward` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `bahagian_data_steward` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `id_data_steward` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `bahagian_pemilik` |  | Hadkan kepada bahagian lot (hartanah \| wakaf \| baitulmal) | character varying |  |  | Tidak |  |  | dalaman |
| `kod_daerah` |  | Hadkan kepada daerah (steward daerah) | character varying |  |  | Tidak |  |  | dalaman |
| `peranan_tanggungjawab` |  |  | text |  |  | Ya |  |  | dalaman |
| `tarikh_berkuat_kuasa` |  |  | date |  |  | Ya |  |  | dalaman |
| `status` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `data_spatial`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya | Bahagian Pengurusan Hartanah | GIS Specialist | dalaman |
| `lot_id` |  |  | uuid |  |  | Ya | Bahagian Pengurusan Hartanah | GIS Specialist | dalaman |
| `srid_asal` | Sistem Unjuran Asal | SRID fail sumber sebelum ditukar ke 4326. | integer | Integer | 4326, 4742, 3375, 3380, 3168, 4245 | Ya | Bahagian Pengurusan Hartanah | GIS Specialist | dalaman |
| `keluasan_dikira` | Keluasan Dikira | Keluasan poligon dikira atas elipsoid (m²). | numeric | Perpuluhan |  | Tidak | Bahagian Pengurusan Hartanah | GIS Specialist | dalaman |
| `sumber_data` | Sumber Data Spatial | Asal geometri. | character varying |  | jupem, ptg, pbt, juruukur, lakaran, satelit, sintetik | Ya | Bahagian Pengurusan Hartanah | GIS Specialist | dalaman |
| `rujukan_fail_sumber` |  |  | character varying |  |  | Tidak | Bahagian Pengurusan Hartanah | GIS Specialist | dalaman |
| `tarikh_perolehan_data` |  |  | date |  |  | Tidak | Bahagian Pengurusan Hartanah | GIS Specialist | dalaman |
| `status_pengesahan` | Status Pengesahan | Status pengesahan geometri (SRS-PRO-10). | character varying |  | belum_disahkan, disahkan_juruukur, disahkan_ptg, bertindih | Ya | Bahagian Pengurusan Hartanah | GIS Specialist | dalaman |
| `id_pegawai_pengesah` | Pegawai Pengesah | Pegawai yang mengesahkan geometri. | bigint | ID pengguna |  | Tidak | Bahagian Pengurusan Hartanah | GIS Specialist | dalaman |
| `tarikh_pengesahan` |  |  | timestamp without time zone |  |  | Tidak | Bahagian Pengurusan Hartanah | GIS Specialist | dalaman |
| `catatan_pertindihan` |  |  | text |  |  | Tidak | Bahagian Pengurusan Hartanah | GIS Specialist | dalaman |
| `semasa` |  |  | boolean |  |  | Ya | Bahagian Pengurusan Hartanah | GIS Specialist | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak | Bahagian Pengurusan Hartanah | GIS Specialist | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak | Bahagian Pengurusan Hartanah | GIS Specialist | dalaman |
| `geometri` | Geometri Poligon | Sempadan lot sebagai MULTIPOLYGON WGS84. | geometry(MultiPolygon,4326) | MULTIPOLYGON 4326 |  | Ya | Bahagian Pengurusan Hartanah | GIS Specialist | dalaman |
| `versi` |  |  | integer |  |  | Ya | Bahagian Pengurusan Hartanah | GIS Specialist | dalaman |
| `format_asal` |  | geojson \| wkt \| kml \| shp | character varying |  |  | Tidak | Bahagian Pengurusan Hartanah | GIS Specialist | dalaman |
| `id_dokumen_sumber` |  | Fail asal dalam DMS | character varying |  |  | Tidak | Bahagian Pengurusan Hartanah | GIS Specialist | dalaman |
| `peratus_beza_keluasan` |  | Beza keluasan dikira berbanding keluasan diisytihar (%) | numeric |  |  | Tidak | Bahagian Pengurusan Hartanah | GIS Specialist | dalaman |
| `id_pemuat_naik` |  |  | bigint |  |  | Tidak | Bahagian Pengurusan Hartanah | GIS Specialist | dalaman |
| `catatan_pengesahan` |  |  | text |  |  | Tidak | Bahagian Pengurusan Hartanah | GIS Specialist | dalaman |
| `sumber_sistem` |  |  | character varying |  |  | Tidak | Bahagian Pengurusan Hartanah | GIS Specialist | dalaman |
| `kunci_asal` |  |  | character varying |  |  | Tidak | Bahagian Pengurusan Hartanah | GIS Specialist | dalaman |

## `dokumen`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `no_rujukan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `kategori_id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `modul_sumber` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `jenis_entiti_induk` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `id_rekod_induk` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `pautan_induk` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `tajuk` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nama_fail` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `jenis_mime` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `sambungan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `saiz_bait` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `cincangan_fail` |  |  | character |  |  | Ya |  |  | dalaman |
| `klasifikasi_keselamatan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `status` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `tarikh_dokumen` |  |  | date |  |  | Tidak |  |  | dalaman |
| `tarikh_luput` |  |  | date |  |  | Tidak |  |  | dalaman |
| `tahun` |  |  | smallint |  |  | Tidak |  |  | dalaman |
| `kod_daerah` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `kod_mukim` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `pemilik_proses_id` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `id_pencipta` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `versi_semasa` |  |  | integer |  |  | Ya |  |  | dalaman |
| `kata_kunci` |  |  | jsonb |  |  | Ya |  |  | dalaman |
| `metadata` |  |  | jsonb |  |  | Ya |  |  | dalaman |
| `teks_kandungan` |  |  | text |  |  | Tidak |  |  | dalaman |
| `status_indeks` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `kaedah_indeks` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `amaran_luput_pada` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `tarikh_arkib` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `tarikh_lupus` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `deleted_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `carian` |  |  | tsvector |  |  | Tidak |  |  | dalaman |

## `dokumen_arkib`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `dokumen_id` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `tarikh_arkib` |  |  | timestamp without time zone |  |  | Ya |  |  | dalaman |
| `tarikh_layak_lupus` |  |  | date |  |  | Tidak |  |  | dalaman |
| `sebab` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `diarkib_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `dokumen_imbasan`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `dokumen_id` |  |  | uuid |  |  | Tidak |  |  | dalaman |
| `kuarantin_id` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `nama_fail` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `cincangan_fail` |  |  | character |  |  | Ya |  |  | dalaman |
| `enjin` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `versi_tandatangan` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `keputusan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nama_ancaman` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `tarikh_imbasan` |  |  | timestamp without time zone |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `dokumen_kategori`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `kod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nama` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `keterangan` |  |  | text |  |  | Tidak |  |  | dalaman |
| `jenis_fail_dibenarkan` |  |  | jsonb |  |  | Tidak |  |  | dalaman |
| `had_saiz_mb` |  |  | integer |  |  | Tidak |  |  | dalaman |
| `tempoh_retensi_bulan` |  |  | integer |  |  | Tidak |  |  | dalaman |
| `tempoh_arkib_bulan` |  |  | integer |  |  | Tidak |  |  | dalaman |
| `tindakan_selepas_arkib` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `tempoh_amaran_luput_hari` |  |  | integer |  |  | Tidak |  |  | dalaman |
| `klasifikasi_lalai` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `aktif` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `dokumen_kebenaran`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `dokumen_id` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `jenis_penerima` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `penerima` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `diberi_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `tamat_pada` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `dokumen_kuarantin`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `nama_fail` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `saiz_bait` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `cincangan_fail` |  |  | character |  |  | Ya |  |  | dalaman |
| `kunci_storan` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `kunci_data_bersulit` |  |  | text |  |  | Tidak |  |  | dalaman |
| `id_kunci_induk` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `nama_ancaman` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `jenis_entiti_induk` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `id_rekod_induk` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `dimuat_naik_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `dimusnahkan_pada` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `dokumen_medan_metadata`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `kategori_id` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `kod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `label` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `jenis` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `pilihan` |  |  | jsonb |  |  | Tidak |  |  | dalaman |
| `wajib` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `aktif` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `susunan` |  |  | integer |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `dokumen_pelupusan`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `dokumen_id` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `justifikasi` |  |  | text |  |  | Ya |  |  | dalaman |
| `dimohon_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `id_aliran_kerja` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `status` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `diputuskan_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `catatan_keputusan` |  |  | text |  |  | Tidak |  |  | dalaman |
| `tarikh_keputusan` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `dokumen_versi`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `dokumen_id` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `no_versi` |  |  | integer |  |  | Ya |  |  | dalaman |
| `nama_fail` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `jenis_mime` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `saiz_bait` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `cincangan_fail` |  |  | character |  |  | Ya |  |  | dalaman |
| `kunci_storan` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `kunci_data_bersulit` |  |  | text |  |  | Tidak |  |  | dalaman |
| `id_kunci_induk` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `algoritma` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `dimuat_naik_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `catatan` |  |  | text |  |  | Tidak |  |  | dalaman |
| `metadata_snapshot` |  |  | jsonb |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `jadual_retensi`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `kategori_rekod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `keterangan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `entiti` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `syarat` |  | Contoh {"status_lot": ["tidak_aktif","digabung"]} | jsonb |  |  | Ya |  |  | dalaman |
| `tempoh_retensi_tahun` |  |  | smallint |  |  | Ya |  |  | dalaman |
| `tindakan_selepas_retensi` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `rujukan_dasar_mais` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `memerlukan_kelulusan` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `id_pelulus` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `aktif` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `kamus_data`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `nama_jadual` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nama_medan` |  | NULL = metadata jadual | character varying |  |  | Tidak |  |  | dalaman |
| `nama_perniagaan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `definisi` |  |  | text |  |  | Tidak |  |  | dalaman |
| `format` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `nilai_dibenarkan` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `wajib` |  |  | boolean |  |  | Tidak |  |  | dalaman |
| `data_owner` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `data_steward` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `klasifikasi` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `kata_laluan_sejarah`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `user_id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `kata_laluan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Ya |  |  | dalaman |

## `kebenaran`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `kunci` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `modul` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `fungsi` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `tindakan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `label` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `kes_mahkamah`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | uuid |  |  | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah | rahsia |
| `no_kes` |  |  | character varying |  |  | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah | rahsia |
| `jenis` |  |  | character varying |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | rahsia |
| `mahkamah` |  |  | character varying |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | rahsia |
| `status` |  |  | character varying |  |  | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah | rahsia |
| `kos_guaman` |  |  | numeric |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | rahsia |
| `klasifikasi` |  |  | character varying |  |  | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah | rahsia |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | rahsia |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | rahsia |
| `deleted_at` |  |  | timestamp without time zone |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | rahsia |

## `kes_mahkamah_lot`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `kes_mahkamah_id` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `lot_id` |  |  | uuid |  |  | Ya |  |  | dalaman |

## `klasifikasi_data`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `komponen_data` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `entiti` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `aras_kerahsiaan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `keperluan_encryption` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `peranan_dibenarkan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `kebenaran_diperlukan` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `tarikh_semakan` |  |  | date |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `kontrak_pajakan`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | uuid |  |  | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah | sulit |
| `lot_id` |  |  | uuid |  |  | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah | sulit |
| `penyewa` |  |  | character varying |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | sulit |
| `tarikh_mula` |  |  | date |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | sulit |
| `tarikh_tamat` |  |  | date |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | sulit |
| `kadar` |  |  | numeric |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | sulit |
| `deposit` |  |  | numeric |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | sulit |
| `terma` |  |  | text |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | sulit |
| `status` |  |  | character varying |  |  | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah | sulit |
| `klasifikasi` |  |  | character varying |  |  | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah | sulit |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | sulit |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | sulit |
| `deleted_at` |  |  | timestamp without time zone |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | sulit |

## `kunci_api_pro`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `id_token` |  | personal_access_tokens.id | bigint |  |  | Ya |  |  | dalaman |
| `id_pemilik` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `sistem_pemanggil` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `skop` |  |  | jsonb |  |  | Ya |  |  | dalaman |
| `had_kadar_seminit` |  |  | integer |  |  | Ya |  |  | dalaman |
| `tamat_pada` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `dibatalkan_pada` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `dibatalkan_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `log_akses_profil`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `lot_id` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `id_pengguna` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `jenis_capaian` |  | lihat \| eksport \| cetak \| api | character varying |  |  | Ya |  |  | dalaman |
| `saluran` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `alamat_ip` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `butiran` |  |  | jsonb |  |  | Tidak |  |  | dalaman |
| `tarikh_masa` |  |  | timestamp without time zone |  |  | Ya |  |  | dalaman |

## `log_log_masuk`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `user_id` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `pengecam` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `kaedah` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `berjaya` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `sebab` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `alamat_ip` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `user_agent` |  |  | text |  |  | Tidak |  |  | dalaman |
| `peranti` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `pelayar` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `platform` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `sidik_peranti` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `lokasi` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `negara` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `mencurigakan` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Ya |  |  | dalaman |

## `log_penggunaan_api`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `id_token` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `sistem_pemanggil` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `kaedah` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `laluan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `kod_status` |  |  | smallint |  |  | Ya |  |  | dalaman |
| `alamat_ip` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `tarikh_masa` |  |  | timestamp without time zone |  |  | Ya |  |  | dalaman |

## `lot_tanah`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` | ID Lot | Pengecam unik rekod lot (UUID). | uuid | UUID |  | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `no_lot` | No. Lot | Nombor lot atau PT seperti dalam hak milik / pelan akui. | character varying | "Lot 1234" atau "PT 1234" |  | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `kod_mukim` | Mukim | Kod mukim MOD-REF (6 digit). | character varying | 6 digit | ref_mukim | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `kod_daerah` | Daerah | Kod daerah MOD-REF (4 digit), mesti sepadan dengan mukim. | character varying | 4 digit | ref_daerah | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `kod_negeri` | Negeri | Kod negeri MOD-REF. | character varying | 2 digit | ref_negeri | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `keluasan` | Keluasan | Keluasan lot seperti diisytihar dalam hak milik. | numeric | Perpuluhan (16,4) | > 0 | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `unit_keluasan` | Unit Keluasan | Unit bagi keluasan. | character varying |  | m2, hektar, ekar | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `kategori_guna_tanah` | Kategori Guna Tanah | Kategori kegunaan tanah (KTN s.52). | character varying |  | ref_kategori_guna_tanah | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `zoning` | Zoning | Zoning rancangan tempatan. | character varying | Teks ≤ 50 |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `syarat_nyata` | Syarat Nyata | Syarat nyata seperti dalam dokumen hak milik. | text | Teks |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | sulit |
| `bahagian_pemilik` | Bahagian Pemilik MAIS | Bahagian MAIS yang memiliki/mengurus lot. | character varying |  | hartanah, wakaf, baitulmal | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `status_lot` | Status Lot | Kitaran hayat rekod lot. | character varying |  | draf, aktif, tidak_aktif, digabung, arkib | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `klasifikasi` | Klasifikasi | Aras kerahsiaan rekod (SRS §4.2). | character varying |  | awam, dalaman, sulit, rahsia | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `id_data_owner` | Data Owner | Pegawai pemilik data (SRS §4.3). | bigint | ID pengguna |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `id_data_steward` | Data Steward | Pegawai pengawas data yang bertanggungjawab atas ketepatan rekod. | bigint | ID pengguna |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `versi_rekod` | Versi Rekod | Nombor versi semasa rekod induk (SRS-PRO-22). | integer | Integer |  | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `deleted_at` |  |  | timestamp without time zone |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `koordinat_pusat` | Koordinat Pusat | Titik di dalam poligon lot (WGS84). | geometry(Point,4326) | POINT 4326 |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `no_lot_normal` |  | No. lot dinormalkan (huruf besar, tanpa tanda baca) untuk padanan duplikasi | character varying |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `sintetik` |  | Data contoh sintetik — bukan rekod sebenar | boolean |  |  | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `sumber_sistem` | Sistem Sumber | Salasilah data: sistem asal rekod (SRS-DAT-08). | character varying |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `fail_asal` |  | Salasilah: jadual / fail asal | character varying |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `kunci_asal` | Kunci Asal | Salasilah data: kunci rekod dalam sistem sumber. | character varying |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `id_migrasi_larian` |  |  | uuid |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `id_sesi_import` |  |  | uuid |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `id_lot_gabung` |  | Lot induk apabila rekod ini digabungkan (duplikasi) | uuid |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `status_sebelum_arkib` |  |  | character varying |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `diarkib_pada` |  |  | timestamp without time zone |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `tag` |  |  | jsonb |  |  | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `catatan` |  |  | text |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |
| `id_pencipta` |  |  | bigint |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | dalaman |

## `migrasi_larian`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `jenis` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `sumber_sistem` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `parameter` |  |  | json |  |  | Tidak |  |  | dalaman |
| `status` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `bilangan_diterima` |  |  | integer |  |  | Ya |  |  | dalaman |
| `bilangan_ditolak` |  |  | integer |  |  | Ya |  |  | dalaman |
| `catatan` |  |  | text |  |  | Tidak |  |  | dalaman |
| `mula_pada` |  |  | timestamp without time zone |  |  | Ya |  |  | dalaman |
| `tamat_pada` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `notifications`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `type` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `notifiable_type` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `notifiable_id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `data` |  |  | text |  |  | Ya |  |  | dalaman |
| `read_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `ntf_eskalasi_log`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `status_pengguna_id` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `mesej_id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `penerima_asal_id` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `penyelia_id` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `mesej_eskalasi_id` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `tarikh_eskalasi` |  |  | timestamp without time zone |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `ntf_jenis`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `kod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nama` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `modul` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `keutamaan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `wajib` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `saluran_lalai` |  |  | jsonb |  |  | Ya |  |  | dalaman |
| `kekerapan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `tempoh_eskalasi_minit` |  |  | integer |  |  | Tidak |  |  | dalaman |
| `tempoh_pendiaman_minit` |  |  | integer |  |  | Ya |  |  | dalaman |
| `hari_peringatan` |  |  | jsonb |  |  | Tidak |  |  | dalaman |
| `medan` |  |  | jsonb |  |  | Ya |  |  | dalaman |
| `medan_sensitif` |  |  | jsonb |  |  | Ya |  |  | dalaman |
| `aktif` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `ntf_keutamaan_pengguna`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `pengguna_id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `jenis_id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `aktif` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `saluran` |  |  | jsonb |  |  | Tidak |  |  | dalaman |
| `ringkasan` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `ntf_mesej`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `jenis_id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `templat_id` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `keutamaan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `modul` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `subjek` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `kandungan` |  |  | text |  |  | Ya |  |  | dalaman |
| `pautan` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `data` |  |  | jsonb |  |  | Ya |  |  | dalaman |
| `kunci_dedupe` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `dicipta_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `ntf_penerima_peraturan`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `jenis_id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `sasaran` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nilai` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `aktif` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `ntf_penghantaran`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `mesej_id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `penerima_id` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `saluran` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `status` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `bilangan_cubaan` |  |  | smallint |  |  | Ya |  |  | dalaman |
| `kod_ralat` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `mesej_ralat` |  |  | text |  |  | Tidak |  |  | dalaman |
| `ringkasan` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `tarikh_beratur` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `tarikh_hantar` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `tarikh_terima` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `tarikh_baca` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `tarikh_gagal` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `ntf_status_pengguna`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `mesej_id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `pengguna_id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `dibaca_pada` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `diarkib_pada` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `dipadam_pada` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `dieskalasi_pada` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `ntf_templat`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `jenis_id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `versi` |  |  | integer |  |  | Ya |  |  | dalaman |
| `subjek` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `kandungan` |  |  | text |  |  | Ya |  |  | dalaman |
| `aktif` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `catatan` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `dicipta_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `pemetaan_import`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `entiti_sasaran` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nama` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `pemetaan` |  | {"lajur dalam fail": "medan sasaran"} | jsonb |  |  | Ya |  |  | dalaman |
| `id_pencipta` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `pemetaan_tuntutan`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `jenis_tuntutan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nilai` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `peranan_id` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `skop_jenis` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `skop_nilai` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `aktif` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `keutamaan` |  |  | integer |  |  | Ya |  |  | dalaman |
| `keterangan` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `pengguna_peranan`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `user_id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `peranan_id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `sumber` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `diberi_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `pengguna_skop`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `user_id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `jenis_skop` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nilai_skop` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `sumber` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `penilaian`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | uuid |  |  | Ya |  |  | rahsia |
| `lot_id` |  |  | uuid |  |  | Ya |  |  | rahsia |
| `tarikh_penilaian` |  |  | date |  |  | Ya |  |  | rahsia |
| `nilai_pasaran` | Nilai Pasaran | Nilai pasaran lot daripada penilaian. | numeric | RM |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah | rahsia |
| `nilai_setara` |  |  | numeric |  |  | Tidak |  |  | rahsia |
| `kaedah` |  |  | character varying |  |  | Tidak |  |  | rahsia |
| `penilai` |  |  | character varying |  |  | Tidak |  |  | rahsia |
| `versi` |  |  | integer |  |  | Ya |  |  | rahsia |
| `klasifikasi` |  |  | character varying |  |  | Ya |  |  | rahsia |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | rahsia |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | rahsia |
| `deleted_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | rahsia |

## `peranan`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `kod` |  |  | character varying |  |  | Ya | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `nama` |  |  | character varying |  |  | Ya | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `kategori_rfp` |  |  | character varying |  |  | Tidak | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `keterangan` |  |  | text |  |  | Tidak | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `status` |  |  | character varying |  |  | Ya | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `boleh_padam` |  |  | boolean |  |  | Ya | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |

## `peranan_kebenaran`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `peranan_id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `kebenaran` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `modul` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `fungsi` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `tindakan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `peranan_skop`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `peranan_id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `jenis_skop` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nilai_skop` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `peraturan_kualiti_data`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `entiti` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `medan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `jenis_peraturan` |  | wajib \| format \| julat \| senarai \| panjang \| rujukan \| unik \| lengkap | character varying |  |  | Ya |  |  | dalaman |
| `ungkapan_peraturan` |  |  | jsonb |  |  | Ya |  |  | dalaman |
| `mesej_ralat` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `tahap_keterukan` |  | ralat (menghalang) \| amaran (dilaporkan sahaja) | character varying |  |  | Ya |  |  | dalaman |
| `status_aktif` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `polisi_keselamatan`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `kunci` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nilai` |  |  | json |  |  | Ya |  |  | dalaman |
| `dikemas_kini_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `ralat_import`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `id_sesi` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `no_baris` |  |  | integer |  |  | Ya |  |  | dalaman |
| `medan` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `nilai_diberi` |  |  | text |  |  | Tidak |  |  | dalaman |
| `kod_ralat` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `sebab_penolakan` |  |  | character varying |  |  | Ya |  |  | dalaman |

## `recert_keputusan`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `kitaran_id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `user_id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `peranan_id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `keputusan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `catatan` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `disemak_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `disemak_pada` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `recert_kitaran`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `nama` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `bahagian` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `tarikh_mula` |  |  | date |  |  | Ya |  |  | dalaman |
| `tarikh_akhir` |  |  | date |  |  | Ya |  |  | dalaman |
| `status` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `dicipta_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `ditutup_pada` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `ref_daerah`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `kod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nama` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `keterangan` |  |  | text |  |  | Tidak |  |  | dalaman |
| `susunan` |  |  | smallint |  |  | Ya |  |  | dalaman |
| `aktif` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `versi` |  |  | integer |  |  | Ya |  |  | dalaman |
| `sah_dari` |  |  | date |  |  | Ya |  |  | dalaman |
| `sah_hingga` |  |  | date |  |  | Tidak |  |  | dalaman |
| `sumber` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `sumber_url` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `negeri_kod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `kod_pendek` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `kod_disahkan` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `nama_lain` |  |  | json |  |  | Tidak |  |  | dalaman |
| `wikidata` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `osm_relation_id` |  |  | bigint |  |  | Tidak |  |  | dalaman |

## `ref_jenis_dokumen`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `kod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nama` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `keterangan` |  |  | text |  |  | Tidak |  |  | dalaman |
| `susunan` |  |  | smallint |  |  | Ya |  |  | dalaman |
| `aktif` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `versi` |  |  | integer |  |  | Ya |  |  | dalaman |
| `sah_dari` |  |  | date |  |  | Ya |  |  | dalaman |
| `sah_hingga` |  |  | date |  |  | Tidak |  |  | dalaman |
| `sumber` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `sumber_url` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `klasifikasi_lalai` |  |  | character varying |  |  | Tidak |  |  | dalaman |

## `ref_jenis_hak_milik`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `kod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nama` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `keterangan` |  |  | text |  |  | Tidak |  |  | dalaman |
| `susunan` |  |  | smallint |  |  | Ya |  |  | dalaman |
| `aktif` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `versi` |  |  | integer |  |  | Ya |  |  | dalaman |
| `sah_dari` |  |  | date |  |  | Ya |  |  | dalaman |
| `sah_hingga` |  |  | date |  |  | Tidak |  |  | dalaman |
| `sumber` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `sumber_url` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `singkatan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `peringkat` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `sementara` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `hak_milik` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `rujukan_undang` |  |  | character varying |  |  | Tidak |  |  | dalaman |

## `ref_kategori_aduan`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `kod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nama` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `keterangan` |  |  | text |  |  | Tidak |  |  | dalaman |
| `susunan` |  |  | smallint |  |  | Ya |  |  | dalaman |
| `aktif` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `versi` |  |  | integer |  |  | Ya |  |  | dalaman |
| `sah_dari` |  |  | date |  |  | Ya |  |  | dalaman |
| `sah_hingga` |  |  | date |  |  | Tidak |  |  | dalaman |
| `sumber` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `sumber_url` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `ref_kategori_guna_tanah`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `kod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nama` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `keterangan` |  |  | text |  |  | Tidak |  |  | dalaman |
| `susunan` |  |  | smallint |  |  | Ya |  |  | dalaman |
| `aktif` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `versi` |  |  | integer |  |  | Ya |  |  | dalaman |
| `sah_dari` |  |  | date |  |  | Ya |  |  | dalaman |
| `sah_hingga` |  |  | date |  |  | Tidak |  |  | dalaman |
| `sumber` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `sumber_url` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `rujukan_undang` |  |  | character varying |  |  | Tidak |  |  | dalaman |

## `ref_klasifikasi_data`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `kod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nama` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `keterangan` |  |  | text |  |  | Tidak |  |  | dalaman |
| `susunan` |  |  | smallint |  |  | Ya |  |  | dalaman |
| `aktif` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `versi` |  |  | integer |  |  | Ya |  |  | dalaman |
| `sah_dari` |  |  | date |  |  | Ya |  |  | dalaman |
| `sah_hingga` |  |  | date |  |  | Tidak |  |  | dalaman |
| `sumber` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `sumber_url` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `aras` |  |  | smallint |  |  | Ya |  |  | dalaman |

## `ref_mukim`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `kod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nama` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `keterangan` |  |  | text |  |  | Tidak |  |  | dalaman |
| `susunan` |  |  | smallint |  |  | Ya |  |  | dalaman |
| `aktif` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `versi` |  |  | integer |  |  | Ya |  |  | dalaman |
| `sah_dari` |  |  | date |  |  | Ya |  |  | dalaman |
| `sah_hingga` |  |  | date |  |  | Tidak |  |  | dalaman |
| `sumber` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `sumber_url` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `daerah_kod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `kod_pendek` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `kod_disahkan` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `nama_lain` |  |  | json |  |  | Tidak |  |  | dalaman |

## `ref_negeri`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `kod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nama` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `keterangan` |  |  | text |  |  | Tidak |  |  | dalaman |
| `susunan` |  |  | smallint |  |  | Ya |  |  | dalaman |
| `aktif` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `versi` |  |  | integer |  |  | Ya |  |  | dalaman |
| `sah_dari` |  |  | date |  |  | Ya |  |  | dalaman |
| `sah_hingga` |  |  | date |  |  | Tidak |  |  | dalaman |
| `sumber` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `sumber_url` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `kod_iso` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `jenis` |  |  | character varying |  |  | Ya |  |  | dalaman |

## `ref_pbt`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `kod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nama` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `keterangan` |  |  | text |  |  | Tidak |  |  | dalaman |
| `susunan` |  |  | smallint |  |  | Ya |  |  | dalaman |
| `aktif` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `versi` |  |  | integer |  |  | Ya |  |  | dalaman |
| `sah_dari` |  |  | date |  |  | Ya |  |  | dalaman |
| `sah_hingga` |  |  | date |  |  | Tidak |  |  | dalaman |
| `sumber` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `sumber_url` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `singkatan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `jenis` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `negeri_kod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `daerah_kod` |  |  | json |  |  | Tidak |  |  | dalaman |
| `wikidata` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `osm_relation_id` |  |  | bigint |  |  | Tidak |  |  | dalaman |

## `ref_sempadan`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `jenis` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `kod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nama` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `geometri` |  |  | geometry(MultiPolygon,4326) |  |  | Ya |  |  | dalaman |
| `anggaran` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `toleransi_permudah` |  |  | numeric |  |  | Tidak |  |  | dalaman |
| `luas_km2` |  |  | numeric |  |  | Tidak |  |  | dalaman |
| `sumber` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `sumber_url` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `lesen` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `osm_id` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `diambil_pada` |  |  | date |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `ref_tempat_ibadat`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `osm_jenis` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `osm_id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `nama` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `jenis` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `daerah_kod` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `mukim_kod` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `geometri` |  |  | geometry(Point,4326) |  |  | Ya |  |  | dalaman |
| `tag` |  |  | json |  |  | Tidak |  |  | dalaman |
| `sumber` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `lesen` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `diambil_pada` |  |  | date |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `rekod_arkib`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `entiti` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `id_rekod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `id_jadual_retensi` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `status_arkib` |  | dicadang \| menunggu_kelulusan \| diarkib \| ditolak \| dipulih | character varying |  |  | Ya |  |  | dalaman |
| `id_pemohon` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `id_pelulus_arkib` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `id_aliran_kerja` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `tarikh_arkib` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `lokasi_storan_arkib` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `tarikh_layak_lupus` |  |  | date |  |  | Tidak |  |  | dalaman |
| `id_pemulih` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `tarikh_pulih` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `sebab` |  |  | text |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `sesi`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `session_id` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `user_id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `kaedah_log_masuk` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `alamat_ip` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `user_agent` |  |  | text |  |  | Tidak |  |  | dalaman |
| `sidik_peranti` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `tarikh_mula` |  |  | timestamp without time zone |  |  | Ya |  |  | dalaman |
| `tarikh_aktiviti_akhir` |  |  | timestamp without time zone |  |  | Ya |  |  | dalaman |
| `tarikh_tamat` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `kaedah_tamat` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `ditamatkan_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |

## `sesi_import`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | uuid |  |  | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah (Bahagian Pengurusan Hartanah) | dalaman |
| `entiti_sasaran` |  |  | character varying |  |  | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah (Bahagian Pengurusan Hartanah) | dalaman |
| `nama_fail` |  |  | character varying |  |  | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah (Bahagian Pengurusan Hartanah) | dalaman |
| `format_fail` |  |  | character varying |  |  | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah (Bahagian Pengurusan Hartanah) | dalaman |
| `id_pengguna` |  |  | bigint |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah (Bahagian Pengurusan Hartanah) | dalaman |
| `pemetaan` |  |  | jsonb |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah (Bahagian Pengurusan Hartanah) | dalaman |
| `lajur_fail` |  |  | jsonb |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah (Bahagian Pengurusan Hartanah) | dalaman |
| `bil_baris_dibaca` |  |  | integer |  |  | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah (Bahagian Pengurusan Hartanah) | dalaman |
| `bil_sah` |  |  | integer |  |  | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah (Bahagian Pengurusan Hartanah) | dalaman |
| `bil_berjaya` |  |  | integer |  |  | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah (Bahagian Pengurusan Hartanah) | dalaman |
| `bil_ditolak` |  |  | integer |  |  | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah (Bahagian Pengurusan Hartanah) | dalaman |
| `bil_diabaikan` |  |  | integer |  |  | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah (Bahagian Pengurusan Hartanah) | dalaman |
| `lokasi_fail_asal` |  | ID dokumen DMS | character varying |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah (Bahagian Pengurusan Hartanah) | dalaman |
| `catatan_fail_asal` |  |  | character varying |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah (Bahagian Pengurusan Hartanah) | dalaman |
| `status_sesi` |  | pratonton \| dikomit \| dibatalkan | character varying |  |  | Ya | Bahagian Pengurusan Hartanah | Pegawai Hartanah (Bahagian Pengurusan Hartanah) | dalaman |
| `tarikh_komit` |  |  | timestamp without time zone |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah (Bahagian Pengurusan Hartanah) | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah (Bahagian Pengurusan Hartanah) | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak | Bahagian Pengurusan Hartanah | Pegawai Hartanah (Bahagian Pengurusan Hartanah) | dalaman |

## `settings`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `key` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `value` |  |  | text |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `staging_hak_milik`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `migrasi_larian_id` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `staging_lot_tanah_id` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `no_hak_milik` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `jenis_hak_milik_kod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `tarikh_daftar` |  |  | date |  |  | Tidak |  |  | dalaman |
| `tarikh_tamat` |  |  | date |  |  | Tidak |  |  | dalaman |
| `tempoh_pajakan_tahun` |  |  | smallint |  |  | Tidak |  |  | dalaman |
| `sekatan_kepentingan` |  |  | text |  |  | Tidak |  |  | dalaman |
| `lokasi_simpanan_fizikal` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `sintetik` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `sumber_sistem` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `fail_asal` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `kunci_asal` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `dimuat_pada` |  |  | timestamp without time zone |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `staging_lot_tanah`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `migrasi_larian_id` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `no_lot` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `jenis_lot` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `negeri_kod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `daerah_kod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `mukim_kod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `keluasan` |  |  | numeric |  |  | Ya |  |  | dalaman |
| `unit_keluasan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `kategori_guna_tanah_kod` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `zoning` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `syarat_nyata` |  |  | text |  |  | Tidak |  |  | dalaman |
| `sekatan_kepentingan` |  |  | text |  |  | Tidak |  |  | dalaman |
| `wakaf` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `jenis_wakaf` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `baitulmal` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `geometri` |  |  | geometry(Polygon,4326) |  |  | Tidak |  |  | dalaman |
| `sintetik` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `sumber_sistem` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `fail_asal` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `kunci_asal` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `dimuat_pada` |  |  | timestamp without time zone |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `staging_pemilik`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `migrasi_larian_id` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `staging_hak_milik_id` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `peranan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `jenis_pemilik` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nama` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `no_pengenalan` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `bahagian` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `sintetik` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `sumber_sistem` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `fail_asal` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `kunci_asal` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `dimuat_pada` |  |  | timestamp without time zone |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `status_akaun_log`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `user_id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `status_lama` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `status_baru` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `sebab` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `dilakukan_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Ya |  |  | dalaman |

## `tugasan_kualiti_data`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `entiti` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `id_rekod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `kod_isu` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `keterangan` |  |  | text |  |  | Ya |  |  | dalaman |
| `tahap_keterukan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `id_stewardship` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `id_data_steward` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `kod_daerah` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `status` |  | terbuka \| selesai \| ditolak | character varying |  |  | Ya |  |  | dalaman |
| `tindakan_pembetulan` |  |  | text |  |  | Tidak |  |  | dalaman |
| `diselesaikan_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `diselesaikan_pada` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `sumber` |  | semakan \| import \| laporan_berjadual | character varying |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `users`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `name` |  |  | character varying |  |  | Ya | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `email` |  |  | character varying |  |  | Ya | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `email_verified_at` |  |  | timestamp without time zone |  |  | Tidak | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `password` |  |  | character varying |  |  | Tidak | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `remember_token` |  |  | character varying |  |  | Tidak | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `role` |  |  | character varying |  |  | Ya | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `is_active` |  |  | boolean |  |  | Ya | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `oidc_sub` |  |  | character varying |  |  | Tidak | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `status_akaun` |  |  | character varying |  |  | Ya | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `jenis_pengesahan` |  |  | character varying |  |  | Ya | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `no_pekerja` |  |  | character varying |  |  | Tidak | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `bahagian` |  |  | character varying |  |  | Tidak | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `unit` |  |  | character varying |  |  | Tidak | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `jawatan` |  |  | character varying |  |  | Tidak | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `tarikh_mula_capaian` |  |  | date |  |  | Tidak | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `tarikh_tamat_capaian` |  |  | date |  |  | Tidak | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `tarikh_log_masuk_akhir` |  |  | timestamp without time zone |  |  | Tidak | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `tarikh_diaktifkan_semula` |  |  | timestamp without time zone |  |  | Tidak | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `mfa_didaftar` |  |  | boolean |  |  | Ya | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `kata_laluan_ditukar_pada` |  |  | timestamp without time zone |  |  | Tidak | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `wajib_tukar_kata_laluan` |  |  | boolean |  |  | Ya | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `cubaan_gagal` |  |  | smallint |  |  | Ya | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `cubaan_gagal_pertama` |  |  | timestamp without time zone |  |  | Tidak | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |
| `dikunci_hingga` |  |  | timestamp without time zone |  |  | Tidak | Bahagian ICT | Pentadbir Sistem (Bahagian ICT) | dalaman |

## `versi_rekod_induk`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `entiti` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `id_rekod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `no_versi` |  | Diberi apabila perubahan berkuat kuasa | integer |  |  | Tidak |  |  | dalaman |
| `nilai_sebelum` |  |  | jsonb |  |  | Tidak |  |  | dalaman |
| `nilai_selepas` |  |  | jsonb |  |  | Ya |  |  | dalaman |
| `kritikal` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `id_pengguna` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `justifikasi` |  |  | text |  |  | Tidak |  |  | dalaman |
| `id_pengesah_kedua` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `status_pengesahan` |  | berkuat_kuasa \| menunggu \| lulus \| tolak \| ditarik_balik \| dibatalkan | character varying |  |  | Ya |  |  | dalaman |
| `id_aliran_kerja` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `catatan_pengesahan` |  |  | text |  |  | Tidak |  |  | dalaman |
| `tarikh_pengesahan` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `vw_kualiti_data_rekod_tidak_lengkap`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `lot_id` |  |  | uuid |  |  | Tidak |  |  | dalaman |
| `no_lot` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `kod_mukim` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `kod_daerah` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `bahagian_pemilik` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `id_data_steward` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `medan_tidak_lengkap` |  |  | ARRAY |  |  | Tidak |  |  | dalaman |

## `vw_lot_spatial_belum_disahkan`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `lot_id` |  |  | uuid |  |  | Tidak |  |  | dalaman |
| `no_lot` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `kod_mukim` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `kod_daerah` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `bahagian_pemilik` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `id_data_steward` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `id_spatial` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `status_pengesahan` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `sumber_data` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `tarikh_perolehan_data` |  |  | date |  |  | Tidak |  |  | dalaman |

## `vw_profil_hartanah_360`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `lot_id` |  |  | uuid |  |  | Tidak |  |  | dalaman |
| `no_lot` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `kod_negeri` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `kod_daerah` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `kod_mukim` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `keluasan` |  |  | numeric |  |  | Tidak |  |  | dalaman |
| `unit_keluasan` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `kategori_guna_tanah` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `zoning` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `bahagian_pemilik` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `status_lot` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `klasifikasi` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `versi_rekod` |  |  | integer |  |  | Tidak |  |  | dalaman |
| `lat` |  |  | double precision |  |  | Tidak |  |  | dalaman |
| `lng` |  |  | double precision |  |  | Tidak |  |  | dalaman |
| `status_pengesahan_spatial` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `keluasan_dikira` |  |  | numeric |  |  | Tidak |  |  | dalaman |
| `sumber_data_spatial` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `bil_penilaian` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `bil_kontrak_pajakan` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `bil_cukai` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `bil_aduan` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `bil_kes_mahkamah` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `wf_cuti_umum`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `tarikh` |  |  | date |  |  | Ya |  |  | dalaman |
| `nama` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `negeri` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `jenis` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `sumber` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `catatan` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `wf_definisi`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `kod_aliran` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nama` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `keterangan` |  |  | text |  |  | Tidak |  |  | dalaman |
| `modul` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `jenis_transaksi` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `no_versi` |  |  | integer |  |  | Ya |  |  | dalaman |
| `status` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `templat` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `tarikh_berkuat_kuasa` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `id_pencipta` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `kaedah_cipta` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `id_asal` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `diminta_aktif_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `diminta_aktif_pada` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `diluluskan_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `diluluskan_pada` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `susun_atur` |  |  | jsonb |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `wf_definisi_log`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `id_definisi` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `id_pengguna` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `tindakan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `kaedah` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `arahan` |  |  | text |  |  | Tidak |  |  | dalaman |
| `butiran` |  |  | jsonb |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Ya |  |  | dalaman |

## `wf_eskalasi`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `id_peringkat` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `aras` |  |  | integer |  |  | Ya |  |  | dalaman |
| `tempoh_nilai` |  |  | integer |  |  | Ya |  |  | dalaman |
| `tempoh_unit` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `jenis_penerima` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nilai_penerima` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `wf_eskalasi_log`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `id_tugasan` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `aras` |  |  | integer |  |  | Ya |  |  | dalaman |
| `penerima` |  |  | jsonb |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Ya |  |  | dalaman |

## `wf_instans`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `id_definisi` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `no_versi_definisi` |  |  | integer |  |  | Ya |  |  | dalaman |
| `kod_aliran` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `modul` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `jenis_subjek` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `id_rekod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `tajuk` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `konteks` |  |  | jsonb |  |  | Tidak |  |  | dalaman |
| `kod_daerah` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `pautan` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `id_pemohon` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `status` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `keadaan` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `peringkat_semasa` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `kitaran` |  |  | integer |  |  | Ya |  |  | dalaman |
| `laluan` |  |  | jsonb |  |  | Tidak |  |  | dalaman |
| `tarikh_mula` |  |  | timestamp without time zone |  |  | Ya |  |  | dalaman |
| `tarikh_tamat` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `ulasan_keputusan` |  |  | text |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `wf_kumpulan`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `kod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nama` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `wf_kumpulan_ahli`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id_kumpulan` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `id_pengguna` |  |  | bigint |  |  | Ya |  |  | dalaman |

## `wf_pelulus`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `id_peringkat` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `jenis` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nilai` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `label` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `wf_pemangku`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `id_pengguna` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `id_pemangku` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `tarikh_mula` |  |  | date |  |  | Ya |  |  | dalaman |
| `tarikh_tamat` |  |  | date |  |  | Ya |  |  | dalaman |
| `sebab` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `dicipta_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `dibatalkan_pada` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `wf_peralihan`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `id_definisi` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `dari` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `ke` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `keutamaan` |  |  | integer |  |  | Ya |  |  | dalaman |
| `logik` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `label` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `wf_peringkat`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `id_definisi` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `kod` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `no_urutan` |  |  | integer |  |  | Ya |  |  | dalaman |
| `nama_peringkat` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `mod_kelulusan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `kuorum` |  |  | integer |  |  | Tidak |  |  | dalaman |
| `sla_nilai` |  |  | integer |  |  | Tidak |  |  | dalaman |
| `sla_unit` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `perlu_mfa` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `perlu_justifikasi` |  |  | boolean |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `wf_pindah_versi`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `id_instans` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `id_definisi_asal` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `id_definisi_baru` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `sebab` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `status` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `diminta_oleh` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `diputuskan_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `diputuskan_pada` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `wf_sejarah`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `id_instans` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `id_tugasan` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `id_peringkat` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `nama_peringkat` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `peristiwa` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `id_pengguna` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `id_bagi_pihak` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `peranan_semasa` |  |  | jsonb |  |  | Tidak |  |  | dalaman |
| `justifikasi` |  |  | text |  |  | Tidak |  |  | dalaman |
| `alamat_ip` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `mfa_pada` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `butiran` |  |  | jsonb |  |  | Tidak |  |  | dalaman |
| `tarikh_masa` |  |  | timestamp without time zone |  |  | Ya |  |  | dalaman |

## `wf_susulan`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `id_definisi` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `bila` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `tajuk` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `jenis_penerima` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nilai_penerima` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `sla_nilai` |  |  | integer |  |  | Tidak |  |  | dalaman |
| `sla_unit` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `wf_syarat`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `id_peralihan` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `medan` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `operator` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nilai` |  |  | jsonb |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

## `wf_tugasan`

| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` |  |  | bigint |  |  | Ya |  |  | dalaman |
| `id_instans` |  |  | uuid |  |  | Ya |  |  | dalaman |
| `id_peringkat` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `jenis` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `tajuk` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `kitaran` |  |  | integer |  |  | Ya |  |  | dalaman |
| `id_pelulus` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `jenis_penerima` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `nilai_penerima` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `penerima_kunci` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `id_pelulus_asal` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `status` |  |  | character varying |  |  | Ya |  |  | dalaman |
| `keputusan` |  |  | character varying |  |  | Tidak |  |  | dalaman |
| `tarikh_akhir_sla` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `ditangguh_hingga` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `aras_eskalasi` |  |  | integer |  |  | Ya |  |  | dalaman |
| `amaran_sla_pada` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `diselesaikan_oleh` |  |  | bigint |  |  | Tidak |  |  | dalaman |
| `diselesaikan_pada` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `created_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |
| `updated_at` |  |  | timestamp without time zone |  |  | Tidak |  |  | dalaman |

