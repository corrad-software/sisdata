<?php

namespace App\Console\MaisSeed;

use App\Modules\Aud\Contracts\AuditScope;
use App\Modules\Pro\Models\LotTanah;
use App\Modules\Pro\Services\LotService;
use App\Modules\Pro\Services\SpatialService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * MOD-PRO: the master lot (through LotService, so quality rules, versioning
 * and audit apply) + its Data Spatial (through SpatialService) + a valuation
 * row. Real: name, address, type, stream (when public), coordinates, OSM
 * outline. Synthetic: lot number, area (unless public), land category,
 * zoning, express condition, valuation.
 */
class LotBuilder
{
    public function __construct(private LotService $lots, private SpatialService $spatial) {}

    /** @param array<string, mixed> $h */
    public function bina(array $h, Rawak $r, Konteks $k): LotTanah
    {
        [$kategori, $zoning, $syarat] = Profil::untuk($h['jenis']);
        $geom = $this->geometri($h, $r);
        $areaM2 = (float) $geom['m2'];
        $hektar = $areaM2 >= 20000;
        $pt = $r->chance(30);

        $lot = AuditScope::withReason('Benih data hartanah MAIS (sisdata:mais-seed) '.$h['kunci'], fn () => $this->lots->create([
            'no_lot' => $this->noLot($h['kod_mukim'], $pt, $r),
            'kod_mukim' => $h['kod_mukim'],
            'kod_daerah' => $h['kod_daerah'],
            'keluasan' => $hektar ? round($areaM2 / 10000, 4) : round($areaM2, 2),
            'unit_keluasan' => $hektar ? 'hektar' : 'm2',
            'kategori_guna_tanah' => $kategori,
            'zoning' => Str::limit($zoning, 50, ''),
            'syarat_nyata' => $syarat.' (contoh sintetik)',
            'nama_hartanah' => Str::limit($h['nama'], 200, ''),
            'bahagian_pemilik' => $h['bahagian'],
            'status_lot' => 'aktif',
            'klasifikasi' => 'dalaman',
            'tag' => array_values(array_unique(['mais_awam', 'jenis:'.$h['jenis'], 'aliran:'.$h['aliran'], 'geometri:'.$geom['sumber']])),
            'catatan' => $this->catatan($h, $geom),
        ], null, [
            'sintetik' => true,
            'sumber_sistem' => Konteks::SUMBER,
            'fail_asal' => Konteks::FAIL,
            'kunci_asal' => $h['kunci'],
            'id_data_steward' => $k->pegawai->id,
            'catatan_versi' => 'Benih hartanah MAIS (data awam + sintetik) '.$h['kunci'],
        ]));

        $parsed = $this->spatial->parse('wkt', $geom['wkt'], 4326);
        $this->spatial->store($lot, $parsed, [
            // data_spatial.sumber_data has no "osm" code: an OSM outline is a sketch (lakaran) by contributors;
            // an estimated parcel around a public point is synthetic.
            'sumber_data' => $geom['sumber'] === 'osm' ? 'lakaran' : 'sintetik',
            'rujukan_fail_sumber' => $geom['rujukan'],
            'tarikh_perolehan_data' => $h['tarikh_capaian'] ?? null,
            'sumber_sistem' => Konteks::SUMBER,
            'kunci_asal' => $h['kunci'],
        ], null);
        $k->tambah('lot_tanah');
        $k->tambah('data_spatial');
        $k->tambah('geometri_'.$geom['sumber']);

        $this->penilaian($lot, $h, $r, $areaM2);
        $k->tambah('penilaian');

        return $lot->refresh();
    }

    /**
     * Real OSM outline when public (a building footprint is widened by 8 m to
     * stand for its plot), else a rotated rectangle around the public point,
     * sized from the public area or a plausible size for the type.
     *
     * @param  array<string, mixed>  $h
     * @return array{wkt: string, m2: float, sumber: string, rujukan: string}
     */
    private function geometri(array $h, Rawak $r): array
    {
        $g = (array) ($h['geometri'] ?? []);
        if (! empty($g['geojson'])) {
            $buffer = ! empty($g['garis_bangunan']) ? 8 : 0;
            $row = DB::selectOne(
                'WITH g AS (SELECT ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)) AS g0),
                      b AS (SELECT CASE WHEN ? > 0 THEN ST_Buffer(g0::geography, ?, 2)::geometry ELSE g0 END AS g1 FROM g)
                 SELECT ST_AsText(ST_Multi(ST_CollectionExtract(ST_SimplifyPreserveTopology(g1, 0.000005), 3))) AS wkt, ST_Area(g1::geography) AS m2 FROM b',
                [json_encode($g['geojson']), $buffer, $buffer],
            );
            if ($row !== null && (float) $row->m2 > 50) {
                return ['wkt' => (string) $row->wkt, 'm2' => (float) $row->m2, 'sumber' => 'osm', 'rujukan' => 'OpenStreetMap '.($g['osm'] ?? '').' (ODbL)'.($buffer > 0 ? ' — garis bangunan diperluas 8 m' : '')];
            }
        }

        [, , , [$min, $max]] = Profil::untuk($h['jenis']);
        $area = is_numeric($h['keluasan_m2'] ?? null) && (float) $h['keluasan_m2'] > 50 ? (float) $h['keluasan_m2'] : $r->float($min, $max);
        $ratio = $r->float(0.6, 1.8);
        $w = sqrt($area * $ratio);
        $hgt = $area / $w;
        $angle = $r->float(0, M_PI);
        $lat = (float) $h['lat'];
        $lon = (float) $h['lon'];
        $mLat = 110574.0;
        $mLon = 111320.0 * cos(deg2rad($lat));
        $pts = [];
        foreach ([[-$w / 2, -$hgt / 2], [$w / 2, -$hgt / 2], [$w / 2, $hgt / 2], [-$w / 2, $hgt / 2]] as [$x, $y]) {
            $rx = $x * cos($angle) - $y * sin($angle);
            $ry = $x * sin($angle) + $y * cos($angle);
            $pts[] = sprintf('%.7F %.7F', $lon + $rx / $mLon, $lat + $ry / $mLat);
        }
        $pts[] = $pts[0];

        return ['wkt' => 'MULTIPOLYGON((('.implode(',', $pts).')))', 'm2' => $area, 'sumber' => 'anggaran', 'rujukan' => 'Anggaran sintetik di sekeliling titik awam ('.($h['geocode'] ?? 'osm').')'];
    }

    private function noLot(string $mukim, bool $pt, Rawak $r): string
    {
        for ($i = 0; $i < 50; $i++) {
            $no = $pt ? 'PT '.$r->int(60001, 99999) : 'Lot '.$r->int(100000, 199999);
            if (! DB::table('lot_tanah')->where('kod_mukim', $mukim)->where('no_lot', $no)->whereNull('deleted_at')->exists()) {
                return $no;
            }
        }

        return 'PT '.$r->int(200000, 299999);
    }

    /**
     * @param  array<string, mixed>  $h
     * @param  array{wkt: string, m2: float, sumber: string, rujukan: string}  $geom
     */
    private function catatan(array $h, array $geom): string
    {
        $aliran = [
            'wakaf' => 'wakaf (dinyatakan sumber awam)', 'baitulmal' => 'Baitulmal (dinyatakan sumber awam)', 'hartanah_am' => 'hartanah am MAIS (dinyatakan sumber awam)',
            'zakat_lzs' => 'Lembaga Zakat Selangor (dinyatakan sumber awam)', 'tidak_dinyatakan' => 'tidak dinyatakan dalam sumber awam — bahagian pemilik ialah andaian demo',
        ][$h['aliran']];
        $lines = [
            'HARTANAH MAIS (data awam): '.$h['nama'],
            'Jenis: '.str_replace('_', ' ', $h['jenis']).'. Aliran pemilikan: '.$aliran.'.',
        ];
        if (! empty($h['alamat'])) {
            $lines[] = 'Alamat: '.$h['alamat'];
        }
        foreach ((array) ($h['fakta'] ?? []) as $f) {
            $lines[] = '• '.$f['medan'].': '.$f['nilai'].' ['.($f['sumber'] ?? '-').']';
        }
        $lines[] = 'Geometri: '.$geom['rujukan'].'.';
        $lines[] = 'Sumber: '.implode('; ', array_map(fn ($s) => (string) $s, (array) ($h['sumber_url'] ?? []))).'.';
        $lines[] = 'SINTETIK: nombor lot, keluasan (jika tiada sumber), kategori, syarat, hak milik, sewaan, cukai dan rekod operasi lain ialah data contoh — bukan rekod sebenar MAIS.';

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $h */
    private function penilaian(LotTanah $lot, array $h, Rawak $r, float $areaM2): void
    {
        $perM2 = match ($h['jenis']) {
            'hotel', 'bangunan_pejabat', 'komersial' => $r->int(900, 3500),
            'kediaman', 'klinik', 'pusat_islam' => $r->int(450, 1500),
            'pertanian' => $r->int(8, 40),
            'tanah_kosong' => $r->int(60, 400),
            default => $r->int(120, 600),
        };
        $nilai = round($areaM2 * $perM2, -3);
        if (is_numeric($h['nilai_awam_rm'] ?? null)) {
            $nilai = (float) $h['nilai_awam_rm'];
        }
        $year = (int) now()->year;
        foreach ([$year - 5, $year - 2] as $i => $y) {
            DB::table('penilaian')->insert([
                'id' => (string) Str::uuid(), 'lot_id' => $lot->id,
                'tarikh_penilaian' => sprintf('%d-%02d-%02d', $y, $r->int(1, 12), $r->int(1, 28)),
                'nilai_pasaran' => round($nilai * ($i === 0 ? $r->float(0.78, 0.92) : 1.0), 2),
                'nilai_setara' => round($nilai * ($i === 0 ? 0.8 : 0.95), 2),
                'kaedah' => $r->pick(['perbandingan', 'kos', 'pelaburan']),
                'penilai' => 'JPPH Negeri Selangor (penilaian SINTETIK)',
                'versi' => $i + 1, 'klasifikasi' => 'rahsia', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
