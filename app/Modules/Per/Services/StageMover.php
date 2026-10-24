<?php

namespace App\Modules\Per\Services;

use App\Modules\Per\Models\Pemerolehan;
use App\Modules\Per\Models\PeringkatPemerolehan;

/**
 * SRS-PER-18: closing a stage stamps tarikh_keluar and tempoh_hari, opening
 * the next stamps tarikh_masuk and the responsible officer. No guards here —
 * CaseService checks them before calling.
 */
class StageMover
{
    public function move(Pemerolehan $case, ?PeringkatPemerolehan $current, ?PeringkatPemerolehan $next, ?int $officerId, ?string $note = null): void
    {
        $now = now();

        if ($current !== null) {
            $current->forceFill([
                'tarikh_keluar' => $now,
                'status_peringkat' => PeringkatPemerolehan::SELESAI,
                'catatan' => $note ?? $current->catatan,
            ]);
            $current->tempoh_hari = $current->hariBerjalan();
            $current->save();
        }

        if ($next !== null) {
            $next->forceFill([
                'tarikh_masuk' => $now,
                'tarikh_keluar' => null,
                'status_peringkat' => PeringkatPemerolehan::SEMASA,
                'id_pegawai_bertanggungjawab' => $officerId ?? $next->id_pegawai_bertanggungjawab ?? $case->id_pegawai_bertanggungjawab,
            ])->save();
        }

        $case->forceFill(['peringkat_semasa' => $next?->kod_peringkat])->save();
        $case->unsetRelation('peringkat');
    }
}
