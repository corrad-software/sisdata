<?php

namespace App\Modules\Kes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Out-of-court settlement proposal; approved through MOD-WFE
 * (`kes.penyelesaian`) before it is sealed.
 *
 * @property int $id
 * @property string $kes_mahkamah_id
 * @property string $terma
 * @property string|null $nilai_penyelesaian
 * @property Carbon $tarikh_cadangan
 * @property Carbon|null $tarikh_persetujuan
 * @property string $status
 * @property string|null $id_dokumen_perjanjian
 * @property string|null $id_instans_aliran
 * @property int|null $id_pengguna
 * @property bool $sintetik
 * @property-read KesMahkamah|null $kes
 */
class Penyelesaian extends KesModel
{
    protected $table = 'kes_penyelesaian';

    protected function casts(): array
    {
        return ['tarikh_cadangan' => 'date:Y-m-d', 'tarikh_persetujuan' => 'date:Y-m-d', 'sintetik' => 'boolean'];
    }

    /** @return BelongsTo<KesMahkamah, $this> */
    public function kes(): BelongsTo
    {
        return $this->belongsTo(KesMahkamah::class, 'kes_mahkamah_id')->withoutGlobalScope('access');
    }
}
