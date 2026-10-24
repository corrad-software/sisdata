<?php

namespace App\Modules\Per\Models;

use App\Models\User;
use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Aud\Contracts\HasClassification;
use App\Modules\Per\Models\Concerns\AuditedByPer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * SRS-PER-16/22: a supporting document of a case. The file lives in MOD-DMS
 * (`rujukan_dms`); this row ties it to a checklist item and records where the
 * physical original is kept.
 *
 * @property int $id
 * @property string $id_pemerolehan
 * @property int|null $id_semak
 * @property string $jenis_dokumen
 * @property string|null $rujukan_dms
 * @property int $no_versi
 * @property Carbon|null $tarikh_dokumen
 * @property Carbon|null $tarikh_terima
 * @property int|null $id_pegawai_terima
 * @property string $status_dokumen
 * @property string|null $kod_peti_besi
 * @property string|null $no_fail
 * @property string|null $lokasi_rak
 * @property string|null $status_dokumen_asal
 * @property string|null $catatan
 * @property-read SemakDokumen|null $semak
 * @property-read User|null $pegawaiTerima
 * @property-read Collection<int, PergerakanDokumen> $pergerakan
 */
class DokumenPemerolehan extends Model implements Auditable, HasClassification
{
    use AuditedByPer;

    public const STATUS_ASAL = ['asal_dalam_simpanan', 'salinan_diakui', 'salinan', 'dikeluarkan', 'tiada'];

    protected $table = 'dokumen_pemerolehan';

    protected $guarded = ['id'];

    protected $casts = ['tarikh_dokumen' => 'date', 'tarikh_terima' => 'date', 'no_versi' => 'integer'];

    /** @return BelongsTo<SemakDokumen, $this> */
    public function semak(): BelongsTo
    {
        return $this->belongsTo(SemakDokumen::class, 'id_semak');
    }

    /** @return BelongsTo<User, $this> */
    public function pegawaiTerima(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai_terima');
    }

    /** @return HasMany<PergerakanDokumen, $this> */
    public function pergerakan(): HasMany
    {
        return $this->hasMany(PergerakanDokumen::class, 'id_dokumen_pemerolehan')->orderBy('tarikh');
    }
}
