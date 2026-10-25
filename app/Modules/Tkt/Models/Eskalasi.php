<?php

namespace App\Modules\Tkt\Models;

use App\Models\User;
use App\Modules\Tkt\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-TKT-09 / §18.5: one warning or escalation — level, recipient, reason, time.
 *
 * @property int $id
 * @property string $id_tiket
 * @property int $peringkat
 * @property string $sasaran
 * @property int|null $id_penerima
 * @property string|null $jenis_penerima
 * @property string $sebab
 * @property int $kitaran
 * @property string|null $catatan
 * @property int|null $id_pengguna
 * @property Carbon $cap_masa
 * @property-read User|null $penerima
 * @property-read Tiket|null $tiket
 */
class Eskalasi extends TktModel
{
    use AppendOnly;

    protected $table = 'tkt_eskalasi';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['cap_masa' => 'datetime', 'peringkat' => 'integer', 'kitaran' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function penerima(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_penerima');
    }

    /** @return BelongsTo<Tiket, $this> */
    public function tiket(): BelongsTo
    {
        return $this->belongsTo(Tiket::class, 'id_tiket');
    }
}
