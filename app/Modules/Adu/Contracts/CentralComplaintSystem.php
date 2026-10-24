<?php

namespace App\Modules\Adu\Contracts;

/**
 * SRS-ADU-07 / RFP 10.2: two-way adapter to the Sistem Aduan berpusat MAIS
 * (SRS-INT-11 — one adapter per third party). MAIS has not given access yet,
 * so MOD-ADU binds Integrasi\MockSistemAduanPusat; a live adapter replaces
 * the binding without touching the rest of the module.
 */
interface CentralComplaintSystem
{
    /**
     * New complaints since the given moment (ISO 8601), oldest first.
     *
     * @return list<array{id_luaran: string, kategori: string, keterangan: string, tarikh: string, lokasi_teks?: string|null, lat?: float|null, lng?: float|null, kod_daerah?: string|null, nama?: string|null, no_telefon?: string|null, emel?: string|null, tanpa_nama?: bool}>
     */
    public function fetchNew(?string $since = null): array;

    /**
     * Push a status change back for a complaint that came from the central system.
     *
     * @param  array{no_rujukan: string, status: string, status_awam: string, kategori_resolusi?: string|null, keterangan?: string|null, tarikh: string}  $payload
     *
     * @throws \RuntimeException when the remote system rejects or is unreachable (the outbox retries)
     */
    public function pushStatus(string $externalId, array $payload): void;
}
