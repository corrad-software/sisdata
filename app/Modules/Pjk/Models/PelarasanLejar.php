<?php

namespace App\Modules\Pjk\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-PJK-28: ledger adjustment / reversal / waiver request. Takes effect only
 * after WFE `pjk.pelarasan_lejar` completes with lulus by a second officer.
 *
 * @property int $id
 * @property string $kontrak_id
 * @property string $jenis
 * @property int|null $lejar_id_sasaran
 * @property int|null $caj_id
 * @property string $amaun
 * @property string $justifikasi
 * @property string $status
 * @property int $id_pemohon
 * @property int|null $id_pelulus
 * @property Carbon|null $tarikh_keputusan
 * @property string|null $ulasan
 * @property string|null $id_instans_aliran
 * @property Carbon|null $created_at
 * @property-read Kontrak $kontrak
 */
class PelarasanLejar extends PjkModel
{
    public const SUBJECT = 'pelarasan_lejar';

    protected $table = 'pelarasan_lejar';

    protected function casts(): array
    {
        return ['tarikh_keputusan' => 'datetime'];
    }

    /** @return BelongsTo<Kontrak, $this> */
    public function kontrak(): BelongsTo
    {
        return $this->belongsTo(Kontrak::class, 'kontrak_id');
    }
}
