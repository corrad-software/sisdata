<?php

namespace App\Modules\Pjk\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-PJK-22/23: one versioned Dasar Agihan Manfaat rate for a property
 * category and acquisition status (wakaf / belian).
 *
 * @property int $id
 * @property int $versi_dasar
 * @property string $kategori_hartanah
 * @property string $status_perolehan
 * @property string $kadar_agihan
 * @property list<array{kod: string, nama: string, peratus: string}> $penerima_manfaat
 * @property Carbon $tarikh_mula_kuasa
 * @property Carbon|null $tarikh_tamat
 * @property string|null $rujukan_kelulusan
 * @property string $status
 * @property string|null $id_instans_aliran
 * @property int|null $id_pencipta
 */
class DasarAgihanManfaat extends PjkModel
{
    public const SUBJECT = 'dasar_agihan_manfaat';

    protected $table = 'dasar_agihan_manfaat';

    protected function casts(): array
    {
        return ['penerima_manfaat' => 'array', 'tarikh_mula_kuasa' => 'date:Y-m-d', 'tarikh_tamat' => 'date:Y-m-d', 'versi_dasar' => 'integer'];
    }
}
