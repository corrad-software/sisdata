<?php

namespace App\Modules\Tkt\Models;

use Illuminate\Support\Carbon;

/**
 * §18.6 monthly support report inputs that are not tickets: security
 * patches, preventive maintenance, downtime with root cause, technology
 * transfer progress, proposed preventive actions.
 *
 * @property int $id
 * @property string $jenis
 * @property Carbon $tarikh
 * @property string $tajuk
 * @property string|null $keterangan
 * @property int|null $tempoh_minit
 * @property string|null $punca_akar
 * @property string|null $rujukan
 * @property int|null $id_pengguna
 * @property bool $sintetik
 */
class AktivitiSokongan extends TktModel
{
    protected $table = 'tkt_aktiviti_sokongan';

    protected function casts(): array
    {
        return ['tarikh' => 'date', 'sintetik' => 'boolean', 'tempoh_minit' => 'integer'];
    }
}
