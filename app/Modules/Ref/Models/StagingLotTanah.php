<?php

namespace App\Modules\Ref\Models;

use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Staging copy of a lot record (source of MOD-PRO's lot_tanah import),
 * with lineage per SRS-DAT-08.
 *
 * @property string $id
 * @property string $no_lot
 * @property string $jenis_lot
 * @property string $daerah_kod
 * @property string $mukim_kod
 * @property float $keluasan
 * @property string $unit_keluasan
 * @property bool $wakaf
 * @property bool $baitulmal
 * @property bool $sintetik
 * @property string $sumber_sistem
 * @property string $fail_asal
 * @property string $kunci_asal
 */
class StagingLotTanah extends Model implements Auditable
{
    use HasUuids;

    protected $table = 'staging_lot_tanah';

    protected $guarded = [];

    protected $hidden = ['geometri'];

    protected function casts(): array
    {
        return [
            'keluasan' => 'float',
            'wakaf' => 'boolean',
            'baitulmal' => 'boolean',
            'sintetik' => 'boolean',
            'dimuat_pada' => 'datetime',
        ];
    }

    /** @return BelongsTo<MigrasiLarian, $this> */
    public function larian(): BelongsTo
    {
        return $this->belongsTo(MigrasiLarian::class, 'migrasi_larian_id');
    }

    /** @return HasMany<StagingHakMilik, $this> */
    public function hakMilik(): HasMany
    {
        return $this->hasMany(StagingHakMilik::class, 'staging_lot_tanah_id');
    }

    public function auditModule(): string
    {
        return 'ref';
    }

    public function auditExcept(): array
    {
        return ['updated_at', 'geometri'];
    }

    public function auditSensitive(): array
    {
        return [];
    }
}
