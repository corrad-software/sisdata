<?php

namespace App\Modules\Pro\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-PRO-14: classification of a data component.
 *
 * @property int $id
 * @property string $komponen_data
 * @property string $entiti
 * @property string $aras_kerahsiaan
 * @property string $keperluan_encryption
 * @property string $peranan_dibenarkan
 * @property string|null $kebenaran_diperlukan
 * @property Carbon $tarikh_semakan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class KlasifikasiData extends ProModel
{
    protected $table = 'klasifikasi_data';

    protected function casts(): array
    {
        return [
            'tarikh_semakan' => 'date',
        ];
    }
}
