<?php

namespace App\Modules\Hkm\Contracts;

use App\Models\User;

/**
 * MOD-HKM's write/read API for titles, used by MOD-PER when an acquisition
 * case completes (SRS-PER-21/22). Changes still follow HKM's own rules
 * (versioning, audit); a title created here starts in the vault register
 * when $vault is given.
 */
interface TitleRegistry
{
    /**
     * Create (or update the matching no_hak_milik in the same mukim) a title for a lot.
     *
     * @param  array{no_hak_milik: string, jenis_hak_milik: string, tarikh_pendaftaran?: string, tarikh_tamat_tempoh?: string|null, pejabat_pendaftaran?: string|null, pemilik_berdaftar?: string, keluasan?: string|null, kategori_tanah?: string|null, syarat_nyata?: string|null}  $data
     * @param  array{kod_peti_besi: string, no_fail?: string, lokasi_rak?: string, no_kotak_pengikat?: string, jenis_dokumen_asal?: string}|null  $vault  deposit the original document
     * @param  string|null  $sourceRef  e.g. "pemerolehan:<id>" — recorded in ownership chronology
     * @return string title id
     */
    public function registerFromAcquisition(string $lotId, array $data, User $actor, ?array $vault = null, ?string $sourceRef = null): string;

    /**
     * @return list<array{id: string, no_hak_milik: string, jenis_hak_milik: string, status_semasa: string, tarikh_tamat_tempoh: ?string}>
     */
    public function forLot(string $lotId, User $user): array;
}
