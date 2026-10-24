<?php

namespace App\Modules\Gis\Layers;

class SempadanPbtLayer extends SempadanLayer
{
    public function key(): string
    {
        return 'gis.sempadan_pbt';
    }

    public function label(): string
    {
        return 'Sempadan PBT';
    }

    protected function jenis(): string
    {
        return 'pbt';
    }

    protected function warna(): string
    {
        return '#7C2D12';
    }
}
