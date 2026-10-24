<?php

namespace App\Modules\Ast\Models;

use App\Models\User;
use App\Modules\Ast\Models\Concerns\SkopLot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Disposal / write-off / demolition request, decided through MOD-WFE (SRS-GEN-03); approval archives the asset.
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> untuk(\App\Models\User $user)
 *
 * @property int $id
 * @property string $id_aset
 * @property string $jenis
 * @property string $sebab
 * @property string|null $kaedah
 * @property string|null $nilai_pelupusan
 * @property Carbon|null $tarikh_dicadang
 * @property string $status
 * @property string|null $id_aliran
 * @property int|null $id_pemohon
 * @property int|null $id_pelulus
 * @property Carbon|null $tarikh_keputusan
 * @property string|null $ulasan
 * @property string $kod_daerah
 * @property string $kod_mukim
 * @property string $klasifikasi
 * @property Carbon $created_at
 * @property-read AsetFizikal|null $aset
 * @property-read User|null $pemohon
 */
class AsetPelupusan extends AstModel
{
    use SkopLot;

    protected $table = 'aset_pelupusan';

    protected $casts = ['tarikh_dicadang' => 'date', 'tarikh_keputusan' => 'datetime', 'nilai_pelupusan' => 'decimal:2'];

    /** @return BelongsTo<AsetFizikal, $this> */
    public function aset(): BelongsTo
    {
        return $this->belongsTo(AsetFizikal::class, 'id_aset');
    }

    /** @return BelongsTo<User, $this> */
    public function pemohon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pemohon');
    }
}
