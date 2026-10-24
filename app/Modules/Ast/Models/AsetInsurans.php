<?php

namespace App\Modules\Ast\Models;

use App\Modules\Ast\Models\Concerns\SkopLot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Insurance policy of an asset (expiry reminders through MOD-NTF).
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> untuk(\App\Models\User $user)
 *
 * @property int $id
 * @property string $id_aset
 * @property string $no_polisi
 * @property string $penanggung
 * @property string|null $jenis_perlindungan
 * @property string|null $nilai_dilindungi
 * @property string|null $premium
 * @property Carbon $tarikh_mula
 * @property Carbon $tarikh_tamat
 * @property string|null $id_dokumen_dms
 * @property string $kod_daerah
 * @property string $kod_mukim
 * @property string $klasifikasi
 * @property bool $sintetik
 * @property-read AsetFizikal|null $aset
 */
class AsetInsurans extends AstModel
{
    use SkopLot;

    protected $table = 'aset_insurans';

    protected $casts = ['tarikh_mula' => 'date', 'tarikh_tamat' => 'date', 'nilai_dilindungi' => 'decimal:2', 'premium' => 'decimal:2', 'sintetik' => 'boolean'];

    /** @return BelongsTo<AsetFizikal, $this> */
    public function aset(): BelongsTo
    {
        return $this->belongsTo(AsetFizikal::class, 'id_aset');
    }
}
