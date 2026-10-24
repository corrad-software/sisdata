<?php

namespace App\Modules\Gis\Layers;

use App\Models\User;
use App\Modules\Gis\Services\LotScope;

/**
 * Accepted imported datasets (SRS-GIS-22/23: JPBD zoning, JUPEM boundaries,
 * infrastructure, risk areas…) as one overlay layer, filterable by
 * `id_import`; usable in overlay / proximity analysis.
 */
class SetDataLayer extends SqlLayer
{
    public const PALET = [
        'zoning' => ['#F59E0B', 'Zoning (JPBD / PBT)'],
        'infrastruktur' => ['#0EA5E9', 'Infrastruktur'],
        'risiko' => ['#E11D48', 'Kawasan risiko'],
        'sempadan' => ['#4F46E5', 'Sempadan rasmi'],
        'lot' => ['#10B981', 'Lot (pelan ukur)'],
        'umum' => ['#6B7280', 'Umum'],
    ];

    public function __construct(private LotScope $scope) {}

    public function key(): string
    {
        return 'gis.set_data';
    }

    public function label(): string
    {
        return 'Set Data Diimport';
    }

    public function group(): string
    {
        return 'rujukan';
    }

    public function geometryType(): string
    {
        return 'polygon';
    }

    public function permission(): string
    {
        return 'gis.peta.lihat';
    }

    public function columns(): array
    {
        return ['id', 'label', 'pautan', 'id_import', 'nama_set_data', 'kategori', 'no_ciri', 'lot_id'];
    }

    public function filterable(): array
    {
        return ['id_import', 'kategori'];
    }

    public function source(User $user, array $filters = []): array
    {
        $levels = $this->scope->levels($user);

        return ["SELECT c.id::text AS id, COALESCE(c.atribut->>'nama', c.atribut->>'name', c.atribut->>'no_lot', i.nama_set_data || ' #' || c.no_ciri) AS label,
                        NULL::text AS pautan, i.id::text AS id_import, i.nama_set_data, i.kategori, c.no_ciri, c.lot_id::text AS lot_id, c.geom
                 FROM gis_ciri_import c JOIN gis_log_import i ON i.id = c.id_import
                 WHERE i.status = 'diterima' AND c.sah AND i.klasifikasi IN (".implode(',', array_fill(0, count($levels), '?')).')', $levels];
    }

    public function scopeSignature(User $user): string
    {
        return substr(sha1(implode(',', $this->scope->levels($user)).$this->key()), 0, 16);
    }

    public function legend(): array
    {
        $entries = [];
        foreach (self::PALET as $value => [$colour, $label]) {
            $entries[] = ['nilai' => $value, 'label' => $label, 'warna' => $colour];
        }

        return ['medan' => 'kategori', 'entri' => $entries];
    }
}
