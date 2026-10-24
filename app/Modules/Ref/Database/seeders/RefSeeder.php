<?php

namespace App\Modules\Ref\Database\seeders;

use App\Modules\Ref\Models\Daerah;
use App\Modules\Ref\Models\JenisDokumen;
use App\Modules\Ref\Models\JenisHakMilik;
use App\Modules\Ref\Models\KategoriAduan;
use App\Modules\Ref\Models\KategoriGunaTanah;
use App\Modules\Ref\Models\KlasifikasiData;
use App\Modules\Ref\Models\Mukim;
use App\Modules\Ref\Models\Negeri;
use App\Modules\Ref\Models\Pbt;
use App\Modules\Ref\Models\RefModel;
use App\Modules\Ref\Services\RefVersioning;
use App\Modules\Ref\Support\RefData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Loads the committed reference data (app/Modules/Ref/Database/data) into
 * the ref_* tables. Idempotent: unchanged codes are left alone, changed ones
 * get a new version (SRS-DAT-05), codes gone from the files are closed.
 *
 *   php artisan ref:seed            (or db:seed --class="App\Modules\Ref\Database\seeders\RefSeeder")
 */
class RefSeeder extends Seeder
{
    private const PBT_SOURCE = 'Wikipedia: List of local governments in Malaysia (nama rasmi & singkatan, diambil 2026-10-24)';

    private const PBT_URL = 'https://en.wikipedia.org/wiki/List_of_local_governments_in_Malaysia';

    /** @var array<string, array<string, int>> */
    public array $stats = [];

    public bool $withSpatial = true;

    public function run(RefVersioning $versioning): void
    {
        $this->codes($versioning, Negeri::class, RefData::json('negeri.json'));
        $this->codes($versioning, Daerah::class, RefData::json('daerah.json'));
        $this->codes($versioning, Mukim::class, RefData::json('mukim.json'));
        $this->codes($versioning, Pbt::class, array_map(fn (array $r) => $r + ['sumber' => self::PBT_SOURCE, 'sumber_url' => self::PBT_URL], RefData::json('pbt.json')));

        $lists = RefData::json('senarai.json');
        $models = [
            'ref_kategori_guna_tanah' => KategoriGunaTanah::class,
            'ref_jenis_hak_milik' => JenisHakMilik::class,
            'ref_jenis_dokumen' => JenisDokumen::class,
            'ref_kategori_aduan' => KategoriAduan::class,
            'ref_klasifikasi_data' => KlasifikasiData::class,
        ];
        foreach ($models as $table => $model) {
            $list = $lists[$table];
            $rows = [];
            foreach ($list['rows'] as $i => $row) {
                $rows[] = $row + ['susunan' => $i + 1, 'sumber' => $list['sumber'], 'sumber_url' => $list['sumber_url']];
            }
            $this->codes($versioning, $model, $rows);
        }

        if ($this->withSpatial) {
            $this->boundaries();
            $this->placesOfWorship();
        }
    }

    /**
     * @param  class-string<RefModel>  $model
     * @param  list<array<string, mixed>>  $rows
     */
    private function codes(RefVersioning $versioning, string $model, array $rows): void
    {
        $table = (new $model)->getTable();
        $seen = [];
        $stats = [RefVersioning::CREATED => 0, RefVersioning::VERSIONED => 0, RefVersioning::UNCHANGED => 0, RefVersioning::RETIRED => 0];

        foreach ($rows as $row) {
            $row['aktif'] ??= true;
            $row['keterangan'] ??= null;
            [$result] = $versioning->upsert($model, $row);
            $stats[$result]++;
            $seen[] = (string) $row['kod'];
        }

        foreach ($model::query()->semasa()->whereNotIn('kod', $seen)->pluck('kod') as $gone) {
            $versioning->retire($model, $gone);
            $stats[RefVersioning::RETIRED]++;
        }

        $this->stats[$table] = $stats;
    }

    private function boundaries(): void
    {
        $files = [
            'negeri' => 'sempadan_negeri.geojson',
            'daerah' => 'sempadan_daerah.geojson',
            'pbt' => 'sempadan_pbt.geojson',
            'mukim' => 'sempadan_mukim_anggaran.geojson',
        ];
        $manifest = RefData::exists('manifest.json') ? RefData::json('manifest.json') : [];

        foreach ($files as $jenis => $file) {
            if (! RefData::exists($file)) {
                continue;
            }
            $meta = $manifest[str_replace('.geojson', '', $file)] ?? [];
            $count = 0;

            foreach (RefData::json($file)['features'] as $f) {
                $p = $f['properties'];
                DB::statement(<<<'SQL'
                    INSERT INTO ref_sempadan (jenis, kod, nama, geometri, anggaran, toleransi_permudah, luas_km2, sumber, sumber_url, lesen, osm_id, diambil_pada, created_at, updated_at)
                    VALUES (?, ?, ?, ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)), ?, ?, ?, ?, ?, ?, ?, ?, now(), now())
                    ON CONFLICT (jenis, kod) DO UPDATE SET nama = EXCLUDED.nama, geometri = EXCLUDED.geometri, anggaran = EXCLUDED.anggaran,
                        toleransi_permudah = EXCLUDED.toleransi_permudah, luas_km2 = EXCLUDED.luas_km2, sumber = EXCLUDED.sumber,
                        sumber_url = EXCLUDED.sumber_url, lesen = EXCLUDED.lesen, osm_id = EXCLUDED.osm_id,
                        diambil_pada = EXCLUDED.diambil_pada, updated_at = now()
                SQL, [
                    $jenis, $p['kod'], $p['nama'], json_encode($f['geometry']),
                    ! empty($p['anggaran']), $p['toleransi_permudah'] ?? null, $p['luas_km2'] ?? null,
                    $jenis === 'mukim' ? 'Anggaran Voronoi daripada titik tempat OSM + sempadan daerah OSM' : ($meta['sumber'] ?? 'OpenStreetMap'),
                    $meta['sumber_url'] ?? null, $meta['lesen'] ?? 'ODbL 1.0 — © OpenStreetMap contributors', $p['osm_id'] ?? null,
                    isset($meta['tarikh_data_osm']) ? substr($meta['tarikh_data_osm'], 0, 10) : null,
                ]);
                $count++;
            }
            $this->stats['ref_sempadan:'.$jenis] = ['dimuat' => $count];
        }
    }

    private function placesOfWorship(): void
    {
        if (! RefData::exists('tempat_ibadat.geojson')) {
            return;
        }
        $meta = (RefData::exists('manifest.json') ? RefData::json('manifest.json') : [])['tempat_ibadat'] ?? [];
        $date = isset($meta['tarikh_data_osm']) ? substr($meta['tarikh_data_osm'], 0, 10) : now()->toDateString();
        $features = RefData::json('tempat_ibadat.geojson')['features'];
        $keys = [];

        foreach (array_chunk($features, 200) as $chunk) {
            $values = [];
            $bindings = [];
            foreach ($chunk as $f) {
                $p = $f['properties'];
                $keys[] = $p['osm_jenis'].'/'.$p['osm_id'];
                $values[] = '(?, ?, ?, ?, ST_SetSRID(ST_MakePoint(?, ?), 4326), ?::json, ?, ?, ?, now(), now())';
                array_push($bindings, $p['osm_jenis'], $p['osm_id'], $p['nama'], $p['jenis'],
                    $f['geometry']['coordinates'][0], $f['geometry']['coordinates'][1],
                    ! empty($p['tag']) ? json_encode($p['tag'], JSON_UNESCAPED_UNICODE) : null,
                    'OpenStreetMap (Overpass)', 'ODbL 1.0 — © OpenStreetMap contributors', $date);
            }
            DB::statement('INSERT INTO ref_tempat_ibadat (osm_jenis, osm_id, nama, jenis, geometri, tag, sumber, lesen, diambil_pada, created_at, updated_at) VALUES '
                .implode(', ', $values)
                .' ON CONFLICT (osm_jenis, osm_id) DO UPDATE SET nama = EXCLUDED.nama, jenis = EXCLUDED.jenis, geometri = EXCLUDED.geometri,
                   tag = EXCLUDED.tag, sumber = EXCLUDED.sumber, lesen = EXCLUDED.lesen, diambil_pada = EXCLUDED.diambil_pada, updated_at = now()', $bindings);
        }

        // Removed from OSM since the last refresh → removed here (derived data, not versioned).
        $deleted = DB::delete("DELETE FROM ref_tempat_ibadat WHERE NOT (osm_jenis || '/' || osm_id = ANY (?::text[]))", ['{'.implode(',', $keys).'}']);

        DB::statement(<<<'SQL'
            UPDATE ref_tempat_ibadat t SET
                daerah_kod = (SELECT s.kod FROM ref_sempadan s WHERE s.jenis = 'daerah' AND ST_Contains(s.geometri, t.geometri) LIMIT 1),
                mukim_kod = (SELECT s.kod FROM ref_sempadan s WHERE s.jenis = 'mukim' AND ST_Contains(s.geometri, t.geometri) LIMIT 1)
        SQL);

        $this->stats['ref_tempat_ibadat'] = ['dimuat' => DB::table('ref_tempat_ibadat')->count(), 'dipadam' => $deleted];
    }
}
