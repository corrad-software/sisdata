<?php

namespace App\Modules\Kes\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-KES-08: one proceeding (hearing, mention, case management, decision…)
 * with outcome, court directions and the next date.
 *
 * @property int $id
 * @property string $kes_mahkamah_id
 * @property Carbon $tarikh_prosiding
 * @property string $jenis_prosiding
 * @property string $status
 * @property string|null $hasil
 * @property string|null $arahan_mahkamah
 * @property Carbon|null $tarikh_berikutnya
 * @property string|null $jenis_berikutnya
 * @property string|null $catatan
 * @property string $sumber
 * @property int|null $id_pegawai_rekod
 * @property Carbon|null $eskalasi_dihantar_pada
 * @property bool $sintetik
 * @property-read KesMahkamah|null $kes
 * @property-read User|null $pegawaiRekod
 */
class Prosiding extends KesModel
{
    protected $table = 'kes_prosiding';

    protected function casts(): array
    {
        return [
            'tarikh_prosiding' => 'datetime',
            'tarikh_berikutnya' => 'date:Y-m-d',
            'eskalasi_dihantar_pada' => 'datetime',
            'sintetik' => 'boolean',
        ];
    }

    /** @return BelongsTo<KesMahkamah, $this> */
    public function kes(): BelongsTo
    {
        return $this->belongsTo(KesMahkamah::class, 'kes_mahkamah_id')->withoutGlobalScope('access');
    }

    /** @return BelongsTo<User, $this> */
    public function pegawaiRekod(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai_rekod');
    }
}
