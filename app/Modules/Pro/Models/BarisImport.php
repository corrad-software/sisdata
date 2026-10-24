<?php

namespace App\Modules\Pro\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-PRO-16: one parsed row of an import session.
 *
 * @property int $id
 * @property string $id_sesi
 * @property int $no_baris
 * @property array<mixed> $data
 * @property string $status
 * @property string|null $id_rekod
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BarisImport extends ProModel
{
    protected $table = 'baris_import';

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }
}
