<?php

namespace App\Modules\Hkm\Contracts;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * SRS-HKM-18: entry point for external integrations (e-Tanah, PTD, penyedia
 * berpusat, e-Faraid…) to propose a change to a title. A proposal is stored
 * as a DRAFT in `cadangan_perubahan` and never writes the title master; an
 * officer confirms it in the HKM inbox, which starts the normal two-stage
 * approval (SRS-HKM-19). Same (sumber, rujukan_luaran) is idempotent.
 */
interface TitleChangeProposals
{
    /**
     * jenis_perubahan is butiran | status | pemilik | sekatan | waris, with the
     * same `data` payload as a change request (see docs in ChangeService).
     *
     * @param  array{sumber: string, rujukan_luaran: string, no_hak_milik: string, kod_mukim?: string|null, jenis_perubahan: string, data: array<string, mixed>, keterangan?: string|null}  $proposal
     * @return string proposal id
     *
     * @throws ValidationException
     */
    public function propose(array $proposal, ?User $sender = null): string;
}
