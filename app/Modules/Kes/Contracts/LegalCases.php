<?php

namespace App\Modules\Kes\Contracts;

use App\Models\User;

/**
 * MOD-KES for other modules: open a legal case from another record (MOD-ADU
 * complaint → case, SRS-KES-19; MOD-PTN finding; MOD-HKM encumbrance) and
 * look cases up without touching the Rahsia tables.
 *
 * Everything returned here is non-sensitive (internal reference, type,
 * status, dates) — never the court number, parties, claim value or costs.
 */
interface LegalCases
{
    /**
     * Open a case in status "didaftar" carrying the source record's lot links,
     * documents and chronology. Idempotent on (sumber, $sourceRef): a second
     * call returns the same case id. `sumber`: adu | ptn | hkm | lain (default lain).
     *
     * @param  array{tajuk: string, keterangan?: string|null, jenis?: string|null, lot_ids?: list<string>, dokumen?: list<array{id_dokumen_dms: string, nama_fail?: string|null}>, kronologi?: list<array{tarikh: string, keterangan: string}>, sumber?: string}  $data
     * @param  string  $sourceRef  e.g. "aduan:<id>", "ptn_isu:<id>", "sekatan:<id>"
     * @return string case id (uuid)
     */
    public function open(array $data, User $by, string $sourceRef): string;

    /** Whether a case with this id exists (e.g. to validate HKM sekatan_kepentingan.id_kes_mahkamah). */
    public function exists(string $caseId): bool;

    /**
     * Non-sensitive summary of one case, or null when $user may not see it
     * (missing permission, scope, MFA) or it does not exist.
     *
     * @return array{id: string, no_rujukan: string, jenis: string|null, status: string, aktif: bool, pautan: string}|null
     */
    public function summary(string $caseId, User $user): ?array;

    /**
     * Cases linked to a lot that $user may see.
     *
     * @return list<array{id: string, no_rujukan: string, jenis: string|null, status: string, aktif: bool, pautan: string}>
     */
    public function forLot(string $lotId, User $user): array;

    /**
     * SRS-KES-06: lots currently "dalam tindakan undang-undang" (at least one
     * active case). Only the flag — safe for any caller.
     *
     * @param  list<string>  $lotIds
     * @return array<string, bool> keyed by lot id; lots without a case are false
     */
    public function underLegalAction(array $lotIds): array;

    /** Frontend path of the case record. */
    public function link(string $caseId): string;
}
