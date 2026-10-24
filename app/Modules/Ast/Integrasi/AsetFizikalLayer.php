<?php

namespace App\Modules\Ast\Integrasi;

use App\Models\User;
use App\Modules\Ast\Support\Lalai;
use App\Modules\Ast\Support\SkopSql;
use App\Modules\Gis\Contracts\ClassifiedMapLayer;
use App\Modules\Gis\Contracts\VectorTileLayer;
use Illuminate\Support\Facades\DB;

/**
 * SRS-AST-13 / SRS-GIS-11 mandatory layer 10 "Aset Fizikal" (key prefix
 * `ast.` → replaces MOD-GIS's interim gis.aset_fizikal): asset points
 * coloured by condition, filterable by category, condition and district;
 * each point links to the asset profile and to its lot (pautan_lot).
 * Same RBC scope as the asset record (SkopSql).
 */
class AsetFizikalLayer implements ClassifiedMapLayer, VectorTileLayer
{
    public const COLUMNS = ['id', 'label', 'pautan', 'no_rujukan_aset', 'kategori', 'kategori_nama', 'jenis_aset', 'status_kondisi', 'kod_daerah', 'kod_mukim', 'lot_id', 'no_lot', 'pautan_lot', 'tarikh_pemeriksaan_terakhir'];

    public function __construct(private SkopSql $skop) {}

    public function key(): string
    {
        return 'ast.aset_fizikal';
    }

    public function label(): string
    {
        return 'Aset Fizikal';
    }

    public function group(): string
    {
        return 'operasi';
    }

    public function geometryType(): string
    {
        return 'point';
    }

    public function permission(): string
    {
        return 'ast.peta.lihat';
    }

    public function klasifikasi(): string
    {
        return 'dalaman';
    }

    public function legend(): array
    {
        return ['medan' => 'status_kondisi', 'entri' => array_map(fn ($k) => ['nilai' => $k, 'label' => Lalai::KONDISI[$k], 'warna' => Lalai::WARNA_KONDISI[$k]], array_keys(Lalai::KONDISI))];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: string, 1: list<mixed>}
     */
    public function source(User $user, array $filters = []): array
    {
        [$where, $b] = $this->skop->where($user, 'a', $filters);

        return ["SELECT a.id::text AS id, a.nama_aset AS label, '/admin/ast/aset/' || a.id::text AS pautan, a.no_rujukan_aset,
                    k.kod AS kategori, k.nama_kategori AS kategori_nama, a.jenis_aset, a.status_kondisi, a.kod_daerah, a.kod_mukim,
                    a.lot_id::text AS lot_id, l.no_lot, '/admin/pro/lot/' || a.lot_id::text AS pautan_lot,
                    a.tarikh_pemeriksaan_terakhir::text AS tarikh_pemeriksaan_terakhir, a.koordinat AS geom
                FROM aset_fizikal a
                JOIN aset_kategori k ON k.id = a.id_kategori
                JOIN lot_tanah l ON l.id = a.lot_id
                WHERE a.koordinat IS NOT NULL AND {$where}", $b];
    }

    public function tileSource(User $user, array $filters = []): array
    {
        [$sql, $b] = $this->source($user, $filters);

        return ['sql' => $sql, 'bindings' => $b, 'columns' => self::COLUMNS];
    }

    public function scopeSignature(User $user): string
    {
        return $this->skop->signature($user, $this->key());
    }

    public function features(User $user, ?array $bbox = null, array $filters = [], int $limit = 5000): array
    {
        if (! $user->can($this->permission())) {
            return ['type' => 'FeatureCollection', 'features' => []];
        }
        [$sql, $b] = $this->source($user, $filters);
        $q = 'SELECT s.*, ST_AsGeoJSON(s.geom, 7) AS _g FROM ('.$sql.') s';
        if ($bbox !== null) {
            $q .= ' WHERE s.geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)';
            array_push($b, ...array_map('floatval', $bbox));
        }
        $q .= ' LIMIT '.max(1, min($limit, 50000));
        $features = [];
        foreach (DB::select($q, $b) as $row) {
            $p = (array) $row;
            $g = json_decode((string) $p['_g'], true);
            unset($p['_g'], $p['geom']);
            $p['warna'] = Lalai::WARNA_KONDISI[$p['status_kondisi']] ?? '#9CA3AF';
            $features[] = ['type' => 'Feature', 'id' => $p['id'], 'geometry' => $g, 'properties' => $p];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }
}
