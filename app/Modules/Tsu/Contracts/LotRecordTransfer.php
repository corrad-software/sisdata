<?php

namespace App\Modules\Tsu\Contracts;

use App\Models\User;

/**
 * SRS-TSU-20: one kind of lot-anchored record (pajakan, cukai, aset fizikal…)
 * that can be moved from an original lot to a successor lot after an
 * approved subdivision / amalgamation. The OWNING module implements it and
 * registers it with LotRecordTransfers; MOD-TSU only calls transfer() after
 * the two-level confirmation (MOD-WFE) has passed.
 */
interface LotRecordTransfer
{
    /** Stable record type, e.g. "kontrak_pajakan". */
    public function jenis(): string;

    public function label(): string;

    /**
     * Records currently on the lot. Labels must not carry Sulit/Rahsia values.
     *
     * @return list<array{id: string, label: string}>
     */
    public function forLot(string $lotId): array;

    /** Move one record; must be atomic and audited by the owner. */
    public function transfer(string $recordId, string $fromLotId, string $toLotId, User $by, string $reason): void;
}
