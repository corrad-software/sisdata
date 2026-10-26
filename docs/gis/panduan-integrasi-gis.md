# Panduan Integrasi GIS — SISDATA

Untuk: pasukan GIS (GIS Specialist & pembangun) yang akan mengintegrasikan platform GIS MAIS (SuperMap atau setara) dengan SISDATA.

## 1. Ringkasan dalam satu perenggan

Semua geometri hartanah MAIS disimpan **sekali sahaja** dalam PostgreSQL/PostGIS SISDATA (SRID 4326, indeks GIST) — itulah rekod tuan (SRS-GIS-03). Platform GIS **tidak menyimpan salinan sendiri** yang disunting berasingan; ia **membaca** melalui SISDATA (tile vektor, GeoJSON, atau WMS/WFS melalui proksi), dan **menghantar balik** geometri baharu/dibetulkan melalui API import/spatial SISDATA supaya pengesahan, versi dan audit berlaku. Setiap bacaan ditapis ikut skop pengguna (daerah/mukim/klasifikasi) — GIS tidak boleh menjadi jalan pintas kebenaran (SRS-RBC-08).

## 2. Modul yang terlibat dengan GIS

### 2.1 Modul yang **menyediakan lapisan peta** (baca)

| # | Modul | Kunci lapisan | Jenis | Kebenaran diperlukan | Klasifikasi | Tile vektor | Isi |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | MOD-PRO / MOD-GIS (Lot) | `gis.lot` | poligon | `gis.peta.lihat` | dalaman | ✔ | Semua lot MAIS, warna ikut bahagian (hartanah/wakaf/baitulmal) |
| 2 | MOD-PRO (guna tanah) | `gis.kategori_guna_tanah`, `gis.zoning` | poligon | `gis.peta.lihat` | dalaman | ✔ | Kategori guna tanah & zoning |
| 3 | MOD-HKM (Hak Milik) | `gis.hak_milik` | poligon | `gis.peta.lihat` | **sulit** | ✔ | Jenis hak milik (warna `frontend/lib/legend.ts`); garis putus = tiada hak milik sah |
| 4 | MOD-HKM (status tanah) | `gis.status_tanah` | poligon | `gis.peta.lihat` | dalaman | ✔ | Status tanah (aktif, disewa, tindakan undang-undang…) |
| 5 | MOD-PER (Pemerolehan) | `gis.pemerolehan` | poligon | `gis.peta.lihat` | **sulit** | ✔ | Saluran pemerolehan (warna `frontend/lib/legend-pemerolehan.ts`) |
| 6 | MOD-PJK (Pajakan) | `pjk.pajakan` | poligon | `pjk.kontrak.lihat` | dalaman | — | Status pajakan/sewaan, tunggakan |
| 7 | MOD-CKI (Cukai) | `cki.status_cukai` | poligon | `cki.bil.lihat` | dalaman | ✔ | Status bayaran cukai (dibayar/tertunggak/denda) |
| 8 | MOD-PTN (Pemantauan) | `ptn.pemantauan`, `ptn.risiko` | poligon | `ptn.peta.lihat` | dalaman | ✔ | Lawatan terakhir/tertunggak; skor risiko merah-kuning-hijau |
| 9 | MOD-ADU (Aduan) | `adu.aduan` | titik | `adu.aduan.lihat` | dalaman | — | Aduan & isu ikut kategori/status |
| 10 | MOD-KES (Kes Mahkamah) | `kes.kes_mahkamah` | poligon | `kes.rahsia.lihat` | **rahsia** | ✔ | Lot dalam tindakan undang-undang |
| 11 | MOD-TSU (Urusan Pejabat Tanah) | `tsu.urusan`, `tsu.hasil_ukur` | poligon | `tsu.peta.lihat` | dalaman | ✔ | Tukar syarat/pecah sempadan/pelupusan; hasil ukur baharu |
| 12 | MOD-AST (Aset Fizikal) | `ast.aset_fizikal` | titik | `ast.peta.lihat` | dalaman | ✔ | Aset (bangunan, struktur) ikut keadaan |
| 13 | MOD-LAP (Analitik) | `lap.analitik` | titik | `lap.analitik.lihat` | dalaman | — | Skor potensi hasil/tanah terbiar (cadangan sistem) |
| 14 | MOD-REF (Rujukan) | `gis.sempadan_daerah`, `gis.sempadan_mukim`, `gis.sempadan_pbt`, `gis.tempat_ibadat` | poligon/titik | `gis.peta.lihat` | awam | ✔ | Sempadan pentadbiran (mukim = **anggaran**, perlu diganti data JUPEM/PTG) & masjid/surau |
| 15 | MOD-GIS (import) | `gis.set_data` | campuran | `gis.peta.lihat` | dalaman | — | Set data yang diimport oleh GIS Specialist |

Senarai sebenar sentiasa boleh diambil daripada `GET /api/v1/gis/lapisan` (ikut kebenaran pengguna yang memanggil).

### 2.2 Modul yang **menulis / menghasilkan data ruang**

| Modul | Data | Cara masuk | Kawalan |
| --- | --- | --- | --- |
| MOD-PRO | Geometri lot (`data_spatial`, MultiPolygon 4326, berversi) | `POST /api/v1/pro/lot/{id}/spatial` (GeoJSON, WKT, KML, SHP zip) | Pengesahan maker-checker: pemuat naik ≠ pengesah; status `belum_disahkan → disahkan_juruukur / disahkan_ptg / bertindih` |
| MOD-GIS | Set data import (SHP/GeoJSON/KML/KMZ/DXF/GPKG) | `POST /api/v1/gis/import` → semak → `POST /api/v1/gis/import/{id}/terima` | Semakan CRS, `ST_IsValid`, di luar Selangor, pertindihan; fail asal ke DMS |
| MOD-TSU | Hasil ukur → lot baharu | Modul TSU (import hasil ukur) | Semakan CRS & toleransi keluasan; pengesah ≠ pengimport |
| MOD-PTN | GPS lawatan & foto bergeotag | Aplikasi lapangan `/admin/ptn/lapangan` | Geofence ikut poligon lot |
| MOD-AST | Titik aset | Daftar aset / aplikasi lapangan aset | — |
| MOD-ADU | Titik aduan | Borang aduan / portal awam | Padanan titik → lot (PostGIS) |

### 2.3 Modul yang **memaparkan peta** (pengguna akhir)

Profil Hartanah 360° (tab Ruang), Peta GIS `/admin/gis`, Dashboard Eksekutif (widget peta), PER (taburan pemerolehan), TSU (banding geometri lama/baru). Semua guna komponen `<PetaSisdata>` (MapLibre GL) — `frontend/components/gis/`.

## 3. Cara integrasi — pilih ikut keperluan

### Pilihan A — Platform GIS membaca melalui OGC (WMS/WFS) ⭐ disyorkan untuk SuperMap/QGIS/ArcGIS

```
SuperMap / QGIS ──WMS/WFS──► SISDATA /api/v1/gis/ogc/{wms|wfs}  (Laravel: sahkan pengguna + suntik skop CQL)
                                        │
                                        ▼
                                 GeoServer :8085 (workspace "sisdata", akaun servis sahaja)
                                        │
                                        ▼
                                 PostgreSQL/PostGIS (rekod tuan)
```

- Endpoint: `GET /api/v1/gis/ogc/wms?...` dan `GET /api/v1/gis/ogc/wfs?...` (parameter OGC standard: `SERVICE`, `REQUEST`, `LAYERS`/`TYPENAMES`, `BBOX`, `SRS`/`SRSNAME`, `FORMAT`…).
- Pengesahan: token Sanctum pengguna (`Authorization: Bearer …`) — pengguna mesti ada kebenaran `gis.peta.lihat` (+ `gis.sulit.lihat`/`gis.rahsia.lihat` bagi lapisan berklasifikasi). Butiran sambungan: `GET /api/v1/gis/ogc` (perlu `gis.ogc.lihat`).
- **Proksi menolak** `CQL_FILTER`, `FILTER`, `VIEWPARAMS`, `ENV`, `SLD` daripada klien dan menyuntik skop pengguna sendiri — klien GIS tidak boleh "melebarkan" capaian.
- WMTS **sengaja tidak disediakan** (cache tile kongsi akan bocorkan skop seorang pengguna kepada yang lain).
- GeoServer **mesti difirewall** daripada pengguna; hanya Laravel boleh memanggilnya. Sediakan: `php artisan gis:geoserver-setup`; tetapkan `GIS_GEOSERVER_PROXY_PASSWORD` dalam produksi.
- **Menggantikan GeoServer dengan SuperMap iServer:** kekalkan proksi Laravel; tukar sasaran dalam `config/gis.php` (URL, workspace, nama lapisan) dan pastikan SuperMap menerima penapis skop yang setara (CQL/attribute filter). Lapisan di SuperMap mesti menjadi **SQL view / data source baca-sahaja** ke PostGIS SISDATA — bukan salinan.

### Pilihan B — Aplikasi web membaca tile vektor / GeoJSON

- Tile vektor (Mapbox Vector Tile): `GET /api/v1/gis/tiles/{lapisan}/{z}/{x}/{y}.pbf`
- GeoJSON ikut bbox: `GET /api/v1/gis/lapisan/{lapisan}/ciri?bbox=minLng,minLat,maxLng,maxLat&kod_daerah=…`
- Carian lokasi: `GET /api/v1/gis/cari?q=…`; statistik daerah/mukim: `GET /api/v1/gis/statistik`
- Setiap permintaan ditapis ikut kebenaran + skop pengguna; tile dicache ikut tandatangan skop.

### Pilihan C — Menghantar geometri baharu/dibetulkan ke SISDATA (tulis)

1. **Satu lot:** `POST /api/v1/pro/lot/{id}/spatial` (perlu `pro.spatial.cipta`). Terima GeoJSON/WKT/KML/SHP zip; nyatakan `srid_asal` jika bukan 4326 — SISDATA menukar sendiri daripada **EPSG:3380 (Cassini Selangor), 3375, 3168, 4742 (GDM2000), 4245** ke 4326. Status mula `belum_disahkan`; pegawai lain mengesahkan melalui `POST /api/v1/pro/spatial/{id}/sahkan` (perlu `pro.spatial.lulus`).
2. **Pukal:** `POST /api/v1/gis/import` (SHP zip/GeoJSON/KML/KMZ/DXF/GPKG) → semak laporan `GET /api/v1/gis/import/{id}/laporan` → `POST /api/v1/gis/import/{id}/terima`. Fail ditolak keseluruhannya jika ada ciri tidak sah (laporan menyenaraikan setiap ciri).
3. **Jangan** tulis terus ke jadual `lot_tanah` / `data_spatial` — versi, pengesahan dan audit akan terlepas.

### Pilihan D — Paparan peta dalam modul SISDATA (pembangun frontend)

```tsx
import { PetaSisdata } from '@/components/gis/PetaSisdata';

<PetaSisdata layers={['gis.lot', 'cki.status_cukai']} focus={{ lotId }} onFeatureClick={(f) => …} />
```

### Pilihan E — Modul baharu menambah lapisan sendiri (pembangun backend)

Laksanakan `App\Modules\Gis\Contracts\MapLayer` dan daftar dalam `boot()` modul:

```php
$this->callAfterResolving(MapLayers::class, fn (MapLayers $layers) => $layers->register(LapisanSaya::class));
```

- `key()` dengan awalan kod modul (`xyz.nama`), `group()` (asas/hartanah/operasi/risiko/rujukan), `geometryType()`, `permission()`, `legend()`.
- `features()` **wajib** menapis ikut skop pengguna (guna model ber-`ScopedByAccess`); setiap ciri ada `properties.id`, `label`, `pautan` (deep link ke Profil 360°).
- Pilihan: `VectorTileLayer` (lapisan besar → tile MVT) dan `ClassifiedMapLayer` (sulit/rahsia).

## 4. Piawaian data ruang

| Perkara | Piawaian |
| --- | --- |
| Storan | PostGIS, **SRID 4326**, `MultiPolygon` bagi lot, `Point` bagi titik |
| Unjuran input diterima | 4326, 3380 (Cassini-Soldner Selangor), 3375 (RSO), 3168, 4742 (GDM2000), 4245; DXF tanpa CRS dianggap 3380 |
| Sumber geometri (`data_spatial.sumber_data`) | `jupem`, `ptg`, `pbt`, `juruukur`, `lakaran`, `satelit`, `sintetik` |
| Status pengesahan | `belum_disahkan`, `disahkan_juruukur`, `disahkan_ptg`, `bertindih` |
| Kod pentadbiran | Daerah 4 digit (cth `1008` Petaling), mukim 6 digit (cth `100803`) — MOD-REF |
| Legenda warna | `frontend/lib/legend.ts`, `frontend/lib/legend-pemerolehan.ts` — ujian gagal jika warna PHP tidak sepadan |
| Semakan kualiti | `php artisan gis:semak-kualiti` (mingguan): geometri tidak sah, kosong, tanpa geometri, luar Selangor/mukim, pertindihan, jurang |

## 5. Tindakan yang diperlukan daripada pasukan GIS / MAIS

1. **Sempadan mukim rasmi** (JUPEM/PTG) — gantikan 54 poligon anggaran dan sahkan 5 kod mukim yang diteka (`database/data`/MOD-REF, bendera `kod_disahkan=false`).
2. **Geometri lot sebenar** — import melalui Pilihan C; sasarkan status `disahkan_ptg`.
3. **Peta asas satelit berlesen** — tetapkan `gis.peta_asas_satelit_url` & atribusi (parameter ADM).
4. **Platform GIS (SuperMap/setara)** — sambung melalui Pilihan A; jangan buka GeoServer/iServer terus kepada pengguna.
5. **Akaun servis & rahsia** — `GIS_GEOSERVER_PROXY_PASSWORD`, kata laluan admin GeoServer/iServer, firewall.
6. **Data JPBD/zoning, PBT, MyGDI** — import sebagai set data atau lapisan rujukan.

## 6. Persekitaran dev

- GeoServer: `infra/bin/sisdata-services.sh start` (port 8085) → `php artisan gis:geoserver-setup`
- Peta GIS: http://localhost:3000/admin/gis (log masuk `admin@example.com` / `password`)
- Kod: `app/Modules/Gis/` (backend), `frontend/components/gis/` (peta), `config/gis.php` (GeoServer/OGC)
