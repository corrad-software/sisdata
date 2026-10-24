<?php

namespace App\Modules\Rbc\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * RECERT_KITARAN (SRS-RBC-27).
 *
 * @property int $id
 * @property string $nama
 * @property string|null $bahagian
 * @property Carbon $tarikh_mula
 * @property Carbon $tarikh_akhir
 * @property string $status
 * @property int|null $dicipta_oleh
 * @property Carbon|null $ditutup_pada
 * @property Carbon $created_at
 */
class RecertKitaran extends Model
{
    public const DIBUKA = 'dibuka';

    public const DITUTUP = 'ditutup';

    protected $table = 'recert_kitaran';

    protected $fillable = ['nama', 'bahagian', 'tarikh_mula', 'tarikh_akhir', 'status', 'dicipta_oleh', 'ditutup_pada'];

    protected $attributes = ['status' => self::DIBUKA];

    protected function casts(): array
    {
        return ['tarikh_mula' => 'date', 'tarikh_akhir' => 'date', 'ditutup_pada' => 'datetime'];
    }

    /** @return HasMany<RecertKeputusan, $this> */
    public function keputusan(): HasMany
    {
        return $this->hasMany(RecertKeputusan::class, 'kitaran_id');
    }
}
