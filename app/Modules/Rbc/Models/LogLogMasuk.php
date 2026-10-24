<?php

namespace App\Modules\Rbc\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * LOG_LOG_MASUK (SRS-RBC-21).
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $pengecam
 * @property string $kaedah
 * @property bool $berjaya
 * @property string|null $sebab
 * @property string|null $alamat_ip
 * @property string|null $user_agent
 * @property string|null $peranti
 * @property string|null $pelayar
 * @property string|null $platform
 * @property string|null $sidik_peranti
 * @property string|null $lokasi
 * @property string|null $negara
 * @property bool $mencurigakan
 * @property Carbon $created_at
 * @property-read User|null $user
 */
class LogLogMasuk extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'log_log_masuk';

    protected $fillable = [
        'user_id', 'pengecam', 'kaedah', 'berjaya', 'sebab', 'alamat_ip', 'user_agent', 'peranti',
        'pelayar', 'platform', 'sidik_peranti', 'lokasi', 'negara', 'mencurigakan',
    ];

    protected function casts(): array
    {
        return ['berjaya' => 'boolean', 'mencurigakan' => 'boolean', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
