<?php

namespace App\Modules\Kes\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-KES-07: every status change with its date (append-only).
 *
 * @property int $id
 * @property string $kes_mahkamah_id
 * @property string|null $status_lama
 * @property string $status_baharu
 * @property Carbon $tarikh_perubahan
 * @property int|null $id_pengguna
 * @property string|null $catatan
 * @property-read User|null $pengguna
 */
class StatusSejarah extends Sejarah
{
    protected $table = 'kes_status_sejarah';

    protected function casts(): array
    {
        return ['tarikh_perubahan' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengguna');
    }
}
