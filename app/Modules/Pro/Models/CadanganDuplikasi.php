<?php

namespace App\Modules\Pro\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-PRO-20 / SRS-DAT-02: a suggested duplicate pair awaiting a steward decision.
 *
 * @property int $id
 * @property string $entiti
 * @property string $id_rekod_a
 * @property string $id_rekod_b
 * @property array<mixed> $kriteria_padanan
 * @property float $skor_padanan
 * @property string $keputusan
 * @property string|null $catatan_keputusan
 * @property int|null $id_data_steward
 * @property Carbon|null $tarikh_keputusan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CadanganDuplikasi extends ProModel
{
    protected $table = 'cadangan_duplikasi';

    protected function casts(): array
    {
        return [
            'kriteria_padanan' => 'array',
            'skor_padanan' => 'float',
            'tarikh_keputusan' => 'datetime',
        ];
    }
}
