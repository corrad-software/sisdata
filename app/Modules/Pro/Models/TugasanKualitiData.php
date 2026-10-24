<?php

namespace App\Modules\Pro\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-PRO-12: a data-quality failure routed to the responsible steward.
 *
 * @property int $id
 * @property string $entiti
 * @property string $id_rekod
 * @property string $kod_isu
 * @property string $keterangan
 * @property string $tahap_keterukan
 * @property int|null $id_stewardship
 * @property int|null $id_data_steward
 * @property string|null $kod_daerah
 * @property string $status
 * @property string|null $tindakan_pembetulan
 * @property int|null $diselesaikan_oleh
 * @property Carbon|null $diselesaikan_pada
 * @property string $sumber
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TugasanKualitiData extends ProModel
{
    protected $table = 'tugasan_kualiti_data';

    protected function casts(): array
    {
        return [
            'diselesaikan_pada' => 'datetime',
        ];
    }
}
