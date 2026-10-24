<?php

namespace App\Modules\Pjk\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-PJK-13: deposit / security held separately from rent; settled at
 * termination with Finance approval (WFE `pjk.penyelarasan_deposit`).
 *
 * @property int $id
 * @property string $kontrak_id
 * @property string $jenis
 * @property string $amaun
 * @property int|null $caj_id
 * @property Carbon|null $tarikh_terima
 * @property string $status
 * @property string $amaun_tolakan
 * @property string|null $justifikasi_tolakan
 * @property string|null $amaun_dipulangkan
 * @property Carbon|null $tarikh_pulangan
 * @property string|null $id_instans_aliran
 * @property int|null $id_pemohon_penyelarasan
 * @property int|null $id_pelulus
 * @property-read Kontrak $kontrak
 */
class DepositCagaran extends PjkModel
{
    public const SUBJECT = 'deposit_cagaran';

    protected $table = 'deposit_cagaran';

    protected function casts(): array
    {
        return ['tarikh_terima' => 'date:Y-m-d', 'tarikh_pulangan' => 'date:Y-m-d'];
    }

    /** @return BelongsTo<Kontrak, $this> */
    public function kontrak(): BelongsTo
    {
        return $this->belongsTo(Kontrak::class, 'kontrak_id');
    }
}
