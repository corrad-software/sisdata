<?php

namespace App\Modules\Adu\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-ADU-17: a scheduled / completed site visit for a complaint.
 *
 * @property int $id
 * @property string $aduan_id
 * @property string|null $no_rujukan_aduan
 * @property Carbon $tarikh_jadual
 * @property Carbon|null $tarikh_lawatan
 * @property int|null $id_pegawai
 * @property string|null $penemuan
 * @property string $status
 * @property string|null $id_rekod_lawatan_ptn
 * @property-read User|null $pegawai
 * @property-read Aduan $aduan
 */
class LawatanTapak extends AduModel
{
    protected $table = 'lawatan_tapak_aduan';

    protected function casts(): array
    {
        return ['tarikh_jadual' => 'date:Y-m-d', 'tarikh_lawatan' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai');
    }

    /** @return BelongsTo<Aduan, $this> */
    public function aduan(): BelongsTo
    {
        return $this->belongsTo(Aduan::class, 'aduan_id');
    }
}
