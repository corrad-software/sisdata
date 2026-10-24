<?php

namespace App\Modules\Pro\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-PRO-13 / SRS-DAT-01: one configurable data-quality rule.
 *
 * @property int $id
 * @property string $entiti
 * @property string $medan
 * @property string $jenis_peraturan
 * @property array<mixed> $ungkapan_peraturan
 * @property string $mesej_ralat
 * @property string $tahap_keterukan
 * @property bool $status_aktif
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PeraturanKualitiData extends ProModel
{
    protected $table = 'peraturan_kualiti_data';

    protected function casts(): array
    {
        return [
            'ungkapan_peraturan' => 'array',
            'status_aktif' => 'boolean',
        ];
    }
}
