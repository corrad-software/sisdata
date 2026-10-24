<?php

namespace App\Modules\Gis\Layers;

use App\Models\User;

/**
 * Administrative boundaries from MOD-REF's ref_sempadan (OSM, ODbL) —
 * SRS-GIS-01 / GIS-14 "lapisan sempadan pentadbiran". Public reference data.
 */
abstract class SempadanLayer extends SqlLayer
{
    /** negeri | daerah | mukim | pbt */
    abstract protected function jenis(): string;

    abstract protected function warna(): string;

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

    public function klasifikasi(): string
    {
        return 'awam';
    }

    public function columns(): array
    {
        return ['id', 'label', 'pautan', 'kod', 'nama', 'jenis', 'luas_km2', 'anggaran'];
    }

    public function filterable(): array
    {
        return ['kod'];
    }

    public function source(User $user, array $filters = []): array
    {
        return ['SELECT s.kod AS id, s.nama AS label, NULL::text AS pautan, s.kod, s.nama, s.jenis, s.luas_km2::float8 AS luas_km2, s.anggaran, s.geometri AS geom
                 FROM ref_sempadan s WHERE s.jenis = ?', [$this->jenis()]];
    }

    public function legend(): array
    {
        return ['medan' => null, 'entri' => [['nilai' => '*', 'label' => $this->label(), 'warna' => $this->warna()]]];
    }
}
