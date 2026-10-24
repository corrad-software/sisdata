<?php

namespace App\Modules\Tsu\Contracts;

use App\Models\User;

/**
 * Changes MOD-TSU needs on the MASTER lot record (lot_tanah / data_spatial,
 * owned by MOD-PRO) once a Land Office urusan is approved: new category /
 * conditions after tukar syarat (SRS-TSU-07), new geometry and area from an
 * accepted survey (SRS-TSU-16..18) — as a PROPOSAL for MOD-PRO's own
 * verification (SRS-INT-09) —, successor lots after pecah sempadan / pecah
 * bahagian / penyatuan (SRS-TSU-19) and archiving disposed or superseded lots
 * (SRS-TSU-06/21).
 *
 * MOD-PRO should bind its own implementation (versioning SRS-DAT-04,
 * quality rules, critical-change approval). Until then MOD-TSU binds
 * Support\LotChangesFallback with bindIf.
 */
interface LotChanges
{
    /**
     * Apply approved non-critical field changes (kategori_guna_tanah, syarat_nyata, zoning).
     *
     * @param  array<string, string|null>  $changes
     */
    public function applyConditions(string $lotId, array $changes, User $approvedBy, string $reason): void;

    /**
     * Propose a new declared area in m² (critical field): goes through
     * MOD-PRO's second-officer confirmation (flow pro.perubahan_kritikal).
     * Returns the pending proposal id, or null when nothing changes.
     */
    public function proposeArea(string $lotId, string $keluasanM2, User $actor, string $reason): ?string;

    /**
     * Propose a surveyed geometry as a new, NOT yet verified data_spatial
     * version of the lot (MOD-PRO's GIS officer verifies it). Returns its id.
     *
     * @param  array{sumber_data?: string, rujukan_fail_sumber?: string|null, srid_asal?: int, format_asal?: string, id_dokumen_sumber?: string|null}  $meta
     */
    public function proposeGeometry(string $lotId, string $hexEwkb, array $meta, User $actor): int;

    /**
     * Create a successor lot (subdivision / amalgamation result) with its
     * surveyed geometry. Returns the new lot id.
     *
     * @param  array{no_lot: string, kod_mukim: string, kod_daerah: string, keluasan?: string|null, kategori_guna_tanah?: string|null, syarat_nyata?: string|null, zoning?: string|null, bahagian_pemilik?: string|null, klasifikasi?: string|null, sintetik?: bool}  $lot
     */
    public function createSuccessor(array $lot, string $hexEwkb, User $actor, string $reason, string $sourceRef): string;

    /** Archive a lot (disposed, or original lot after subdivision) — never deleted. */
    public function archive(string $lotId, User $actor, string $reason): void;
}
