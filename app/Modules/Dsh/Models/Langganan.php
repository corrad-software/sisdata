<?php

namespace App\Modules\Dsh\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $id_pengguna
 * @property string $kekerapan
 * @property string $aras
 * @property array<string, mixed> $penapis
 * @property bool $aktif
 * @property Carbon|null $terakhir_dihantar
 * @property-read User|null $pengguna
 */
class Langganan extends DshModel
{
    protected $table = 'dsh_langganan';

    protected function casts(): array
    {
        return ['penapis' => 'array', 'aktif' => 'boolean', 'terakhir_dihantar' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengguna');
    }
}
