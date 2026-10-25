<?php

namespace App\Modules\Lap\Models;

use App\Models\User;
use App\Modules\Rbc\Contracts\Concerns\ScopedByAccess;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Smart recommendation (SRS-LAP-19): accepted or rejected by an officer with
 * a recorded justification. Labelled a system suggestion (SRS-LAP-21).
 *
 * @property int $id
 * @property int|null $id_hasil
 * @property string $id_lot
 * @property string|null $no_lot
 * @property string|null $kod_daerah
 * @property string|null $kod_mukim
 * @property string $klasifikasi
 * @property string $jenis_analitik
 * @property string $jenis_tindakan
 * @property string $asas_cadangan
 * @property string $status_keputusan baharu | diterima | ditolak
 * @property string|null $justifikasi_pegawai
 * @property int|null $id_pegawai
 * @property Carbon|null $tarikh_keputusan
 * @property string $versi_model
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $pegawai
 */
class CadanganAi extends LapModel
{
    use ScopedByAccess;

    protected $table = 'lap_cadangan_ai';

    protected array $accessScopes = ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi'];

    protected function casts(): array
    {
        return ['tarikh_keputusan' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai');
    }
}
