<?php

namespace App\Modules\Wfe\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-WFE-12: who changed a definition, when, how (method) and with which
 * instruction.
 *
 * @property int $id
 * @property int $id_definisi
 * @property int|null $id_pengguna
 * @property string $tindakan
 * @property string $kaedah
 * @property string|null $arahan
 * @property array<string, mixed>|null $butiran
 * @property Carbon $created_at
 * @property-read User|null $pengguna
 */
class DefinisiLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'wf_definisi_log';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['butiran' => 'array', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengguna');
    }
}
