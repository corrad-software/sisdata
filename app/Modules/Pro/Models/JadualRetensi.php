<?php

namespace App\Modules\Pro\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-PRO-23: retention schedule per record category.
 *
 * @property int $id
 * @property string $kategori_rekod
 * @property string $keterangan
 * @property string $entiti
 * @property array<mixed> $syarat
 * @property int $tempoh_retensi_tahun
 * @property string $tindakan_selepas_retensi
 * @property string|null $rujukan_dasar_mais
 * @property bool $memerlukan_kelulusan
 * @property int|null $id_pelulus
 * @property bool $aktif
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class JadualRetensi extends ProModel
{
    protected $table = 'jadual_retensi';

    protected function casts(): array
    {
        return [
            'syarat' => 'array',
            'memerlukan_kelulusan' => 'boolean',
            'aktif' => 'boolean',
        ];
    }
}
