<?php

namespace App\Modules\Kes\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-KES-20: officer reassignment history with justification (append-only).
 *
 * @property int $id
 * @property string $kes_mahkamah_id
 * @property int|null $id_pegawai_lama
 * @property int $id_pegawai_baharu
 * @property Carbon $tarikh_tukar
 * @property string $justifikasi
 * @property int|null $id_pengguna
 * @property-read User|null $lama
 * @property-read User|null $baharu
 */
class PegawaiSejarah extends Sejarah
{
    protected $table = 'kes_pegawai_sejarah';

    protected function casts(): array
    {
        return ['tarikh_tukar' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function lama(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai_lama');
    }

    /** @return BelongsTo<User, $this> */
    public function baharu(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai_baharu');
    }
}
