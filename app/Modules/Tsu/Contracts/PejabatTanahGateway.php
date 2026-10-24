<?php

namespace App\Modules\Tsu\Contracts;

/**
 * SRS-TSU-22 / SRS §11.2: the central integration provider for Pejabat Tanah
 * Daerah application status (PTG / e-Tanah behind it). Coverage is per
 * daerah; districts outside it use the structured manual update with
 * automatic follow-up reminders (SRS-INT-10). The adapter owns the vendor
 * schema (SRS-INT-11); MOD-TSU binds Integrasi\PejabatTanahMock until MAIS
 * provides the real provider.
 */
interface PejabatTanahGateway
{
    public function covers(string $kodDaerah): bool;

    /**
     * Current status of an application at the Land Office.
     *
     * @return array{status: string, tarikh: string, catatan?: string|null}|null null when the reference is unknown
     *
     * @throws PejabatTanahTidakTersedia
     */
    public function status(string $noRujukanPtd, string $kodDaerah): ?array;

    /**
     * Submit an application; returns the Land Office reference number.
     *
     * @param  array{no_rujukan_mais: string, jenis_urusan: string, kod_daerah: string, kod_mukim: string, no_lot: list<string>}  $permohonan
     *
     * @throws PejabatTanahTidakTersedia
     */
    public function submit(array $permohonan): string;
}
