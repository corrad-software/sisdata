<?php

namespace App\Modules\Adu\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Process step 8: referral to Penguatkuasaan / Perundangan / Pengurusan
 * Hartanah or an outside agency, with the complaint data carried over.
 *
 * @property int $id
 * @property string $aduan_id
 * @property string $jenis_rujukan
 * @property string|null $nama_agensi
 * @property string $keterangan
 * @property int|null $id_pegawai
 * @property string $status
 * @property string|null $maklum_balas
 * @property-read User|null $pegawai
 */
class RujukanAduan extends AduModel
{
    protected $table = 'rujukan_aduan';

    /** @return BelongsTo<User, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai');
    }
}
