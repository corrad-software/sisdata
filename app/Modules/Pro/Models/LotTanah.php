<?php

namespace App\Modules\Pro\Models;

use App\Models\User;
use App\Modules\Rbc\Contracts\Concerns\ScopedByAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Master lot record (SRS-PRO-06, ADR-01). Scoped by daerah, mukim and
 * klasifikasi for the logged-in user (SRS-RBC-07); audited field by field.
 *
 * @property string $id
 * @property string $no_lot
 * @property string|null $no_lot_normal
 * @property string $kod_mukim
 * @property string $kod_daerah
 * @property string $kod_negeri
 * @property string|null $keluasan
 * @property string $unit_keluasan
 * @property string|null $kategori_guna_tanah
 * @property string|null $zoning
 * @property string|null $syarat_nyata
 * @property string|null $bahagian_pemilik
 * @property string $status_lot
 * @property string $klasifikasi
 * @property int|null $id_data_owner
 * @property int|null $id_data_steward
 * @property int $versi_rekod
 * @property bool $sintetik
 * @property string|null $sumber_sistem
 * @property string|null $fail_asal
 * @property string|null $kunci_asal
 * @property string|null $id_migrasi_larian
 * @property string|null $id_sesi_import
 * @property string|null $id_lot_gabung
 * @property string|null $status_sebelum_arkib
 * @property Carbon|null $diarkib_pada
 * @property list<string>|null $tag
 * @property string|null $catatan
 * @property int|null $id_pencipta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DataSpatial|null $spatialSemasa
 */
class LotTanah extends ProModel
{
    use HasUuids, ScopedByAccess, SoftDeletes;

    protected $table = 'lot_tanah';

    /** @var array<string, string> */
    protected array $accessScopes = ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi'];

    /** Fields an officer edits through the lot form / import (everything else is system-managed). */
    public const EDITABLE = [
        'no_lot', 'kod_mukim', 'kod_daerah', 'keluasan', 'unit_keluasan', 'kategori_guna_tanah', 'zoning',
        'syarat_nyata', 'bahagian_pemilik', 'status_lot', 'klasifikasi', 'id_data_owner', 'id_data_steward', 'tag', 'catatan',
    ];

    public const STATUS = ['draf', 'aktif', 'tidak_aktif', 'digabung', 'arkib'];

    public const BAHAGIAN = ['hartanah', 'wakaf', 'baitulmal'];

    public const KLASIFIKASI = ['awam', 'dalaman', 'sulit', 'rahsia'];

    public const UNIT = ['m2', 'hektar', 'ekar'];

    protected $hidden = ['koordinat_pusat'];

    protected function casts(): array
    {
        return [
            'tag' => 'array',
            'sintetik' => 'boolean',
            'versi_rekod' => 'integer',
            'diarkib_pada' => 'datetime',
        ];
    }

    public function auditExcept(): array
    {
        return ['created_at', 'updated_at', 'koordinat_pusat', 'no_lot_normal'];
    }

    /** @return HasOne<DataSpatial, $this> */
    public function spatialSemasa(): HasOne
    {
        return $this->hasOne(DataSpatial::class, 'lot_id')->where('semasa', true);
    }

    /** @return HasMany<DataSpatial, $this> */
    public function spatial(): HasMany
    {
        return $this->hasMany(DataSpatial::class, 'lot_id');
    }

    /** @return BelongsTo<User, $this> */
    public function steward(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_data_steward');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_data_owner');
    }

    /** @param Builder<LotTanah> $query */
    public function scopeTidakDiarkib(Builder $query): void
    {
        $query->where('status_lot', '!=', 'arkib');
    }

    public function keluasanM2(): ?float
    {
        if ($this->keluasan === null) {
            return null;
        }

        return match ($this->unit_keluasan) {
            'hektar' => (float) $this->keluasan * 10000,
            'ekar' => (float) $this->keluasan * 4046.8564224,
            default => (float) $this->keluasan,
        };
    }
}
