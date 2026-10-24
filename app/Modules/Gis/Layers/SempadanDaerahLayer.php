<?php

namespace App\Modules\Gis\Layers;

class SempadanDaerahLayer extends SempadanLayer
{
    public function key(): string
    {
        return 'gis.sempadan_daerah';
    }

    public function label(): string
    {
        return 'Sempadan Daerah';
    }

    protected function jenis(): string
    {
        return 'daerah';
    }

    protected function warna(): string
    {
        return '#1E3A8A';
    }
}
