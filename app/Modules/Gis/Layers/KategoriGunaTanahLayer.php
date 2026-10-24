<?php

namespace App\Modules\Gis\Layers;

use App\Modules\Gis\Support\Palet;

/** Mandatory layer 2 — Kategori Guna Tanah (lot_tanah.kategori_guna_tanah), shared legend.ts palette. */
class KategoriGunaTanahLayer extends LotLayer
{
    public function key(): string
    {
        return 'gis.kategori_guna_tanah';
    }

    public function label(): string
    {
        return 'Kategori Guna Tanah';
    }

    public function legend(): array
    {
        return ['medan' => 'kategori_guna_tanah', 'entri' => Palet::entries(Palet::KATEGORI_GUNA_TANAH)];
    }
}
