<?php

namespace App\Modules\Dsh\Kpi;

use App\Models\User;
use App\Modules\Dsh\Contracts\KpiDrillDown;

/**
 * Acquisition KPIs over pemerolehan (MOD-PER, read-only), visible with
 * per.laporan.lihat and scoped like MOD-PER (daerah, bahagian, klasifikasi).
 * Drill-down lists cases → the case page.
 */
abstract class PemerolehanKpi extends DshKpi implements KpiDrillDown
{
    public const DALAM_PROSES = ['permohonan_awam_menunggu_semakan', 'dalam_proses', 'menunggu_kelulusan', 'diluluskan'];

    public function permission(): string
    {
        return 'per.laporan.lihat';
    }

    /** @return array{0: string, 1: list<mixed>} */
    protected function caseWhere(User $user, array $filters): array
    {
        [$sql, $b] = $this->scopedWhere($user, $filters, 'p', ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'bahagian' => 'kod_bahagian', 'klasifikasi' => 'klasifikasi']);
        $sql .= ' AND p.deleted_at IS NULL';
        if (! empty($filters['pegawai'])) {
            $sql .= ' AND p.id_pegawai_bertanggungjawab = ?';
            $b[] = (int) $filters['pegawai'];
        }
        if (! empty($filters['status'])) {
            $sql .= ' AND p.status_pemerolehan = ?';
            $b[] = (string) $filters['status'];
        }

        return [$sql, $b];
    }

    protected function inProcess(): string
    {
        return "p.status_pemerolehan IN ('".implode("','", self::DALAM_PROSES)."')";
    }

    /** @return array<string, string> */
    protected function channels(): array
    {
        return $this->names('saluran_pemerolehan', 'id', 'nama_saluran');
    }

    /** Condition selecting this KPI's cases (drill-down and compute). */
    abstract protected function condition(array $filters): string;

    public function records(User $user, array $filters, ?string $slice, int $page = 1, int $perPage = 25): array
    {
        [$where, $b] = $this->caseWhere($user, $filters);
        $where .= ' AND '.$this->condition($filters);
        [$dim, $value] = $this->slice($slice);
        $column = ['saluran' => 'id_saluran', 'daerah' => 'kod_daerah', 'tahun' => null][$dim] ?? null;
        if ($column !== null) {
            $where .= $value === null ? " AND p.{$column} IS NULL" : " AND p.{$column}::text = ?";
            if ($value !== null) {
                $b[] = $value;
            }
        }
        if ($dim === 'tahun' && $value !== null && ctype_digit($value)) {
            $where .= ' AND extract(year FROM p.tarikh_pemerolehan) = ?';
            $b[] = (int) $value;
        }
        [$rows, $total] = $this->page("FROM pemerolehan p WHERE {$where}", $b,
            'p.id, p.no_kes, p.tajuk, p.id_saluran, p.kod_daerah, p.status_pemerolehan, p.peringkat_semasa, p.tarikh_mula_kes, p.tarikh_pemerolehan',
            'p.tarikh_mula_kes DESC NULLS LAST, p.no_kes', $page, $perPage);
        $daerah = $this->names('ref_daerah');
        $saluran = $this->channels();

        return [
            'lajur' => [
                ['kod' => 'no_kes', 'label' => __('dsh::dsh.lajur.no_kes')],
                ['kod' => 'tajuk', 'label' => __('dsh::dsh.lajur.tajuk')],
                ['kod' => 'saluran', 'label' => __('dsh::dsh.lajur.saluran')],
                ['kod' => 'daerah', 'label' => __('dsh::dsh.lajur.daerah')],
                ['kod' => 'status', 'label' => __('dsh::dsh.lajur.status')],
                ['kod' => 'tarikh_mula', 'label' => __('dsh::dsh.lajur.tarikh_mula')],
            ],
            'baris' => array_map(fn ($r) => [
                'pautan' => '/admin/per/kes/'.$r->id,
                'sel' => [
                    'no_kes' => $r->no_kes,
                    'tajuk' => $r->tajuk,
                    'saluran' => $saluran[(string) $r->id_saluran] ?? $r->id_saluran,
                    'daerah' => $daerah[(string) $r->kod_daerah] ?? $r->kod_daerah,
                    'status' => str_replace('_', ' ', (string) $r->status_pemerolehan),
                    'tarikh_mula' => $r->tarikh_mula_kes,
                ],
            ], $rows),
            'jumlah' => $total,
        ];
    }
}
