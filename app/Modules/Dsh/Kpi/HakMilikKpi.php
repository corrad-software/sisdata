<?php

namespace App\Modules\Dsh\Kpi;

use App\Models\User;
use App\Modules\Dsh\Contracts\KpiDrillDown;

/**
 * Title KPIs over hak_milik (MOD-HKM, read-only), visible with
 * hkm.hak_milik.lihat and scoped like MOD-HKM's own access rule (daerah,
 * mukim, klasifikasi). Drill-down lists titles → the title record page.
 */
abstract class HakMilikKpi extends DshKpi implements KpiDrillDown
{
    /** Titles no longer in force. */
    protected const TIDAK_BERKUAT_KUASA = ['diganti', 'dilupuskan'];

    public function permission(): string
    {
        return 'hkm.hak_milik.lihat';
    }

    /** @return array{0: string, 1: list<mixed>} */
    protected function titleWhere(User $user, array $filters): array
    {
        [$sql, $b] = $this->scopedWhere($user, $filters, 'h', ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi']);
        $sql .= ' AND h.deleted_at IS NULL';
        foreach (['bahagian' => 'bahagian_pemilik', 'kategori' => 'kategori_guna_tanah'] as $key => $column) {
            if (! empty($filters[$key])) {
                $sql .= " AND h.lot_id IN (SELECT id FROM lot_tanah WHERE {$column} = ?)";
                $b[] = (string) $filters[$key];
            }
        }
        if (! empty($filters['status'])) {
            $sql .= ' AND h.status_semasa = ?';
            $b[] = (string) $filters['status'];
        }

        return [$sql, $b];
    }

    protected function inForce(): string
    {
        return "coalesce(h.status_semasa, '') NOT IN ('".implode("','", self::TIDAK_BERKUAT_KUASA)."')";
    }

    /** @return array<string, string> status kod => nama, from MOD-HKM's configurable list (ADM parameter) */
    protected function statusLabels(): array
    {
        $raw = $this->parameters->get('hkm.senarai_status_tanah', []);
        $raw = is_string($raw) ? (json_decode($raw, true) ?: []) : (array) $raw;
        $out = [];
        foreach ($raw as $row) {
            if (is_array($row) && isset($row['kod'], $row['nama'])) {
                $out[(string) $row['kod']] = (string) $row['nama'];
            }
        }

        return $out;
    }

    /** Extra condition for the drill-down of this KPI (e.g. "expiring soon"). */
    protected function drillCondition(): string
    {
        return $this->inForce();
    }

    public function records(User $user, array $filters, ?string $slice, int $page = 1, int $perPage = 25): array
    {
        [$where, $b] = $this->titleWhere($user, $filters);
        $where .= ' AND '.$this->drillCondition();
        [$dim, $value] = $this->slice($slice);
        $column = ['jenis' => 'jenis_hak_milik', 'status' => 'status_semasa', 'daerah' => 'kod_daerah'][$dim] ?? null;
        if ($column !== null) {
            $where .= $value === null ? " AND h.{$column} IS NULL" : " AND h.{$column} = ?";
            if ($value !== null) {
                $b[] = $value;
            }
        }
        [$rows, $total] = $this->page("FROM hak_milik h WHERE {$where}", $b,
            'h.id, h.no_hak_milik, h.jenis_hak_milik, h.kod_daerah, h.status_semasa, h.tarikh_tamat_tempoh, h.disahkan',
            'h.kod_daerah, h.no_hak_milik', $page, $perPage);
        $daerah = $this->names('ref_daerah');
        $jenis = $this->names('ref_jenis_hak_milik');
        $status = $this->statusLabels();

        return [
            'lajur' => [
                ['kod' => 'no_hak_milik', 'label' => __('dsh::dsh.lajur.no_hak_milik')],
                ['kod' => 'jenis', 'label' => __('dsh::dsh.lajur.jenis')],
                ['kod' => 'daerah', 'label' => __('dsh::dsh.lajur.daerah')],
                ['kod' => 'status', 'label' => __('dsh::dsh.lajur.status')],
                ['kod' => 'tarikh_tamat', 'label' => __('dsh::dsh.lajur.tarikh_tamat')],
                ['kod' => 'disahkan', 'label' => __('dsh::dsh.lajur.disahkan')],
            ],
            'baris' => array_map(fn ($r) => [
                'pautan' => '/admin/hkm/hak-milik/'.$r->id,
                'sel' => [
                    'no_hak_milik' => $r->no_hak_milik,
                    'jenis' => $jenis[(string) $r->jenis_hak_milik] ?? $r->jenis_hak_milik,
                    'daerah' => $daerah[(string) $r->kod_daerah] ?? $r->kod_daerah,
                    'status' => $status[(string) $r->status_semasa] ?? $r->status_semasa,
                    'tarikh_tamat' => $r->tarikh_tamat_tempoh,
                    'disahkan' => $r->disahkan ? __('dsh::dsh.ya') : __('dsh::dsh.tidak'),
                ],
            ], $rows),
            'jumlah' => $total,
        ];
    }
}
