<?php

namespace App\Modules\Kes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-KES-15: one legal cost item (fee, court cost, related cost). Amounts
 * are DECIMAL strings and summed with bcmath. Payment approval goes through
 * MOD-WFE (`kes.bayaran_kos`); the approved request is sent to GRP MAIS
 * (Cki\Contracts\GrpPembayaran) for payment and verification.
 *
 * @property int $id
 * @property string $kes_mahkamah_id
 * @property string $kategori_kos
 * @property string|null $keterangan
 * @property string $amaun
 * @property Carbon $tarikh
 * @property string|null $no_resit
 * @property string|null $id_dokumen
 * @property int|null $kes_panel_peguam_id
 * @property string $status_bayaran
 * @property string|null $id_instans_aliran
 * @property string|null $rujukan_grp
 * @property Carbon|null $tarikh_bayar
 * @property int|null $id_pengguna
 * @property bool $sintetik
 * @property-read KesMahkamah|null $kes
 */
class KosGuaman extends KesModel
{
    protected $table = 'kos_guaman';

    public const KATEGORI = ['fi_peguam', 'kos_mahkamah', 'kos_berkaitan'];

    protected function casts(): array
    {
        return ['tarikh' => 'date:Y-m-d', 'tarikh_bayar' => 'date:Y-m-d', 'sintetik' => 'boolean'];
    }

    /** @return BelongsTo<KesMahkamah, $this> */
    public function kes(): BelongsTo
    {
        return $this->belongsTo(KesMahkamah::class, 'kes_mahkamah_id')->withoutGlobalScope('access');
    }

    /** @return BelongsTo<PanelPeguam, $this> */
    public function panel(): BelongsTo
    {
        return $this->belongsTo(PanelPeguam::class, 'kes_panel_peguam_id');
    }
}
