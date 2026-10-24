<?php

namespace App\Modules\Dsh\Kpi;

use App\Models\User;
use App\Modules\Dsh\Contracts\KpiDrillDown;

/**
 * Portfolio KPIs over lot_tanah (MOD-PRO, read-only). Drill-down lists the
 * lots behind the value / slice, each linking to its Profil Hartanah 360°.
 */
abstract class LotKpi extends DshKpi implements KpiDrillDown
{
    public const HEKTAR = "CASE l.unit_keluasan WHEN 'hektar' THEN l.keluasan WHEN 'ekar' THEN l.keluasan * 0.40468564224 ELSE l.keluasan / 10000.0 END";

    public const BAHAGIAN = ['hartanah' => 'Hartanah', 'wakaf' => 'Wakaf', 'baitulmal' => 'Baitulmal'];

    public const STATUS_LOT = ['draf' => 'Draf', 'aktif' => 'Aktif', 'tidak_aktif' => 'Tidak aktif'];

    public function domain(): string
    {
        return 'portfolio';
    }

    public function permission(): string
    {
        return 'pro.lot.lihat';
    }

    /** Lot column behind each slice dimension. */
    protected const DIMENSI = ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'kategori' => 'kategori_guna_tanah', 'bahagian' => 'bahagian_pemilik', 'status' => 'status_lot'];

    public function records(User $user, array $filters, ?string $slice, int $page = 1, int $perPage = 25): array
    {
        [$where, $b] = $this->lotWhere($user, $filters);
        [$dim, $value] = $this->slice($slice);
        if ($dim !== null && isset(self::DIMENSI[$dim])) {
            $where .= $value === null ? ' AND l.'.self::DIMENSI[$dim].' IS NULL' : ' AND l.'.self::DIMENSI[$dim].' = ?';
            if ($value !== null) {
                $b[] = $value;
            }
        }
        [$rows, $total] = $this->page("FROM lot_tanah l WHERE {$where}", $b,
            'l.id, l.no_lot, l.kod_daerah, l.kod_mukim, l.kategori_guna_tanah, l.bahagian_pemilik, l.status_lot, '.self::HEKTAR.' AS hektar',
            'l.kod_daerah, l.no_lot', $page, $perPage);

        $daerah = $this->names('ref_daerah');
        $mukim = $this->names('ref_mukim');
        $kategori = $this->names('ref_kategori_guna_tanah');

        return [
            'lajur' => [
                ['kod' => 'no_lot', 'label' => __('dsh::dsh.lajur.no_lot')],
                ['kod' => 'daerah', 'label' => __('dsh::dsh.lajur.daerah')],
                ['kod' => 'mukim', 'label' => __('dsh::dsh.lajur.mukim')],
                ['kod' => 'kategori', 'label' => __('dsh::dsh.lajur.kategori')],
                ['kod' => 'bahagian', 'label' => __('dsh::dsh.lajur.bahagian')],
                ['kod' => 'hektar', 'label' => __('dsh::dsh.lajur.hektar')],
            ],
            'baris' => array_map(fn ($r) => [
                'pautan' => '/admin/pro/lot/'.$r->id,
                'sel' => [
                    'no_lot' => $r->no_lot,
                    'daerah' => $daerah[$r->kod_daerah] ?? $r->kod_daerah,
                    'mukim' => $mukim[$r->kod_mukim] ?? $r->kod_mukim,
                    'kategori' => $kategori[(string) $r->kategori_guna_tanah] ?? $r->kategori_guna_tanah,
                    'bahagian' => self::BAHAGIAN[(string) $r->bahagian_pemilik] ?? $r->bahagian_pemilik,
                    'hektar' => $r->hektar === null ? null : round((float) $r->hektar, 4),
                ],
            ], $rows),
            'jumlah' => $total,
        ];
    }

    /** Last change on the lots in view (data freshness, SRS-DSH-12). */
    protected function lastUpdate(string $where, array $b): ?string
    {
        return $this->iso($this->scalar("SELECT max(l.updated_at) FROM lot_tanah l WHERE {$where}", $b));
    }
}
