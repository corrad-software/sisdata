<?php

namespace App\Modules\Cki\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-CKI-08 `mod_operasi_integrasi`: one fallback-mode period of an agency.
 *
 * @property int $id
 * @property string $kod_agensi
 * @property string $mod
 * @property Carbon $tarikh_mula
 * @property Carbon|null $tarikh_tamat
 * @property string|null $sebab
 * @property int|null $id_pegawai_maklum
 */
class ModOperasi extends CkiModel
{
    protected $table = 'mod_operasi_integrasi';

    protected $casts = ['tarikh_mula' => 'datetime', 'tarikh_tamat' => 'datetime'];
}
