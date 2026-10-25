<?php

namespace App\Modules\Tkt\Models;

use App\Models\User;
use App\Modules\Tkt\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-TKT-11: one timeline entry. `dalaman` notes are never shown to the
 * reporter; `kelihatan_pelapor` / `pelapor` are the conversation.
 *
 * @property int $id
 * @property string $id_tiket
 * @property string $jenis_catatan
 * @property int|null $id_pengguna
 * @property string|null $kandungan
 * @property string|null $status_dari
 * @property string|null $status_ke
 * @property array<string, mixed>|null $meta
 * @property Carbon $cap_masa
 * @property-read User|null $pengguna
 */
class Kronologi extends TktModel
{
    use AppendOnly;

    protected $table = 'tkt_kronologi';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['meta' => 'array', 'cap_masa' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengguna');
    }
}
