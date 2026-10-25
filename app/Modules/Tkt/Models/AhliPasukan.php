<?php

namespace App\Modules\Tkt\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $id_pasukan
 * @property int $id_pengguna
 * @property string $peranan
 * @property bool $aktif
 * @property-read User|null $pengguna
 * @property-read Pasukan|null $pasukan
 */
class AhliPasukan extends TktModel
{
    protected $table = 'tkt_ahli_pasukan';

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengguna');
    }

    /** @return BelongsTo<Pasukan, $this> */
    public function pasukan(): BelongsTo
    {
        return $this->belongsTo(Pasukan::class, 'id_pasukan');
    }
}
