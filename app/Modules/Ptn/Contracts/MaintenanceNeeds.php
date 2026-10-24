<?php

namespace App\Modules\Ptn\Contracts;

/**
 * SRS-PTN-21: maintenance needs found during monitoring, for MOD-AST (G4) to
 * pull into its asset maintenance schedule and to link back to an asset.
 */
interface MaintenanceNeeds
{
    /**
     * @return list<array{id: int, lot_id: string, jenis: string, keterangan: string, keutamaan: string, status: string, tarikh_jadual: string|null, id_aset_fizikal: string|null, id_lawatan: string|null, tarikh_dikenal_pasti: string}>
     */
    public function forLot(string $lotId): array;

    /** Link a need to a MOD-AST asset (and optionally mark it scheduled / done). */
    public function link(int $id, string $assetId, ?string $status = null): void;
}
