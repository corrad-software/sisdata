<?php

namespace App\Modules\Gis\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Distribution of MAIS land by daerah / mukim / category (SRS-GIS-34 and the
 * "taburan aset" report), within the user's lot scope; optionally with the
 * boundary geometry for a choropleth map.
 */
class Statistik
{
    public function __construct(private LotScope $scope) {}

    /**
     * @param  'daerah'|'mukim'  $level
     * @param  array<string, mixed>  $filters  kod_daerah, bahagian_pemilik, kategori_guna_tanah
     * @return array{peringkat: string, baris: list<array<string, mixed>>, jumlah: array<string, mixed>, kategori: list<array<string, mixed>>, bahagian: list<array<string, mixed>>}
     */
    public function distribution(User $user, string $level = 'daerah', array $filters = [], bool $withGeometry = false): array
    {
        [$where, $bindings] = $this->scope->where($user, 'l');
        foreach (['kod_daerah', 'bahagian_pemilik', 'kategori_guna_tanah'] as $f) {
            if (! empty($filters[$f]) && is_string($filters[$f])) {
                $where .= " AND l.{$f} = ?";
                $bindings[] = $filters[$f];
            }
        }
        $column = $level === 'mukim' ? 'kod_mukim' : 'kod_daerah';

        $geometry = $withGeometry ? ', ST_AsGeoJSON(ST_SimplifyPreserveTopology(s.geometri, 0.0008), 6) AS geometri' : '';
        $rows = DB::select(
            "WITH b AS (
                SELECT l.{$column} AS kod, count(*) AS bilangan_lot, count(ds.id) AS ada_geometri,
                       COALESCE(sum(ds.keluasan_dikira), 0) AS luas_m2,
                       count(*) FILTER (WHERE l.bahagian_pemilik = 'wakaf') AS wakaf,
                       count(*) FILTER (WHERE l.bahagian_pemilik = 'baitulmal') AS baitulmal,
                       count(*) FILTER (WHERE l.bahagian_pemilik = 'hartanah') AS hartanah
                FROM lot_tanah l LEFT JOIN data_spatial ds ON ds.lot_id = l.id AND ds.semasa
                WHERE {$where} GROUP BY l.{$column}
             )
             SELECT b.*, s.nama{$geometry} FROM b LEFT JOIN ref_sempadan s ON s.jenis = ? AND s.kod = b.kod ORDER BY b.bilangan_lot DESC",
            [...$bindings, $level === 'mukim' ? 'mukim' : 'daerah'],
        );

        $baris = array_map(function ($r) {
            $row = (array) $r;
            foreach (['bilangan_lot', 'ada_geometri', 'wakaf', 'baitulmal', 'hartanah'] as $f) {
                $row[$f] = (int) $row[$f];
            }
            $row['luas_m2'] = round((float) $row['luas_m2'], 2);
            $row['luas_hektar'] = round($row['luas_m2'] / 10000, 4);
            if (isset($row['geometri'])) {
                $row['geometri'] = json_decode((string) $row['geometri'], true);
            }

            return $row;
        }, $rows);

        $group = fn (string $col) => array_map(fn ($r) => ['kod' => $r->kod, 'bilangan' => (int) $r->bilangan], DB::select(
            "SELECT COALESCE(l.{$col}, 'TIADA') AS kod, count(*) AS bilangan FROM lot_tanah l WHERE {$where} GROUP BY 1 ORDER BY 2 DESC",
            $bindings,
        ));

        return [
            'peringkat' => $level,
            'baris' => $baris,
            'jumlah' => [
                'bilangan_lot' => array_sum(array_column($baris, 'bilangan_lot')),
                'ada_geometri' => array_sum(array_column($baris, 'ada_geometri')),
                'luas_hektar' => round(array_sum(array_column($baris, 'luas_m2')) / 10000, 4),
            ],
            'kategori' => $group('kategori_guna_tanah'),
            'bahagian' => $group('bahagian_pemilik'),
        ];
    }
}
