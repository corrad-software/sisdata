<?php

namespace App\Modules\Pro\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-PRO-15: saved column mapping for an import entity.
 *
 * @property int $id
 * @property string $entiti_sasaran
 * @property string $nama
 * @property array<mixed> $pemetaan
 * @property int|null $id_pencipta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PemetaanImport extends ProModel
{
    protected $table = 'pemetaan_import';

    protected function casts(): array
    {
        return [
            'pemetaan' => 'array',
        ];
    }
}
