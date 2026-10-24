# Data Rujukan SISDATA — manifest sumber

Committed, processed reference data for `app/Modules/Ref` (MOD-REF). Raw downloads are **not** committed:
they are cached in `storage/app/ref-raw/` (gitignored) and can be re-created with the commands below.
Machine-readable provenance per OSM dataset (source, licence, OSM data timestamp, processing date, row count)
lives in [`manifest.json`](manifest.json) and is rewritten by `php artisan ref:fetch-osm`.

Retrieval / processing date for this version: **2026-10-24**.

## Attribution (required)

> Map data © OpenStreetMap contributors, available under the Open Database Licence (ODbL) 1.0 —
> https://www.openstreetmap.org/copyright

The boundary and place-of-worship files below are a *derived database* of OpenStreetMap. Under ODbL,
any public use (map, export, API) must show the attribution above, and if the derived database itself is
distributed publicly it must be offered under ODbL. The API returns the attribution in every GeoJSON
response (`attribution` / `properties.atribusi`).

## Datasets

| File | Rows | Source | Licence | Notes |
| --- | --- | --- | --- | --- |
| `negeri.json` | 3 | State codes as used by JPN / JUPEM (Selangor `10`, WP Kuala Lumpur `14`, WP Putrajaya `16`) — the same two digits lead every data.gov.my *Kod Kampung*; ISO 3166-2:MY | public codes | |
| `daerah.json` | 9 | data.gov.my — *Senarai Kampung Mengikut Kategori di Semenanjung Malaysia dan WP Labuan* (PLANMalaysia, 2021): digits 1–4 of *Kod Kampung* = negeri + daerah. https://archive.data.gov.my/data/dataset/senarai-kampung-mengikut-kategori-di-semenanjung-malaysia-dan-wilayah-persekutuan-labuan | CC BY (data.gov.my) | All 9 codes observed in the source (`kod_disahkan = true`). Code `03` is absent from the source (historically Kuala Lumpur). Wikidata IDs and OSM relation IDs from OSM tags. |
| `mukim.json` | 54 | Same data.gov.my dataset, digits 1–6 of *Kod Kampung* = negeri + daerah + mukim; mukim names from data.gov.my *Senarai Kampung Mengikut Daerah di Negeri Selangor* https://archive.data.gov.my/data/dataset/senarai-kampung-mengikut-daerah-di-negeri-selangor; count per district cross-checked with Wikipedia *Mukim* (54 for Selangor) | CC BY (data.gov.my) | 49 codes observed in the source. **5 codes are inferred** from gaps in the code sequence and flagged `kod_disahkan = false`: 100603 Ampang, 100703 Ampang Pecah, 100708 Kuala Kalumpang, 100713 Sungai Tinggi, 100404 Hulu Tinggi. Must be confirmed with JUPEM / PTG Selangor. Spelling variants kept in `nama_lain` for normalisation. |
| `pbt.json` | 12 | Wikipedia — *List of local governments in Malaysia* (official Malay names, abbreviations, status), retrieved 2026-10-24. https://en.wikipedia.org/wiki/List_of_local_governments_in_Malaysia | CC BY-SA 4.0 (facts only) | Codes are the upper-cased official abbreviations (`MBSA`, `MBPJ`, `MBSJ`, `MBDK`, `MPAJ`, `MPKJ`, `MPS`, `MPSP`, `MPHS`, `MPKL`, `MPKS`, `MDSB`). District coverage (`daerah_kod`) is our own mapping. Status upgrades after the retrieval date are not reflected. |
| `senarai.json` | 4 + 8 + 19 + 10 + 4 | Kategori guna tanah and jenis hak milik: Kanun Tanah Negara 1965 (s.52, s.53, s.62, s.65, s.77, s.176). Jenis dokumen and kategori aduan: SISDATA starter lists derived from the SRS. Klasifikasi data: SRS §4.2. | statute / own work | Starter lists — MAIS must confirm before production (MOD-ADM edits them through versioned MDM). |
| `sempadan_negeri.geojson` | 3 | OSM `boundary=administrative`, `admin_level=4`, `ISO3166-2` MY-10/14/16 via Overpass | ODbL 1.0 | The OSM Selangor relation includes territorial waters (≈15,500 km²); use the union of district polygons for "on land in Selangor" checks. |
| `sempadan_daerah.geojson` | 9 | OSM `admin_level=6` relations (IDs in `daerah.json`) via Overpass | ODbL 1.0 | Coverage-simplified (`ST_CoverageSimplify`, 0.0005° ≈ 55 m) so neighbouring districts still share edges. Sum ≈ 7,920 km². |
| `sempadan_pbt.geojson` | 7 | OSM relations for MBSA, MBPJ, MBSJ, MBDK, MPAJ, MPKj, MPS (IDs in `pbt.json`) via Overpass | ODbL 1.0 | Only 7 of 12 PBTs have a boundary in OSM; OSM (Sinar Project) boundaries are not the gazetted ones. |
| `sempadan_mukim_anggaran.geojson` | 54 | **Derived, approximate.** Voronoi cells of one seed point per mukim, clipped to the OSM district polygon. Seed = OSM `place=*` node of the same name (or the place in `mukim_titik.json`), or a manual approximate point. | ODbL 1.0 (derived) | OSM has no mukim layer for Selangor (only Ampang and Ulu Kelang exist at `admin_level=8`). Every mukim polygon is flagged `anggaran = true` and must be replaced with JUPEM/PTG gazetted boundaries before production use. `properties.titik_asal` names the seed used. |
| `mukim_titik.json` | 24 | Own work: place name overrides / approximate seed points for the mukim Voronoi | — | Edit and re-run `ref:fetch-osm --step=mukim`. |
| `tempat_ibadat.geojson` | 1,433 | OSM `amenity=place_of_worship` + `religion=muslim` inside MY-10 (nodes, ways as centroids, relations) via Overpass | ODbL 1.0 | 394 masjid, 751 surau, 288 unclassified (`lain`, mostly unnamed). Names as tagged in OSM; only descriptive tags kept (no address, phone, e-mail, website). |

## What is NOT here (and why)

- **No real persons, owners, IC numbers, addresses or land titles.** All owner/donor data in `staging_*`
  is generated by `php artisan ref:sample-lots` (`sintetik = true`; IC numbers use place-of-birth code
  `00`, which JPN does not issue — a database CHECK enforces it).
- A balai penghulu list on data.gov.my was inspected for mukim names but **not used or stored** because it
  contains officers' names and phone numbers.
- Official JUPEM/NDCDB or e-Tanah code tables and gazetted mukim boundaries are not openly downloadable;
  they must come from JUPEM / PTG Selangor.

## Refresh

```bash
php artisan ref:fetch-osm                 # re-process cached OSM downloads (no network if cached)
php artisan ref:fetch-osm --refresh       # download again from Overpass (polite: one query at a time, 5 s apart)
php artisan ref:fetch-osm --step=mukim    # rebuild only the approximate mukim polygons
php artisan ref:seed                      # load/refresh ref_* tables (idempotent, versioned)
php artisan ref:sample-lots --count=500   # SYNTHETIC staging lots/titles/owners
```
