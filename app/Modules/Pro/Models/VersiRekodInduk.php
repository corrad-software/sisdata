<?php

namespace App\Modules\Pro\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Carbon;

/**
 * SRS-PRO-22 / SRS-DAT-04: one version (or pending critical change) of a master record.
 *
 * @property string $id
 * @property string $entiti
 * @property string $id_rekod
 * @property int|null $no_versi
 * @property array<mixed>|null $nilai_sebelum
 * @property array<mixed> $nilai_selepas
 * @property bool $kritikal
 * @property int|null $id_pengguna
 * @property string|null $justifikasi
 * @property int|null $id_pengesah_kedua
 * @property string $status_pengesahan
 * @property string|null $id_aliran_kerja
 * @property string|null $catatan_pengesahan
 * @property Carbon|null $tarikh_pengesahan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class VersiRekodInduk extends ProModel
{
    use HasUuids;

    protected $table = 'versi_rekod_induk';

    protected function casts(): array
    {
        return [
            'nilai_sebelum' => 'array',
            'nilai_selepas' => 'array',
            'kritikal' => 'boolean',
            'tarikh_pengesahan' => 'datetime',
            'no_versi' => 'integer',
        ];
    }
}
