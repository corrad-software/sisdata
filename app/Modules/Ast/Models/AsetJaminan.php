<?php

namespace App\Modules\Ast\Models;

use App\Modules\Ast\Models\Concerns\SkopLot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Warranty of an asset / component (expiry reminders through MOD-NTF).
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> untuk(\App\Models\User $user)
 *
 * @property int $id
 * @property string $id_aset
 * @property string $perkara
 * @property string|null $pembekal
 * @property Carbon $tarikh_mula
 * @property Carbon $tarikh_tamat
 * @property string|null $id_dokumen_dms
 * @property string|null $catatan
 * @property string $kod_daerah
 * @property string $kod_mukim
 * @property string $klasifikasi
 * @property bool $sintetik
 * @property-read AsetFizikal|null $aset
 */
class AsetJaminan extends AstModel
{
    use SkopLot;

    protected $table = 'aset_jaminan';

    protected $casts = ['tarikh_mula' => 'date', 'tarikh_tamat' => 'date', 'sintetik' => 'boolean'];

    /** @return BelongsTo<AsetFizikal, $this> */
    public function aset(): BelongsTo
    {
        return $this->belongsTo(AsetFizikal::class, 'id_aset');
    }
}
