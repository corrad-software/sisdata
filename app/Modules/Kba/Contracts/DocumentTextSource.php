<?php

namespace App\Modules\Kba\Contracts;

/**
 * Read-only view of a DMS document's already-extracted text (SRS-KBA-02).
 * MOD-DMS extracts text / OCR into its own index; MOD-KBA reuses that rather
 * than decrypting files itself. Until MOD-DMS implements this contract, KBA
 * binds a read-only adapter over the `dokumen` table.
 */
interface DocumentTextSource
{
    /**
     * null when the document does not exist (or was disposed / deleted).
     * `status_indeks` is DMS's own: menunggu | selesai | gagal | tiada.
     *
     * @return array{id: string, tajuk: string, versi: int, kategori: string|null, klasifikasi: string, kod_daerah: string|null, status: string, status_indeks: string, teks: string|null, id_pencipta: int|null, tarikh_dokumen: string|null}|null
     */
    public function find(string $documentId): ?array;
}
