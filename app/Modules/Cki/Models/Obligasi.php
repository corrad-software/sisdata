<?php

namespace App\Modules\Cki\Models;

use App\Modules\Rbc\Contracts\Concerns\ScopedByAccess;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SRS-CKI-01: a tax obligation (register entry) of a lot / premise — one
 * account at one issuing agency. Scoped by daerah/mukim/klasifikasi.
 *
 * @property string $id
 * @property string $lot_id
 * @property int|null $id_premis
 * @property string $jenis_cukai
 * @property string $pihak_pengeluar
 * @property string|null $kod_pbt
 * @property string|null $kod_ptd
 * @property string $kod_agensi
 * @property string $no_akaun_cukai
 * @property string $status_liputan_api
 * @property string $kod_daerah
 * @property string $kod_mukim
 * @property string $klasifikasi
 * @property bool $aktif
 * @property bool $sintetik
 * @property string|null $catatan
 * @property int|null $id_pencipta
 * @property-read Liputan|null $agensi
 */
class Obligasi extends CkiModel
{
    use HasUuids, ScopedByAccess, SoftDeletes;

    public const SUBJECT = 'obligasi_cukai';

    protected $table = 'obligasi_cukai';

    protected array $accessScopes = ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi'];

    protected $casts = ['aktif' => 'boolean', 'sintetik' => 'boolean'];

    /** @return HasMany<Cukai, $this> */
    public function bil(): HasMany
    {
        return $this->hasMany(Cukai::class, 'obligasi_id');
    }

    /** @return BelongsTo<Liputan, $this> */
    public function agensi(): BelongsTo
    {
        return $this->belongsTo(Liputan::class, 'kod_agensi', 'kod_agensi');
    }
}
