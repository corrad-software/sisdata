<?php

namespace App\Modules\Kes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-KES-10: deadlines other than hearings — document filing, appeal and
 * compliance deadlines — reminded 14/7/1 days ahead.
 *
 * @property int $id
 * @property string $kes_mahkamah_id
 * @property string $jenis
 * @property Carbon $tarikh
 * @property string|null $keterangan
 * @property string $status
 * @property int|null $id_pengguna
 * @property bool $sintetik
 * @property-read KesMahkamah|null $kes
 */
class TarikhPenting extends KesModel
{
    protected $table = 'kes_tarikh_penting';

    protected function casts(): array
    {
        return ['tarikh' => 'date:Y-m-d', 'sintetik' => 'boolean'];
    }

    /** @return BelongsTo<KesMahkamah, $this> */
    public function kes(): BelongsTo
    {
        return $this->belongsTo(KesMahkamah::class, 'kes_mahkamah_id')->withoutGlobalScope('access');
    }
}
