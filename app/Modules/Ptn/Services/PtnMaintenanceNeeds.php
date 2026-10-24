<?php

namespace App\Modules\Ptn\Services;

use App\Modules\Ptn\Contracts\MaintenanceNeeds;
use App\Modules\Ptn\Models\KeperluanPenyelenggaraan;

class PtnMaintenanceNeeds implements MaintenanceNeeds
{
    public function forLot(string $lotId): array
    {
        return KeperluanPenyelenggaraan::query()->withoutGlobalScope('access')->where('lot_id', $lotId)->orderByDesc('created_at')->get()
            ->map(fn (KeperluanPenyelenggaraan $k) => [
                'id' => $k->id, 'lot_id' => $k->lot_id, 'jenis' => $k->jenis, 'keterangan' => $k->keterangan, 'keutamaan' => $k->keutamaan,
                'status' => $k->status, 'tarikh_jadual' => $k->tarikh_jadual?->toDateString(), 'id_aset_fizikal' => $k->id_aset_fizikal,
                'id_lawatan' => $k->id_lawatan, 'tarikh_dikenal_pasti' => $k->created_at->toDateString(),
            ])->values()->all();
    }

    public function link(int $id, string $assetId, ?string $status = null): void
    {
        $k = KeperluanPenyelenggaraan::query()->withoutGlobalScope('access')->findOrFail($id);
        $k->forceFill(['id_aset_fizikal' => $assetId] + ($status !== null && in_array($status, ['dikenal_pasti', 'dijadual', 'selesai', 'dibatalkan'], true) ? ['status' => $status] : []))->save();
    }
}
