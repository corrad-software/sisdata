<?php

namespace App\Modules\Pjk\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-PJK-17: official receipt — sequential serial, immutable (DB trigger);
 * cancelled only by a separate credit receipt (jenis resit_kredit).
 *
 * @property int $id
 * @property string $no_resit
 * @property string $jenis
 * @property int $bayaran_id
 * @property string $kontrak_id
 * @property string $amaun
 * @property Carbon $tarikh
 * @property int|null $id_resit_asal
 * @property string|null $id_dokumen
 * @property string|null $emel_penerima
 * @property Carbon $created_at
 * @property-read Bayaran $bayaran
 */
class Resit extends PjkModel
{
    public const UPDATED_AT = null;

    protected $table = 'resit';

    protected function casts(): array
    {
        return ['tarikh' => 'date:Y-m-d'];
    }

    public function auditSensitive(): array
    {
        return ['emel_penerima'];
    }

    /** @return BelongsTo<Bayaran, $this> */
    public function bayaran(): BelongsTo
    {
        return $this->belongsTo(Bayaran::class, 'bayaran_id');
    }
}
