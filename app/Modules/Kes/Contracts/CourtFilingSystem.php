<?php

namespace App\Modules\Kes\Contracts;

/**
 * Adapter to the courts' electronic systems (e-Filing / e-Court of the
 * Malaysian Judiciary, and the Syariah courts' e-Syariah). No access has been
 * given yet (PLAN §11 "adapter + mock"): Integrasi\MockSistemMahkamah is
 * bound; a live implementation replaces it without touching MOD-KES.
 */
interface CourtFilingSystem
{
    /** Short id shown in the UI / logs, e.g. "mock", "efiling". */
    public function name(): string;

    /**
     * Case status and hearing dates the court system holds for a case number.
     *
     * @return array{no_kes: string, status: string|null, prosiding: list<array{tarikh: string, jenis: string, catatan?: string|null}>}|null null when the court has no such case
     *
     * @throws CourtSystemUnavailable
     */
    public function caseInfo(string $noKes, ?string $mahkamah = null): ?array;

    /**
     * File a document electronically.
     *
     * @return string the court's filing reference
     *
     * @throws CourtSystemUnavailable
     */
    public function fileDocument(string $noKes, string $kategori, string $namaFail, string $kandungan): string;
}
