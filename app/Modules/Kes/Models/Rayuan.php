<?php

namespace App\Modules\Kes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Appeal against a decision. When MAIS appeals, the decision to file is
 * approved through MOD-WFE (`kes.rayuan`) before it is filed.
 *
 * @property int $id
 * @property string $kes_mahkamah_id
 * @property int|null $kes_keputusan_id
 * @property string $pihak_merayu
 * @property string|null $mahkamah_rayuan
 * @property string|null $peringkat_mahkamah
 * @property string|null $no_kes_rayuan
 * @property Carbon|null $tarikh_had_failan
 * @property Carbon|null $tarikh_failan
 * @property string|null $alasan
 * @property string $status
 * @property string|null $keputusan_rayuan
 * @property string|null $id_instans_aliran
 * @property int|null $id_pengguna
 * @property bool $sintetik
 * @property-read KesMahkamah|null $kes
 */
class Rayuan extends KesModel
{
    protected $table = 'kes_rayuan';

    protected function casts(): array
    {
        return ['tarikh_had_failan' => 'date:Y-m-d', 'tarikh_failan' => 'date:Y-m-d', 'sintetik' => 'boolean'];
    }

    /** @return BelongsTo<KesMahkamah, $this> */
    public function kes(): BelongsTo
    {
        return $this->belongsTo(KesMahkamah::class, 'kes_mahkamah_id')->withoutGlobalScope('access');
    }
}
