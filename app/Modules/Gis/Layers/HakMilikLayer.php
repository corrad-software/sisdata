<?php

namespace App\Modules\Gis\Layers;

use App\Modules\Gis\Support\Jadual;
use App\Modules\Gis\Support\Palet;

/**
 * Mandatory layer 1 — Hak Milik: lots coloured by title type (MOD-HKM's
 * shared legend.ts); a lot without a valid title (none, replaced, expired)
 * is drawn with a dashed outline (`tanpa_hak_milik_sah`). Reads hak_milik
 * read-only; only the title TYPE is exposed, never number or owner. Sulit.
 */
class HakMilikLayer extends LotLayer
{
    public function key(): string
    {
        return 'gis.hak_milik';
    }

    public function label(): string
    {
        return 'Hak Milik';
    }

    public function permission(): string
    {
        return 'hkm.hak_milik.lihat';
    }

    public function klasifikasi(): string
    {
        return 'sulit';
    }

    protected function extraJoins(): string
    {
        if (! Jadual::has('hak_milik', ['lot_id', 'jenis_hak_milik', 'status_semasa', 'tarikh_tamat_tempoh', 'deleted_at'])) {
            return 'LEFT JOIN LATERAL (SELECT NULL::text AS jenis_hak_milik, NULL::text AS status_semasa, NULL::date AS tarikh_tamat_tempoh) hm ON TRUE';
        }

        return "LEFT JOIN LATERAL (
                    SELECT h.jenis_hak_milik, h.status_semasa, h.tarikh_tamat_tempoh FROM hak_milik h
                    WHERE h.lot_id = l.id AND h.deleted_at IS NULL AND h.status_semasa <> 'diganti'
                    ORDER BY (h.status_semasa = 'aktif') DESC, h.created_at DESC LIMIT 1
                ) hm ON TRUE";
    }

    protected function extraSelect(): string
    {
        return "COALESCE(hm.jenis_hak_milik, 'TIADA') AS jenis_hak_milik,
                (hm.jenis_hak_milik IS NULL OR hm.status_semasa IS DISTINCT FROM 'aktif' OR (hm.tarikh_tamat_tempoh IS NOT NULL AND hm.tarikh_tamat_tempoh < CURRENT_DATE)) AS tanpa_hak_milik_sah";
    }

    protected function extraColumns(): array
    {
        return ['jenis_hak_milik', 'tanpa_hak_milik_sah'];
    }

    public function legend(): array
    {
        return ['medan' => 'jenis_hak_milik', 'entri' => [
            ...Palet::entries(Palet::JENIS_HAK_MILIK),
            ['nilai' => 'TIADA', 'label' => 'Tiada hak milik sah (garis putus)', 'warna' => Palet::LALAI],
        ]];
    }
}
