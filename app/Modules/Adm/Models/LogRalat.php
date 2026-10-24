<?php

namespace App\Modules\Adm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Structured application log entry (SRS-ADM-11). ADM_LOG_RALAT. Written by
 * Services\Logging\DatabaseLogHandler — already redacted (SRS-ADM-15).
 *
 * @property int $id
 * @property string|null $id_korelasi
 * @property Carbon $tarikh_masa
 * @property string $aras_keterukan
 * @property string $saluran
 * @property string|null $modul
 * @property string|null $kod_ralat
 * @property string $mesej
 * @property string|null $jejak_tindanan
 * @property int|null $id_pengguna
 * @property string|null $alamat_ip
 * @property array<string, mixed>|null $konteks
 */
class LogRalat extends Model
{
    public $timestamps = false;

    protected $table = 'adm_log_ralat';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tarikh_masa' => 'datetime', 'konteks' => 'array'];
    }
}
