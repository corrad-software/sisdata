<?php

namespace App\Modules\Adm\Contracts;

use DateTimeInterface;

/**
 * Versioned reference codes (SRS-ADM-02, SRS-DAT-05): daerah, mukim,
 * kategori_guna_tanah, jenis_hak_milik, jenis_dokumen, kategori_aduan,
 * jenis_urusan, status_transaksi… Deactivated codes stay valid for records
 * dated inside their validity period, so history never breaks.
 */
interface ReferenceCodes
{
    /** Is $code a valid $type code on $at (default: today)? */
    public function valid(string $type, string $code, ?DateTimeInterface $at = null): bool;

    /** Display text for a code, including deactivated ones (for history). */
    public function label(string $type, string $code): ?string;

    /**
     * Options for a dropdown, ordered by susunan.
     *
     * @return list<array{kod: string, keterangan: string, kod_induk: ?string}>
     */
    public function options(string $type, ?string $parent = null, bool $activeOnly = true): array;
}
