<?php

namespace App\Modules\Kes\Contracts;

/**
 * Adapter to the Attorney General's Chambers (Jabatan Peguam Negara) / State
 * Legal Adviser: refer a case for legal advice or representation and follow
 * the referral. Mock bound (Integrasi\MockPeguamNegara) until access is given.
 */
interface AttorneyGeneralChambers
{
    /**
     * @param  array{no_rujukan: string, no_kes: string, jenis: string, ringkasan: string, jenis_rujukan: string}  $referral  jenis_rujukan: nasihat | perwakilan
     * @return string AGC reference number
     *
     * @throws CourtSystemUnavailable
     */
    public function refer(array $referral): string;

    /**
     * @return array{status: string, pegawai: string|null, ulasan: string|null}|null
     *
     * @throws CourtSystemUnavailable
     */
    public function status(string $reference): ?array;
}
