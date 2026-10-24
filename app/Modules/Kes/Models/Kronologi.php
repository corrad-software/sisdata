<?php

namespace App\Modules\Kes\Models;

use Illuminate\Support\Carbon;

/**
 * Chronology entries that are not proceedings: complaint / inspection
 * history carried over when a case is opened from MOD-ADU / MOD-PTN
 * (SRS-KES-19), e-Filing sync, system events.
 *
 * @property int $id
 * @property string $kes_mahkamah_id
 * @property Carbon $tarikh
 * @property string $jenis
 * @property string $keterangan
 * @property string|null $sumber
 * @property int|null $id_pengguna
 */
class Kronologi extends Sejarah
{
    protected $table = 'kes_kronologi';

    protected function casts(): array
    {
        return ['tarikh' => 'datetime'];
    }
}
