<?php

namespace App\Modules\Ref\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Staging title record (hak_milik, Sulit), linked to its staging lot.
 *
 * @property string $id
 * @property string $no_hak_milik
 * @property string $jenis_hak_milik_kod
 * @property bool $sintetik
 */
class StagingHakMilik extends Model
{
    use HasUuids;

    protected $table = 'staging_hak_milik';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tarikh_daftar' => 'date',
            'tarikh_tamat' => 'date',
            'sintetik' => 'boolean',
            'dimuat_pada' => 'datetime',
        ];
    }

    /** @return BelongsTo<StagingLotTanah, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(StagingLotTanah::class, 'staging_lot_tanah_id');
    }

    /** @return HasMany<StagingPemilik, $this> */
    public function pemilik(): HasMany
    {
        return $this->hasMany(StagingPemilik::class, 'staging_hak_milik_id');
    }
}
