<?php

namespace App\Modules\Per\Services;

use App\Modules\Per\Models\DokumenPemerolehan;
use App\Modules\Per\Models\Pemerolehan;
use App\Modules\Per\Models\SemakDokumen;
use App\Modules\Per\Support\ChannelCatalog;

/**
 * SRS-PER-16: mandatory documents per channel, completeness status and the
 * list of what is missing. An item is satisfied by a document row linked to
 * it that has a DMS copy and was not rejected. Items "dijana_sistem" (the
 * vault register record) are satisfied by closing the case itself.
 */
class Checklist
{
    /**
     * @return list<array{id: int, jenis_dokumen: string, nama_dokumen: string, kategori_dms: string, wajib: bool, dijana_sistem: bool, peringkat_diperlukan: string, dipenuhi: bool, dokumen: list<int>}>
     */
    public function items(Pemerolehan $case): array
    {
        $case->loadMissing(['saluran.senaraiSemak', 'dokumen']);
        $items = [];
        foreach ($case->saluran->senaraiSemak as $item) {
            $docs = $case->dokumen->filter(fn (DokumenPemerolehan $d) => $d->id_semak === $item->id);
            $items[] = [
                'id' => $item->id,
                'jenis_dokumen' => $item->jenis_dokumen,
                'nama_dokumen' => $item->nama_dokumen,
                'kategori_dms' => $item->kategori_dms,
                'wajib' => $item->status_wajib,
                'dijana_sistem' => $item->dijana_sistem,
                'peringkat_diperlukan' => $item->peringkat_diperlukan,
                'dipenuhi' => $item->dijana_sistem ? $case->id_hak_milik_dijana !== null : $this->satisfied($docs->all()),
                'dokumen' => $docs->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            ];
        }

        return $items;
    }

    /**
     * Mandatory documents still missing before the case may enter $stageCode
     * ("tutup" = before closing). Names, in display order.
     *
     * @return list<string>
     */
    public function missingBefore(Pemerolehan $case, string $stageCode): array
    {
        $order = $this->stageOrder($case);
        $target = $order[$stageCode] ?? PHP_INT_MAX;

        $missing = [];
        foreach ($this->items($case) as $item) {
            if (! $item['wajib'] || $item['dijana_sistem'] || $item['dipenuhi']) {
                continue;
            }
            if (($order[$item['peringkat_diperlukan']] ?? PHP_INT_MAX) <= $target) {
                $missing[] = $item['nama_dokumen'];
            }
        }

        return $missing;
    }

    /** @return list<string> every mandatory document not yet in */
    public function missingAll(Pemerolehan $case): array
    {
        return $this->missingBefore($case, ChannelCatalog::TUTUP);
    }

    /** Recompute pemerolehan.status_kelengkapan_dokumen. */
    public function refresh(Pemerolehan $case): void
    {
        $case->unsetRelation('dokumen');
        $status = $this->missingAll($case) === [] ? 'lengkap' : 'tidak_lengkap';
        if ($case->status_kelengkapan_dokumen !== $status) {
            $case->forceFill(['status_kelengkapan_dokumen' => $status])->save();
        }
    }

    /** @param list<DokumenPemerolehan> $docs */
    private function satisfied(array $docs): bool
    {
        foreach ($docs as $d) {
            if ($d->rujukan_dms !== null && $d->status_dokumen !== 'ditolak') {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, int> stage code => order, "tutup" last */
    private function stageOrder(Pemerolehan $case): array
    {
        $case->loadMissing('peringkat');
        $order = [];
        foreach ($case->peringkat as $stage) {
            $order[$stage->kod_peringkat] = $stage->susunan;
        }
        $order[ChannelCatalog::TUTUP] = PHP_INT_MAX;

        return $order;
    }

    public function itemFor(Pemerolehan $case, int $semakId): ?SemakDokumen
    {
        return SemakDokumen::query()->where('id_saluran', $case->id_saluran)->whereKey($semakId)->first();
    }
}
