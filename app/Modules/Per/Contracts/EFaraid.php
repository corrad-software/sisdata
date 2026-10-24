<?php

namespace App\Modules\Per\Contracts;

/**
 * e-Faraid MAIS (SRS-PER-13): verifies heirs, shares and the faraid case
 * number. No API has been supplied yet, so MOD-PER binds
 * Integration\MockEFaraid (deterministic synthetic data); swap the binding
 * for the real adapter.
 */
interface EFaraid
{
    /**
     * @return array{no_kes_faraid: string, nama_si_mati: string, tarikh_kematian: string|null, bahagian_mais: string|null, rujukan: string, waris: list<array{nama: string, hubungan: string, no_pengenalan: string|null, bahagian: string|null}>}|null
     */
    public function lookup(string $faraidCaseNumber): ?array;
}
