<?php

namespace App\Modules\Ptn\Models;

use App\Models\User;
use App\Modules\Ptn\Models\Concerns\UntukPengguna;
use App\Modules\Rbc\Contracts\Concerns\ScopedByAccess;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-PTN-08: a proposed change to the master lot record arising from a
 * visit. Applied to the master record only after MOD-WFE approval.
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> untuk(\App\Models\User $user)
 *
 * @property int $id
 * @property string $id_lawatan
 * @property string $lot_id
 * @property string $kod_daerah
 * @property string $kod_mukim
 * @property string $klasifikasi
 * @property string $medan
 * @property string|null $nilai_semasa
 * @property string $nilai_dicadang
 * @property string $justifikasi
 * @property string $status
 * @property string|null $id_aliran
 * @property int|null $id_pencadang
 * @property int|null $id_pelulus
 * @property Carbon|null $tarikh_keputusan
 * @property string|null $ulasan_keputusan
 * @property-read User|null $pencadang
 */
class CadanganKemaskiniLot extends PtnModel
{
    use ScopedByAccess, UntukPengguna;

    protected $table = 'cadangan_kemaskini_lot';

    /** @var array<string, string> */
    protected array $accessScopes = ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi'];

    protected $casts = ['tarikh_keputusan' => 'datetime'];

    /** @return BelongsTo<User, $this> */
    public function pencadang(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pencadang');
    }
}
