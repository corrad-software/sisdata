<?php

namespace App\Modules\Pro\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Carbon;

/**
 * SRS-PRO-23: archive proposal / archived record / restore.
 *
 * @property string $id
 * @property string $entiti
 * @property string $id_rekod
 * @property int|null $id_jadual_retensi
 * @property string $status_arkib
 * @property int|null $id_pemohon
 * @property int|null $id_pelulus_arkib
 * @property string|null $id_aliran_kerja
 * @property Carbon|null $tarikh_arkib
 * @property string $lokasi_storan_arkib
 * @property Carbon|null $tarikh_layak_lupus
 * @property int|null $id_pemulih
 * @property Carbon|null $tarikh_pulih
 * @property string|null $sebab
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RekodArkib extends ProModel
{
    use HasUuids;

    protected $table = 'rekod_arkib';

    protected function casts(): array
    {
        return [
            'tarikh_arkib' => 'datetime',
            'tarikh_pulih' => 'datetime',
            'tarikh_layak_lupus' => 'date',
        ];
    }
}
