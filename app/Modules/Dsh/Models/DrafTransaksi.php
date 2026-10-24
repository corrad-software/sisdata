<?php

namespace App\Modules\Dsh\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $id_pengguna
 * @property string $modul
 * @property string $jenis
 * @property string $tajuk
 * @property string|null $pautan
 * @property array<string, mixed> $muatan_json
 * @property Carbon $tarikh_simpan
 * @property Carbon $updated_at
 */
class DrafTransaksi extends DshModel
{
    use HasUuids;

    protected $table = 'dsh_draf_transaksi';

    protected function casts(): array
    {
        return ['muatan_json' => 'array', 'tarikh_simpan' => 'datetime'];
    }

    public function auditSensitive(): array
    {
        // Draft content may hold anything the form had: record that it changed, not what.
        return ['muatan_json'];
    }
}
