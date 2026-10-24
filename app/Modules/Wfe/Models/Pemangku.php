<?php

namespace App\Modules\Wfe\Models;

use App\Models\User;
use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Wfe\Models\Concerns\AuditedByWfe;
use App\Modules\Wfe\Services\WorkingCalendar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-WFE-21: acting approver (pemangku) for a date range.
 *
 * @property int $id
 * @property int $id_pengguna
 * @property int $id_pemangku
 * @property Carbon $tarikh_mula
 * @property Carbon $tarikh_tamat
 * @property string|null $sebab
 * @property int|null $dicipta_oleh
 * @property Carbon|null $dibatalkan_pada
 * @property-read User $pengguna
 * @property-read User $pemangku
 */
class Pemangku extends Model implements Auditable
{
    use AuditedByWfe;

    protected $table = 'wf_pemangku';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tarikh_mula' => 'date', 'tarikh_tamat' => 'date', 'dibatalkan_pada' => 'datetime'];
    }

    /** @param Builder<Pemangku> $query */
    public function scopeBerkuatKuasa(Builder $query, ?Carbon $on = null): void
    {
        $day = ($on ?? now())->copy()->setTimezone(WorkingCalendar::TZ)->toDateString();
        $query->whereNull('dibatalkan_pada')->whereDate('tarikh_mula', '<=', $day)->whereDate('tarikh_tamat', '>=', $day);
    }

    /** @return BelongsTo<User, $this> */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengguna');
    }

    /** @return BelongsTo<User, $this> */
    public function pemangku(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pemangku');
    }
}
