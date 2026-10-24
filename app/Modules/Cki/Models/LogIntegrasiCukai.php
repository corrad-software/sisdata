<?php

namespace App\Modules\Cki\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * SRS-CKI-10 `log_integrasi_cukai`. Not audited per row (it is itself a log);
 * the payload summary never carries credentials or full financial details.
 *
 * @property int $id
 * @property string $jenis_panggilan
 * @property string|null $kod_agensi
 * @property string|null $id_obligasi
 * @property Carbon $masa_panggilan
 * @property int|null $masa_tindak_balas
 * @property int|null $status_http
 * @property bool $berjaya
 * @property string|null $kod_ralat
 * @property array<string, mixed>|null $ringkasan_payload
 * @property string|null $rujukan_transaksi
 * @property int $bilangan_cuba_semula
 */
class LogIntegrasiCukai extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'log_integrasi_cukai';

    protected $guarded = ['id'];

    protected $casts = ['masa_panggilan' => 'datetime', 'berjaya' => 'boolean', 'ringkasan_payload' => 'array'];
}
