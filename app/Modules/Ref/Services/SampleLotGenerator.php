<?php

namespace App\Modules\Ref\Services;

use App\Modules\Ref\Models\MigrasiLarian;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * Generates SYNTHETIC lots, titles and owners into the staging tables.
 * Deterministic for a given seed: same seed ⇒ same rows, same UUIDs, same
 * lineage keys, so a re-run replaces its own previous output (idempotent).
 *
 * Realism, not reality: lot numbers, title numbers, areas and shapes are
 * random but plausible; every polygon lies inside its (approximate) mukim
 * boundary from ref_sempadan; every row is sintetik = true.
 */
class SampleLotGenerator
{
    public const SOURCE_SYSTEM = 'SINTETIK';

    private const URBAN = ['1001', '1006', '1008', '1009', '1010'];

    private const UUID_NS = '6f1d3c7e-2b9a-4c55-9a51-5e0a2f1b7d10';

    private Randomizer $random;

    private SyntheticPeople $people;

    /** @var array<string, array<string, true>> mukim => used lot numbers */
    private array $usedLots = [];

    /** @var array<string, true> daerah|no_hak_milik already used */
    private array $usedTitles = [];

    public function __construct(private readonly int $seed = 20261025)
    {
        $this->random = new Randomizer(new Mt19937($seed));
        $this->people = new SyntheticPeople($this->random);
    }

    public function sourceFile(): string
    {
        return 'ref:sample-lots?seed='.$this->seed;
    }

    /**
     * @param  list<string>  $daerah  restrict to these district codes (empty = all)
     * @param  float  $messy  share of lots whose no_lot is written in a non-standard format (0–1)
     * @return array{larian: MigrasiLarian, lot: int, hak_milik: int, pemilik: int, ditolak: int}
     */
    public function generate(int $count, array $daerah = [], float $messy = 0.0): array
    {
        $mukim = DB::table('ref_sempadan as s')
            ->join('ref_mukim as m', fn ($j) => $j->on('m.kod', '=', 's.kod')->whereNull('m.sah_hingga'))
            ->where('s.jenis', 'mukim')
            ->when($daerah !== [], fn ($q) => $q->whereIn('m.daerah_kod', $daerah))
            ->orderBy('s.kod')
            ->get(['s.kod', 'm.daerah_kod', 's.luas_km2']);

        if ($mukim->isEmpty()) {
            throw new RuntimeException('No mukim boundaries in ref_sempadan — run `php artisan ref:seed` first.');
        }

        $file = $this->sourceFile();
        $now = now();
        $larian = MigrasiLarian::query()->create([
            'jenis' => 'sintetik_lot_tanah',
            'sumber_sistem' => self::SOURCE_SYSTEM,
            'parameter' => ['seed' => $this->seed, 'count' => $count, 'daerah' => $daerah, 'messy' => $messy],
            'status' => 'berjalan',
            'mula_pada' => $now,
        ]);

        // 1. Decide the mukim of each lot, weighted by sqrt(area) so small urban mukims still get lots.
        $weights = $mukim->map(fn ($m) => sqrt(max((float) $m->luas_km2, 1.0)))->all();
        $total = array_sum($weights);
        $perMukim = [];
        for ($i = 0; $i < $count; $i++) {
            $r = $this->random->getFloat(0, $total);
            $chosen = $mukim->last()->kod;
            foreach ($mukim as $idx => $m) {
                $r -= $weights[$idx];
                if ($r <= 0) {
                    $chosen = $m->kod;
                    break;
                }
            }
            $perMukim[$chosen][] = $i;
        }

        $lots = [];
        $rejected = 0;
        foreach ($perMukim as $kod => $indexes) {
            $m = $mukim->firstWhere('kod', $kod);
            $points = $this->points($kod, count($indexes) * 4);
            foreach ($indexes as $i) {
                $lot = null;
                while ($lot === null && $points !== []) {
                    $lot = $this->lot($i, $m->kod, $m->daerah_kod, array_shift($points), $messy);
                    if ($lot === null) {
                        $rejected++;
                    }
                }
                if ($lot !== null) {
                    $lots[] = $lot;
                }
            }
        }
        usort($lots, fn ($a, $b) => $a['kunci_asal'] <=> $b['kunci_asal']);

        $counts = DB::transaction(function () use ($lots, $file, $larian, $now) {
            // Same seed ⇒ same lineage file: replace the previous output of this seed.
            DB::table('staging_lot_tanah')->where('sumber_sistem', self::SOURCE_SYSTEM)->where('fail_asal', $file)->delete();

            $titles = 0;
            $owners = 0;
            foreach ($lots as $lot) {
                $children = $lot['_hak_milik'];
                unset($lot['_hak_milik']);
                $wkt = $lot['_wkt'];
                unset($lot['_wkt']);

                DB::table('staging_lot_tanah')->insert([
                    ...$lot,
                    'migrasi_larian_id' => $larian->id,
                    'geometri' => DB::raw("ST_GeomFromText('".$wkt."', 4326)"),
                    ...$this->lineage($file, $lot['kunci_asal'], $now),
                ]);

                foreach ($children as $title) {
                    $people = $title['_pemilik'];
                    unset($title['_pemilik']);
                    DB::table('staging_hak_milik')->insert([
                        ...$title,
                        'migrasi_larian_id' => $larian->id,
                        'staging_lot_tanah_id' => $lot['id'],
                        ...$this->lineage($file, $title['kunci_asal'], $now),
                    ]);
                    $titles++;

                    foreach ($people as $person) {
                        DB::table('staging_pemilik')->insert([
                            ...$person,
                            'migrasi_larian_id' => $larian->id,
                            'staging_hak_milik_id' => $title['id'],
                            ...$this->lineage($file, $person['kunci_asal'], $now),
                        ]);
                        $owners++;
                    }
                }
            }

            return [$titles, $owners];
        });

        $larian->forceFill([
            'status' => 'selesai',
            'bilangan_diterima' => count($lots),
            'bilangan_ditolak' => $count - count($lots),
            'catatan' => "Data SINTETIK. Calon poligon ditolak (di luar sempadan mukim): {$rejected}.",
            'tamat_pada' => now(),
        ])->save();

        return ['larian' => $larian, 'lot' => count($lots), 'hak_milik' => $counts[0], 'pemilik' => $counts[1], 'ditolak' => $rejected];
    }

    /**
     * Random points inside the mukim polygon (PostGIS, seeded ⇒ reproducible).
     *
     * @return list<array{0: float, 1: float}>
     */
    private function points(string $mukimKod, int $n): array
    {
        $seed = (crc32($this->seed.':'.$mukimKod) & 0x3FFFFFFF) + 1;
        $rows = DB::select(
            'SELECT ST_X(d.geom) AS x, ST_Y(d.geom) AS y
             FROM ref_sempadan s, ST_Dump(ST_GeneratePoints(s.geometri, ?, ?)) d
             WHERE s.jenis = ? AND s.kod = ?',
            [$n, $seed, 'mukim', $mukimKod],
        );

        return array_map(fn ($r) => [(float) $r->x, (float) $r->y], $rows);
    }

    /**
     * @param  array{0: float, 1: float}  $centre
     * @return array<string, mixed>|null null when the polygon would leave the mukim
     */
    private function lot(int $i, string $mukimKod, string $daerahKod, array $centre, float $messy): ?array
    {
        $kategori = $this->category($daerahKod);
        [$areaM2, $unit] = $this->area($kategori);
        $wkt = $this->polygon($centre, $areaM2);

        $inside = DB::selectOne(
            "SELECT ST_Within(g, s.geometri) AS ok, ST_Area(g::geography) AS m2
             FROM ref_sempadan s, ST_GeomFromText(?, 4326) AS g WHERE s.jenis = 'mukim' AND s.kod = ?",
            [$wkt, $mukimKod],
        );
        if (! $inside?->ok) {
            return null;
        }
        $areaM2 = (float) $inside->m2; // recorded area = surveyed polygon area

        $pt = $this->chance(25);
        $prefix = $pt ? 'PT' : 'Lot';
        do {
            $number = $pt ? $this->random->getInt(1, 60000) : $this->random->getInt(1, 99999);
        } while (isset($this->usedLots[$mukimKod][$prefix.$number]));
        $this->usedLots[$mukimKod][$prefix.$number] = true;

        $noLot = $this->random->getFloat(0, 1) < $messy
            ? $this->messyLotNumber($prefix, $number)
            : "{$prefix} {$number}";

        $wakaf = $this->chance(55);
        $baitulmal = ! $wakaf && $this->chance(55);
        $jenisWakaf = $wakaf ? ($this->chance(40) ? 'khas' : 'am') : null;
        $key = sprintf('LOT-%06d', $i + 1);
        $id = $this->uuid($key);

        $rizab = ! $pt && $this->chance(3);

        return [
            'id' => $id,
            'no_lot' => $noLot,
            'jenis_lot' => $pt ? 'PT' : 'LOT',
            'negeri_kod' => substr($daerahKod, 0, 2),
            'daerah_kod' => $daerahKod,
            'mukim_kod' => $mukimKod,
            'keluasan' => $unit === 'hektar' ? round($areaM2 / 10000, 4) : round($areaM2, 2),
            'unit_keluasan' => $unit,
            'kategori_guna_tanah_kod' => $rizab ? 'TIADA' : $kategori,
            'zoning' => $this->zoning($kategori),
            'syarat_nyata' => $this->condition($kategori, $jenisWakaf),
            'sekatan_kepentingan' => $wakaf ? 'Tanah wakaf — tidak boleh dijual, dipindah milik atau digadai (contoh sintetik).' : ($this->chance(30) ? 'Tidak boleh dipindah milik tanpa kebenaran Pihak Berkuasa Negeri (contoh sintetik).' : null),
            'wakaf' => $wakaf,
            'jenis_wakaf' => $jenisWakaf,
            'baitulmal' => $baitulmal,
            'kunci_asal' => $key,
            '_wkt' => $wkt,
            '_hak_milik' => $rizab ? [] : [$this->title($i, $id, $daerahKod, $pt, $wakaf)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function title(int $i, string $lotId, string $daerahKod, bool $pt, bool $wakaf): array
    {
        $type = $pt
            ? ($this->chance(60) ? 'HSD' : 'HSM')
            : $this->weighted(['GRN' => 45, 'PN' => 15, 'GM' => 28, 'PM' => 12]);
        $prefix = ['GRN' => 'GRN', 'PN' => 'PN', 'GM' => 'GM', 'PM' => 'PM', 'HSD' => 'HS(D)', 'HSM' => 'HS(M)'][$type];

        do {
            $number = $this->random->getInt(1, in_array($type, ['GRN', 'HSD'], true) ? 250000 : 40000);
        } while (isset($this->usedTitles[$daerahKod.'|'.$prefix.$number]));
        $this->usedTitles[$daerahKod.'|'.$prefix.$number] = true;

        $from = in_array($type, ['HSD', 'HSM'], true) ? 1995 : 1950;
        $registered = sprintf('%04d-%02d-%02d', $this->random->getInt($from, 2025), $this->random->getInt(1, 12), $this->random->getInt(1, 28));
        $lease = in_array($type, ['PN', 'PM'], true) ? $this->weighted(['60' => 30, '99' => 70]) : null;
        $key = sprintf('HM-%06d', $i + 1);
        $id = $this->uuid($key);

        $people = [[
            'id' => $this->uuid($key.'-1'),
            'peranan' => 'pemilik_berdaftar',
            'jenis_pemilik' => 'organisasi',
            'nama' => $this->people->organisation(),
            'no_pengenalan' => null,
            'bahagian' => '1/1',
            'kunci_asal' => sprintf('PMK-%06d-1', $i + 1),
        ]];

        // Donors (wakaf) or previous owners (acquired land): synthetic individuals.
        $extra = $wakaf ? $this->random->getInt(1, 2) : ($this->chance(50) ? 1 : 0);
        for ($n = 0; $n < $extra; $n++) {
            $p = $this->people->person();
            $people[] = [
                'id' => $this->uuid($key.'-'.($n + 2)),
                'peranan' => $wakaf ? 'pewakaf' : 'pemilik_asal',
                'jenis_pemilik' => 'individu',
                'nama' => $p['nama'],
                'no_pengenalan' => $p['no_pengenalan'],
                'bahagian' => $extra === 2 ? '1/2' : '1/1',
                'kunci_asal' => sprintf('PMK-%06d-%d', $i + 1, $n + 2),
            ];
        }

        return [
            'id' => $id,
            'no_hak_milik' => "{$prefix} {$number}",
            'jenis_hak_milik_kod' => $type,
            'tarikh_daftar' => $registered,
            'tarikh_tamat' => $lease ? date('Y-m-d', (int) strtotime($registered." +{$lease} years")) : null,
            'tempoh_pajakan_tahun' => $lease ? (int) $lease : null,
            'sekatan_kepentingan' => null,
            'lokasi_simpanan_fizikal' => sprintf('Bilik Kebal MAIS, Rak %s-%02d, Fail %04d (sintetik)', chr(65 + $this->random->getInt(0, 7)), $this->random->getInt(1, 30), $this->random->getInt(1, 9999)),
            'kunci_asal' => $key,
            '_pemilik' => $people,
        ];
    }

    private function category(string $daerahKod): string
    {
        return in_array($daerahKod, self::URBAN, true)
            ? $this->weighted(['BANGUNAN' => 60, 'PERTANIAN' => 28, 'PERUSAHAAN' => 12])
            : $this->weighted(['PERTANIAN' => 70, 'BANGUNAN' => 25, 'PERUSAHAAN' => 5]);
    }

    /**
     * @return array{0: float, 1: string} area in m² and the unit it is recorded in
     */
    private function area(string $kategori): array
    {
        return match ($kategori) {
            // house lots mostly, sometimes a mosque / school site
            'BANGUNAN' => [$this->chance(80) ? $this->random->getFloat(130, 2000) : $this->random->getFloat(2000, 20000), 'meter_persegi'],
            // 1 acre … 25 ha
            'PERTANIAN' => [$this->random->getFloat(4047, 250000), 'hektar'],
            default => [$this->random->getFloat(2000, 50000), $this->chance(50) ? 'hektar' : 'meter_persegi'],
        };
    }

    /**
     * Slightly irregular rotated quadrilateral of ≈ $areaM2 around $centre.
     *
     * @param  array{0: float, 1: float}  $centre
     */
    private function polygon(array $centre, float $areaM2): string
    {
        [$lon, $lat] = $centre;
        $ratio = $this->random->getFloat(0.5, 2.0);
        $w = sqrt($areaM2 * $ratio);
        $h = $areaM2 / $w;
        $angle = $this->random->getFloat(0, M_PI);
        $mPerDegLat = 110574.0;
        $mPerDegLon = 111320.0 * cos(deg2rad($lat));

        $corners = [[-$w / 2, -$h / 2], [$w / 2, -$h / 2], [$w / 2, $h / 2], [-$w / 2, $h / 2]];
        $pts = [];
        foreach ($corners as [$x, $y]) {
            $x *= $this->random->getFloat(0.92, 1.08);
            $y *= $this->random->getFloat(0.92, 1.08);
            $rx = $x * cos($angle) - $y * sin($angle);
            $ry = $x * sin($angle) + $y * cos($angle);
            $pts[] = sprintf('%.7F %.7F', $lon + $rx / $mPerDegLon, $lat + $ry / $mPerDegLat);
        }
        $pts[] = $pts[0];

        return 'POLYGON(('.implode(',', $pts).'))';
    }

    private function zoning(string $kategori): string
    {
        return match ($kategori) {
            'BANGUNAN' => $this->weighted(['Kediaman' => 50, 'Perniagaan' => 20, 'Institusi dan Kemudahan Masyarakat' => 30]),
            'PERUSAHAAN' => 'Industri',
            default => $this->weighted(['Pertanian' => 85, 'Tanah Lapang dan Rekreasi' => 15]),
        };
    }

    private function condition(string $kategori, ?string $jenisWakaf): string
    {
        if ($jenisWakaf === 'khas') {
            return $this->weighted(['Tapak masjid' => 30, 'Tapak surau' => 30, 'Tanah perkuburan Islam' => 25, 'Sekolah agama' => 15]);
        }

        return match ($kategori) {
            'BANGUNAN' => $this->weighted(['Bangunan kediaman' => 50, 'Bangunan perniagaan' => 25, 'Bangunan institusi agama' => 25]),
            'PERUSAHAAN' => $this->weighted(['Perusahaan ringan' => 70, 'Gudang' => 30]),
            default => $this->weighted(['Tanaman kelapa sawit' => 45, 'Tanaman getah' => 25, 'Tanaman padi' => 15, 'Dusun buah-buahan' => 15]),
        };
    }

    private function messyLotNumber(string $prefix, int $number): string
    {
        $formats = $prefix === 'PT'
            ? ['PT%d', 'P.T. %d', 'PT No. %d', 'pt %d']
            : ['LOT %d', 'Lot No. %d', 'No. Lot %d', 'L%d', 'lot%d'];

        return sprintf($formats[$this->random->getInt(0, count($formats) - 1)], $number);
    }

    /**
     * @phpstan-impure
     */
    private function chance(int $percent): bool
    {
        return $this->random->getInt(1, 100) <= $percent;
    }

    /**
     * @phpstan-impure
     *
     * @param  array<int|string, int>  $weights
     */
    private function weighted(array $weights): string
    {
        $r = $this->random->getInt(1, array_sum($weights));
        foreach ($weights as $value => $weight) {
            $r -= $weight;
            if ($r <= 0) {
                return (string) $value;
            }
        }

        return (string) array_key_last($weights);
    }

    private function uuid(string $key): string
    {
        return Uuid::uuid5(self::UUID_NS, $this->seed.':'.$key)->toString();
    }

    /**
     * @return array<string, mixed>
     */
    private function lineage(string $file, string $key, \DateTimeInterface $now): array
    {
        return [
            'sintetik' => true,
            'sumber_sistem' => self::SOURCE_SYSTEM,
            'fail_asal' => $file,
            'kunci_asal' => $key,
            'dimuat_pada' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
