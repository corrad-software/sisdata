<?php

namespace App\Modules\Adm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Health snapshot per component (SRS-ADM-16). ADM_KESIHATAN.
 *
 * @property int $id
 * @property string $komponen
 * @property string $status ok | amaran | gagal
 * @property int|null $masa_respons_ms
 * @property string|null $mesej
 * @property array<string, mixed>|null $butiran
 * @property Carbon $dicatat_pada
 */
class Kesihatan extends Model
{
    public $timestamps = false;

    protected $table = 'adm_kesihatan';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['butiran' => 'array', 'dicatat_pada' => 'datetime'];
    }
}
