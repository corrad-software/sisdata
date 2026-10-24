<?php

namespace App\Modules\Gis\Layers;

use App\Modules\Gis\Support\PenunjukLot as P;

/**
 * Mandatory layer 9 — Risiko Hartanah (interim, until MOD-LAP publishes its
 * analytic risk layer): a weighted score from indicators already in the
 * system — active court case (3), overlap (2), tax arrears (2), open
 * complaint (1), expired lease still active (1), no active title (1),
 * unverified geometry (1). Rendered as heat map + graded polygons. Sulit.
 */
class RisikoLayer extends LotLayer
{
    public const PALET = [
        'rendah' => ['#16A34A', 'Rendah (0–1)'],
        'sederhana' => ['#EAB308', 'Sederhana (2–3)'],
        'tinggi' => ['#F97316', 'Tinggi (4–5)'],
        'kritikal' => ['#DC2626', 'Kritikal (6+)'],
    ];

    public function key(): string
    {
        return 'gis.risiko';
    }

    public function label(): string
    {
        return 'Risiko Hartanah';
    }

    public function group(): string
    {
        return 'risiko';
    }

    public function klasifikasi(): string
    {
        return 'sulit';
    }

    private function score(): string
    {
        $weights = [
            [P::kesAktif(), 3], [P::bertindih(), 2], [P::cukaiTertunggak(), 2], [P::aduanTerbuka(), 1],
            [P::pajakanTamat(), 1], [P::tanpaHakMilik(), 1], [P::belumDisahkan(), 1],
        ];

        return '('.implode(' + ', array_map(fn ($w) => "(CASE WHEN {$w[0]} THEN {$w[1]} ELSE 0 END)", $weights)).')';
    }

    protected function extraJoins(): string
    {
        return 'CROSS JOIN LATERAL (SELECT '.$this->score().' AS skor) r';
    }

    protected function extraSelect(): string
    {
        return "r.skor AS skor_risiko, CASE WHEN r.skor >= 6 THEN 'kritikal' WHEN r.skor >= 4 THEN 'tinggi' WHEN r.skor >= 2 THEN 'sederhana' ELSE 'rendah' END AS tahap_risiko";
    }

    protected function extraColumns(): array
    {
        return ['skor_risiko', 'tahap_risiko'];
    }

    public function legend(): array
    {
        $entries = [];
        foreach (self::PALET as $value => [$colour, $label]) {
            $entries[] = ['nilai' => $value, 'label' => $label, 'warna' => $colour];
        }

        return ['medan' => 'tahap_risiko', 'entri' => $entries];
    }
}
