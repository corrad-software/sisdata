<?php

namespace App\Modules\Rbc\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SESI (SRS-RBC-18..20).
 *
 * @property string $id
 * @property string $session_id
 * @property int $user_id
 * @property string|null $kaedah_log_masuk
 * @property string|null $alamat_ip
 * @property string|null $user_agent
 * @property string|null $sidik_peranti
 * @property Carbon $tarikh_mula
 * @property Carbon $tarikh_aktiviti_akhir
 * @property Carbon|null $tarikh_tamat
 * @property string|null $kaedah_tamat
 * @property int|null $ditamatkan_oleh
 * @property-read User $user
 */
class Sesi extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'sesi';

    protected $fillable = [
        'session_id', 'user_id', 'kaedah_log_masuk', 'alamat_ip', 'user_agent', 'sidik_peranti',
        'tarikh_mula', 'tarikh_aktiviti_akhir', 'tarikh_tamat', 'kaedah_tamat', 'ditamatkan_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tarikh_mula' => 'datetime',
            'tarikh_aktiviti_akhir' => 'datetime',
            'tarikh_tamat' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<Sesi> $query */
    public function scopeAktif(Builder $query): void
    {
        $query->whereNull('tarikh_tamat');
    }
}
