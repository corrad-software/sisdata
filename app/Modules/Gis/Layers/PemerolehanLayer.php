<?php

namespace App\Modules\Gis\Layers;

use App\Models\User;
use App\Modules\Gis\Services\LotScope;
use App\Modules\Gis\Support\Jadual;
use App\Modules\Gis\Support\Palet;

/**
 * Acquisition cases (MOD-PER, read-only) as points coloured by channel
 * (legend-pemerolehan.ts). Position: the lot's centre when the user may see
 * the lot, otherwise the mukim's centre — a hidden lot's location never leaks.
 * Sulit (SRS §4.2: pemerolehan).
 */
class PemerolehanLayer extends SqlLayer
{
    public function __construct(private LotScope $scope) {}

    public function key(): string
    {
        return 'gis.pemerolehan';
    }

    public function label(): string
    {
        return 'Pemerolehan mengikut Saluran';
    }

    public function group(): string
    {
        return 'hartanah';
    }

    public function geometryType(): string
    {
        return 'point';
    }

    public function permission(): string
    {
        return 'per.kes.lihat';
    }

    public function klasifikasi(): string
    {
        return 'sulit';
    }

    public function columns(): array
    {
        return ['id', 'label', 'pautan', 'tajuk', 'saluran', 'nama_saluran', 'status_pemerolehan', 'kod_daerah', 'kod_mukim', 'lot_id'];
    }

    public function filterable(): array
    {
        return ['saluran', 'status_pemerolehan', 'kod_daerah', 'kod_mukim'];
    }

    public function source(User $user, array $filters = []): array
    {
        if (! Jadual::has('pemerolehan', ['id', 'no_kes', 'id_saluran', 'lot_id', 'kod_daerah', 'kod_mukim', 'status_pemerolehan']) || ! Jadual::has('saluran_pemerolehan', ['kod_saluran'])) {
            return ['SELECT NULL::text AS id, NULL::text AS label, NULL::text AS pautan, NULL::text AS tajuk, NULL::text AS saluran, NULL::text AS nama_saluran, NULL::text AS status_pemerolehan, NULL::text AS kod_daerah, NULL::text AS kod_mukim, NULL::text AS lot_id, NULL::geometry AS geom WHERE FALSE', []];
        }
        [$lotWhere, $lotBindings] = $this->scope->where($user, 'l');
        [$where, $bindings] = $this->scope->districtWhere($user, 'p');
        $deleted = Jadual::has('pemerolehan', ['deleted_at']) ? ' AND p.deleted_at IS NULL' : '';

        $sql = "SELECT p.id::text AS id, p.no_kes AS label, '/admin/per/kes/' || p.id::text AS pautan, p.tajuk, s.kod_saluran AS saluran,
                       s.nama_saluran, p.status_pemerolehan, p.kod_daerah, p.kod_mukim, l.id::text AS lot_id,
                       COALESCE(l.koordinat_pusat, ST_PointOnSurface(ds.geometri), ST_PointOnSurface(m.geometri)) AS geom
                FROM pemerolehan p
                JOIN saluran_pemerolehan s ON s.id = p.id_saluran
                LEFT JOIN lot_tanah l ON l.id = p.lot_id AND {$lotWhere}
                LEFT JOIN data_spatial ds ON ds.lot_id = l.id AND ds.semasa
                LEFT JOIN ref_sempadan m ON m.jenis = 'mukim' AND m.kod = p.kod_mukim
                WHERE {$where}{$deleted}";

        return [$sql, [...$lotBindings, ...$bindings]];
    }

    public function scopeSignature(User $user): string
    {
        return $this->scope->signature($user, $this->key());
    }

    public function legend(): array
    {
        return ['medan' => 'saluran', 'entri' => Palet::entries(Palet::SALURAN)];
    }
}
