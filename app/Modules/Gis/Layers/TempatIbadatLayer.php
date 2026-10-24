<?php

namespace App\Modules\Gis\Layers;

use App\Models\User;

/** Mosques / surau from MOD-REF (OSM) — "lapisan kemudahan awam" (SRS-GIS-14) and proximity target (GIS-19). */
class TempatIbadatLayer extends SqlLayer
{
    public function key(): string
    {
        return 'gis.tempat_ibadat';
    }

    public function label(): string
    {
        return 'Masjid & Surau';
    }

    public function group(): string
    {
        return 'rujukan';
    }

    public function geometryType(): string
    {
        return 'point';
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
        return ['id', 'label', 'pautan', 'jenis', 'daerah_kod', 'mukim_kod'];
    }

    public function filterable(): array
    {
        return ['jenis', 'daerah_kod', 'mukim_kod'];
    }

    public function source(User $user, array $filters = []): array
    {
        return ['SELECT t.id::text AS id, COALESCE(t.nama, initcap(t.jenis)) AS label, NULL::text AS pautan, t.jenis, t.daerah_kod, t.mukim_kod, t.geometri AS geom
                 FROM ref_tempat_ibadat t', []];
    }

    public function legend(): array
    {
        return ['medan' => 'jenis', 'entri' => [
            ['nilai' => 'masjid', 'label' => 'Masjid', 'warna' => '#047857'],
            ['nilai' => 'surau', 'label' => 'Surau', 'warna' => '#0D9488'],
            ['nilai' => 'lain', 'label' => 'Lain-lain', 'warna' => '#64748B'],
        ]];
    }
}
