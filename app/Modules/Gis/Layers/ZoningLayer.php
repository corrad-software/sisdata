<?php

namespace App\Modules\Gis\Layers;

/**
 * Mandatory layer 3 — Zoning, from lot_tanah.zoning (until JPBD / local-plan
 * zoning datasets are imported as their own dataset layer). Palette follows
 * the usual local-plan colours; thick outline is applied by the UI style.
 */
class ZoningLayer extends LotLayer
{
    public const PALET = [
        'KEDIAMAN' => ['#FACC15', 'Kediaman'],
        'PERNIAGAAN' => ['#EF4444', 'Perniagaan'],
        'INDUSTRI' => ['#A855F7', 'Industri'],
        'INSTITUSI' => ['#3B82F6', 'Institusi / Kemudahan Masyarakat'],
        'TANAH_LAPANG' => ['#22C55E', 'Tanah Lapang & Rekreasi'],
        'PERTANIAN' => ['#84CC16', 'Pertanian'],
        'BERCAMPUR' => ['#F97316', 'Pembangunan Bercampur'],
        'TIADA' => ['#A1A1AA', 'Tiada maklumat zon'],
    ];

    public function key(): string
    {
        return 'gis.zoning';
    }

    public function label(): string
    {
        return 'Zoning';
    }

    protected function extraSelect(): string
    {
        return "COALESCE(NULLIF(regexp_replace(upper(trim(l.zoning)), '[^A-Z]+', '_', 'g'), ''), 'TIADA') AS zon";
    }

    protected function extraColumns(): array
    {
        return ['zon'];
    }

    public function legend(): array
    {
        $entries = [];
        foreach (self::PALET as $value => [$colour, $label]) {
            $entries[] = ['nilai' => $value, 'label' => $label, 'warna' => $colour];
        }

        return ['medan' => 'zon', 'entri' => $entries];
    }
}
