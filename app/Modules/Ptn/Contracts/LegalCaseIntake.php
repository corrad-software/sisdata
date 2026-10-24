<?php

namespace App\Modules\Ptn\Contracts;

use App\Models\User;

/**
 * SRS-PTN-18: escalate a monitoring issue to MOD-KES (court / legal case).
 * MOD-KES (G4) does not exist yet: MOD-PTN binds a "not available"
 * implementation; MOD-KES binds the real one.
 */
interface LegalCaseIntake
{
    public function available(): bool;

    /**
     * @param  array{lot_id: string, tajuk: string, keterangan: string, kategori: string, rujukan_dokumen?: list<string>}  $data
     * @return string case id
     */
    public function open(array $data, User $by, string $sourceRef): string;
}
