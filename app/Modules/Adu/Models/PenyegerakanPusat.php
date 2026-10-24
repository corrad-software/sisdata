<?php

namespace App\Modules\Adu\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-ADU-07 / SRS-INT-10: outbox of status pushes to the central
 * complaints system — retried until delivered.
 *
 * @property int $id
 * @property string $aduan_id
 * @property string $id_aduan_sistem_luaran
 * @property string $arah
 * @property string $status_aduan
 * @property array<string, mixed> $muatan
 * @property string $status
 * @property int $cubaan
 * @property string|null $ralat_terakhir
 * @property Carbon|null $dihantar_pada
 * @property Carbon|null $created_at
 */
class PenyegerakanPusat extends AduModel
{
    protected $table = 'penyegerakan_aduan_pusat';

    protected function casts(): array
    {
        return ['muatan' => 'array', 'dihantar_pada' => 'datetime', 'cubaan' => 'integer'];
    }
}
