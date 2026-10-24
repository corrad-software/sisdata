<?php

namespace App\Modules\Adu\Models;

use App\Modules\Adu\Models\Concerns\AppendOnly;
use Illuminate\Support\Carbon;

/**
 * SRS-ADU-14/17: one escalation (automatic per SLA path, or manual).
 *
 * @property int $id
 * @property string $aduan_id
 * @property string|null $no_rujukan_aduan
 * @property int $peringkat
 * @property string $jenis
 * @property int|null $id_pegawai_asal
 * @property int|null $id_pegawai_eskalasi
 * @property list<int>|null $penerima
 * @property string $sebab_eskalasi
 * @property Carbon $tarikh_eskalasi
 */
class EskalasiAduan extends AduModel
{
    use AppendOnly;

    public const UPDATED_AT = null;

    protected $table = 'eskalasi_aduan';

    protected function casts(): array
    {
        return ['penerima' => 'array', 'tarikh_eskalasi' => 'datetime', 'peringkat' => 'integer'];
    }
}
