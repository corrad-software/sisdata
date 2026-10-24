<?php

namespace App\Modules\Pro\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Carbon;

/**
 * SRS-PRO-15..17: one import session (upload, preview, commit, report).
 *
 * @property string $id
 * @property string $entiti_sasaran
 * @property string $nama_fail
 * @property string $format_fail
 * @property int|null $id_pengguna
 * @property array<mixed>|null $pemetaan
 * @property array<mixed>|null $lajur_fail
 * @property int $bil_baris_dibaca
 * @property int $bil_sah
 * @property int $bil_berjaya
 * @property int $bil_ditolak
 * @property int $bil_diabaikan
 * @property string|null $lokasi_fail_asal
 * @property string|null $catatan_fail_asal
 * @property string $status_sesi
 * @property Carbon|null $tarikh_komit
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SesiImport extends ProModel
{
    use HasUuids;

    protected $table = 'sesi_import';

    protected function casts(): array
    {
        return [
            'pemetaan' => 'array',
            'lajur_fail' => 'array',
            'tarikh_komit' => 'datetime',
        ];
    }
}
