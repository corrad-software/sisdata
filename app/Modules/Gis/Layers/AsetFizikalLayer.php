<?php

namespace App\Modules\Gis\Layers;

use App\Models\User;
use App\Modules\Gis\Services\LotScope;

/**
 * Mandatory layer 10 — Aset Fizikal: asset points kept in gis_aset_titik
 * until MOD-AST registers its own layer; colour = condition status.
 */
class AsetFizikalLayer extends SqlLayer
{
    public function __construct(private LotScope $scope) {}

    public function key(): string
    {
        return 'gis.aset_fizikal';
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
        return 'gis.peta.lihat';
    }

    public function columns(): array
    {
        return ['id', 'label', 'pautan', 'kategori', 'status_keadaan', 'kod_daerah', 'kod_mukim', 'id_lot'];
    }

    public function filterable(): array
    {
        return ['kategori', 'status_keadaan', 'kod_daerah', 'kod_mukim'];
    }

    public function source(User $user, array $filters = []): array
    {
        [$where, $bindings] = $this->scope->districtWhere($user, 'a');
        $levels = $this->scope->levels($user);

        return ["SELECT a.id::text AS id, a.nama AS label, CASE WHEN a.id_lot IS NULL THEN NULL ELSE '/admin/pro/lot/' || a.id_lot::text END AS pautan,
                        a.kategori, a.status_keadaan, a.kod_daerah, a.kod_mukim, a.id_lot::text AS id_lot, a.geom
                 FROM gis_aset_titik a
                 WHERE a.deleted_at IS NULL AND {$where} AND a.klasifikasi IN (".implode(',', array_fill(0, count($levels), '?')).')',
            [...$bindings, ...$levels]];
    }

    public function scopeSignature(User $user): string
    {
        return $this->scope->signature($user, $this->key());
    }

    public function legend(): array
    {
        return ['medan' => 'status_keadaan', 'entri' => [
            ['nilai' => 'baik', 'label' => 'Baik', 'warna' => '#16A34A'],
            ['nilai' => 'perlu_perhatian', 'label' => 'Perlu perhatian', 'warna' => '#EAB308'],
            ['nilai' => 'kritikal', 'label' => 'Kritikal', 'warna' => '#DC2626'],
        ]];
    }
}
