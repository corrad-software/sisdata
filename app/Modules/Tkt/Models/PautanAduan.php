<?php

namespace App\Modules\Tkt\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-TKT-15: cross-reference ticket number ↔ central complaint number.
 *
 * @property int $id
 * @property string $id_tiket
 * @property string $nombor_aduan_berpusat
 * @property string $arah_asal
 * @property string $status_penyegerakan
 * @property string|null $status_terakhir_disegerak
 * @property Carbon|null $cap_masa_penyegerakan_akhir
 */
class PautanAduan extends TktModel
{
    protected $table = 'tkt_pautan_aduan';

    protected function casts(): array
    {
        return ['cap_masa_penyegerakan_akhir' => 'datetime'];
    }
}
