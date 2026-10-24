<?php

namespace App\Modules\Gis\Layers;

use App\Modules\Gis\Support\Palet;

/** Lot polygons of MAIS, coloured by owning division (hartanah / wakaf / baitulmal) — SRS-GIS-01. */
class LotBahagianLayer extends LotLayer
{
    public function key(): string
    {
        return 'gis.lot';
    }

    public function label(): string
    {
        return 'Lot Tanah MAIS';
    }

    public function legend(): array
    {
        return ['medan' => 'bahagian_pemilik', 'entri' => Palet::entries(Palet::BAHAGIAN_PEMILIK)];
    }
}
