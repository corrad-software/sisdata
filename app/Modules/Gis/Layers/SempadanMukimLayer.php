<?php

namespace App\Modules\Gis\Layers;

/** Mukim boundaries — approximate (Voronoi from OSM places, flagged `anggaran`), not surveyed. */
class SempadanMukimLayer extends SempadanLayer
{
    public function key(): string
    {
        return 'gis.sempadan_mukim';
    }

    public function label(): string
    {
        return 'Sempadan Mukim (anggaran)';
    }

    protected function jenis(): string
    {
        return 'mukim';
    }

    protected function warna(): string
    {
        return '#0E7490';
    }
}
