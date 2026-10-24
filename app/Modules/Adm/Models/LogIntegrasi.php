<?php

namespace App\Modules\Adm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $kod_integrasi
 * @property string|null $id_korelasi
 * @property Carbon $tarikh_masa
 * @property string $arah
 * @property string|null $operasi
 * @property bool $berjaya
 * @property int|null $status_http
 * @property int|null $tempoh_ms
 * @property int|null $bilangan_rekod
 * @property string|null $mesej
 * @property array<string, mixed>|null $konteks
 */
class LogIntegrasi extends Model
{
    public $timestamps = false;

    protected $table = 'adm_log_integrasi';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tarikh_masa' => 'datetime', 'konteks' => 'array', 'berjaya' => 'boolean'];
    }
}
