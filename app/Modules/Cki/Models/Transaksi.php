<?php

namespace App\Modules\Cki\Models;

use App\Modules\Rbc\Contracts\Concerns\ScopedByAccess;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * SRS-CKI-11..15: one payment of a bill (MAIS → land office / PBT), from
 * request through approval, execution, receipt and completion.
 *
 * @property string $id
 * @property string $no_permohonan
 * @property string $id_bil
 * @property string $kaedah_bayaran
 * @property string $amaun
 * @property Carbon|null $tarikh_bayaran
 * @property string|null $no_rujukan_eft
 * @property string|null $no_bank_draf
 * @property string|null $bank_penerbit
 * @property string|null $rujukan_fpx
 * @property string|null $nama_kaunter
 * @property string|null $no_resit
 * @property string|null $id_dokumen_resit
 * @property string|null $id_dokumen_permohonan
 * @property string $status_transaksi
 * @property int|null $id_kelulusan
 * @property bool $melalui_api
 * @property string|null $rujukan_grp
 * @property string|null $status_grp
 * @property string|null $rujukan_transaksi_api
 * @property string $kod_daerah
 * @property string|null $kod_mukim
 * @property string|null $catatan
 * @property int|null $id_pemohon
 * @property Carbon $created_at
 * @property-read Cukai|null $bil
 * @property-read Kelulusan|null $kelulusan
 */
class Transaksi extends CkiModel
{
    use HasUuids, ScopedByAccess;

    public const SUBJECT = 'transaksi_bayaran_cukai';

    protected $table = 'transaksi_bayaran_cukai';

    protected array $accessScopes = ['daerah' => 'kod_daerah'];

    protected $casts = ['tarikh_bayaran' => 'date', 'melalui_api' => 'boolean'];

    /** @return BelongsTo<Cukai, $this> */
    public function bil(): BelongsTo
    {
        return $this->belongsTo(Cukai::class, 'id_bil')->withoutGlobalScope('access');
    }

    /** @return BelongsTo<Kelulusan, $this> */
    public function kelulusan(): BelongsTo
    {
        return $this->belongsTo(Kelulusan::class, 'id_kelulusan');
    }

    /** @return HasMany<Resit, $this> */
    public function resit(): HasMany
    {
        return $this->hasMany(Resit::class, 'id_transaksi');
    }
}
