<?php

namespace App\Modules\Kba\Models;

use Illuminate\Support\Carbon;

/**
 * Classification → role mapping (SRS-KBA-08, entity kba_pemetaan_kebenaran):
 * a role mapped to "sulit" with jenis_capaian "baca" may retrieve Sulit
 * segments, from tarikh_berkuat_kuasa.
 *
 * @property int $id
 * @property string $klasifikasi
 * @property string $peranan
 * @property string $jenis_capaian
 * @property Carbon $tarikh_berkuat_kuasa
 */
class PemetaanKebenaran extends KbaModel
{
    protected $table = 'kba_pemetaan_kebenaran';

    protected function casts(): array
    {
        return ['tarikh_berkuat_kuasa' => 'date'];
    }
}
