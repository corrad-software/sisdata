<?php

namespace App\Modules\Tkt\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-TKT-06/07: one knowledge-base suggestion shown while the reporter was
 * typing (keyed by the form session), whether it was opened and whether
 * the ticket was still submitted (deflection).
 *
 * @property int $id
 * @property string $id_tiket_draf
 * @property string|null $id_tiket
 * @property int|null $id_pengguna
 * @property string $id_artikel_kb
 * @property string|null $tajuk
 * @property float|null $skor_relevan
 * @property int $kedudukan
 * @property bool $diklik
 * @property bool|null $tiket_diteruskan
 * @property string|null $modul
 * @property Carbon $cap_masa
 */
class CadanganKb extends TktModel
{
    protected $table = 'tkt_cadangan_kb';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['diklik' => 'boolean', 'tiket_diteruskan' => 'boolean', 'skor_relevan' => 'float', 'cap_masa' => 'datetime'];
    }
}
