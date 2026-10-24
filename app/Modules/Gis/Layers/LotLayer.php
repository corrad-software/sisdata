<?php

namespace App\Modules\Gis\Layers;

use App\Models\User;
use App\Modules\Gis\Services\LotScope;
use App\Modules\Pro\Contracts\LotRegistry;

/**
 * Base for thematic views of the lot polygons (MOD-PRO's lot_tanah + current
 * data_spatial, read-only). Scope = the same rule MOD-PRO applies (LotScope).
 * Subclasses add a thematic column and legend.
 */
abstract class LotLayer extends SqlLayer
{
    public const BASE_COLUMNS = ['id', 'label', 'pautan', 'no_lot', 'kod_daerah', 'kod_mukim', 'keluasan', 'unit_keluasan', 'kategori_guna_tanah', 'bahagian_pemilik', 'zoning', 'status_lot', 'klasifikasi', 'status_pengesahan'];

    public function __construct(protected LotScope $scope, protected LotRegistry $lots) {}

    public function group(): string
    {
        return 'hartanah';
    }

    public function geometryType(): string
    {
        return 'polygon';
    }

    public function permission(): string
    {
        return 'pro.lot.lihat';
    }

    /** Extra SELECT expressions ("expr AS name") — names must be listed in extraColumns(). */
    protected function extraSelect(): string
    {
        return '';
    }

    /** @return list<string> */
    protected function extraColumns(): array
    {
        return [];
    }

    protected function extraJoins(): string
    {
        return '';
    }

    public function columns(): array
    {
        return [...self::BASE_COLUMNS, ...$this->extraColumns()];
    }

    public function filterable(): array
    {
        return ['id', 'kod_daerah', 'kod_mukim', 'kategori_guna_tanah', 'bahagian_pemilik', 'status_lot', 'status_pengesahan', 'zoning', ...$this->extraColumns()];
    }

    public function source(User $user, array $filters = []): array
    {
        [$where, $bindings] = $this->scope->where($user, 'l');
        [$prefix, $suffix] = $this->linkParts();
        $extra = $this->extraSelect();
        $extra = $extra === '' ? '' : ', '.$extra;

        $sql = "SELECT l.id::text AS id, l.no_lot AS label, ?::text || l.id::text || ?::text AS pautan, l.no_lot, l.kod_daerah, l.kod_mukim,
                       l.keluasan::float8 AS keluasan, l.unit_keluasan, COALESCE(l.kategori_guna_tanah, 'TIADA') AS kategori_guna_tanah,
                       l.bahagian_pemilik, l.zoning, l.status_lot, l.klasifikasi, ds.status_pengesahan{$extra}, ds.geometri AS geom
                FROM lot_tanah l
                JOIN data_spatial ds ON ds.lot_id = l.id AND ds.semasa
                {$this->extraJoins()}
                WHERE {$where}";

        return [$sql, [$prefix, $suffix, ...$bindings]];
    }

    public function scopeSignature(User $user): string
    {
        return $this->scope->signature($user, $this->key());
    }

    /** @return array{0: string, 1: string} profile link around the lot id (SRS-GIS-09) */
    protected function linkParts(): array
    {
        $marker = '00000000-0000-0000-0000-000000000000';
        $link = $this->lots->profileLink($marker);
        $at = strpos($link, $marker);

        return $at === false ? [$link, ''] : [substr($link, 0, $at), substr($link, $at + strlen($marker))];
    }
}
