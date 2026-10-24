<?php

namespace App\Modules\Pro\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-PRO-05: curated business metadata of a table or column.
 *
 * @property int $id
 * @property string $nama_jadual
 * @property string|null $nama_medan
 * @property string $nama_perniagaan
 * @property string|null $definisi
 * @property string|null $format
 * @property string|null $nilai_dibenarkan
 * @property bool|null $wajib
 * @property string|null $data_owner
 * @property string|null $data_steward
 * @property string $klasifikasi
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class KamusData extends ProModel
{
    protected $table = 'kamus_data';

    protected function casts(): array
    {
        return [
            'wajib' => 'boolean',
        ];
    }
}
