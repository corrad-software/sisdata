<?php

namespace App\Modules\Ptn\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-PTN-05: one offline-sync event (accepted, duplicate, conflict, refused).
 *
 * @property int $id
 * @property int|null $id_pengguna
 * @property string $id_luar_talian
 * @property string|null $id_lawatan
 * @property string $jenis
 * @property string $status
 * @property array<string, mixed> $butiran
 * @property Carbon $created_at
 */
class LogPenyegerakan extends PtnModel
{
    public const UPDATED_AT = null;

    protected $table = 'log_penyegerakan';

    protected $casts = ['butiran' => 'array', 'created_at' => 'datetime'];
}
