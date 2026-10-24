<?php

namespace App\Modules\Rbc\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * STATUS_AKAUN_LOG (SRS-RBC-15).
 *
 * @property int $id
 * @property int $user_id
 * @property string $status_lama
 * @property string $status_baru
 * @property string|null $sebab
 * @property int|null $dilakukan_oleh
 * @property Carbon $created_at
 * @property-read User|null $pelaku
 */
class StatusAkaunLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'status_akaun_log';

    protected $fillable = ['user_id', 'status_lama', 'status_baru', 'sebab', 'dilakukan_oleh'];

    /** @return BelongsTo<User, $this> */
    public function pelaku(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dilakukan_oleh');
    }
}
