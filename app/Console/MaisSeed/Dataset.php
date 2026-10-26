<?php

namespace App\Console\MaisSeed;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Loads database/data/mais/hartanah_mais.json — curated, publicly documented
 * MAIS / PWS / Baitulmal / LZS properties — and fills the derived fields the
 * seeder needs (district/mukim by point-in-polygon when absent).
 *
 * Only `nama`, `jenis`, `aliran`, `alamat`, coordinates, OSM geometry and the
 * entries in `fakta` come from public sources; everything else the seeder
 * creates is synthetic and flagged so.
 */
class Dataset
{
    public const LALAI = 'database/data/mais/hartanah_mais.json';

    public const JENIS = ['masjid', 'surau', 'sekolah_agama', 'bangunan_pejabat', 'komersial', 'kediaman', 'hotel', 'tanah_kosong', 'pertanian', 'klinik', 'kubur', 'institusi_pendidikan', 'pusat_islam', 'lain'];

    public const ALIRAN = ['wakaf', 'baitulmal', 'hartanah_am', 'zakat_lzs', 'tidak_dinyatakan'];

    /** @var array<string, mixed> */
    public array $meta = [];

    /** @var list<array<string, mixed>> */
    public array $hartanah = [];

    /** @var array<string, array<string, mixed>> */
    public array $sumber = [];

    public static function load(string $path): self
    {
        $full = str_starts_with($path, '/') ? $path : base_path($path);
        if (! is_file($full)) {
            throw new RuntimeException("Fail data tidak dijumpai: {$path}");
        }
        $json = json_decode((string) file_get_contents($full), true, 512, JSON_THROW_ON_ERROR);
        $set = new self;
        $set->meta = (array) ($json['meta'] ?? []);
        $set->sumber = (array) ($json['sumber'] ?? []);
        $seen = [];
        foreach ((array) ($json['hartanah'] ?? []) as $i => $h) {
            $key = (string) ($h['kunci'] ?? '');
            if ($key === '' || isset($seen[$key])) {
                throw new RuntimeException("Baris {$i}: kunci kosong atau berulang ({$key}).");
            }
            $seen[$key] = true;
            if (! in_array($h['jenis'] ?? null, self::JENIS, true)) {
                throw new RuntimeException("{$key}: jenis tidak sah.");
            }
            if (! in_array($h['aliran'] ?? null, self::ALIRAN, true)) {
                throw new RuntimeException("{$key}: aliran tidak sah.");
            }
            if (! is_numeric($h['lat'] ?? null) || ! is_numeric($h['lon'] ?? null)) {
                throw new RuntimeException("{$key}: koordinat tiada.");
            }
            $set->hartanah[] = $h;
        }

        return $set;
    }

    /**
     * District + mukim of a point from MOD-REF boundaries (ref_sempadan),
     * nearest mukim when the point falls on a coastline gap.
     *
     * @return array{kod_daerah: string, kod_mukim: string}|null
     */
    public static function lokasi(float $lat, float $lon): ?array
    {
        $row = DB::selectOne(
            "SELECT m.kod, m.daerah_kod FROM ref_sempadan s
             JOIN ref_mukim m ON m.kod = s.kod AND m.sah_hingga IS NULL
             WHERE s.jenis = 'mukim'
             ORDER BY ST_Distance(s.geometri, ST_SetSRID(ST_MakePoint(?, ?), 4326)) LIMIT 1",
            [$lon, $lat],
        );

        return $row === null ? null : ['kod_daerah' => (string) $row->daerah_kod, 'kod_mukim' => (string) $row->kod];
    }
}
