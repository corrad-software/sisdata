<?php

namespace App\Modules\Ptn\Models;

use App\Models\User;
use App\Modules\Ptn\Models\Concerns\UntukPengguna;
use App\Modules\Rbc\Contracts\Concerns\ScopedByAccess;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-PTN-09/10: generated visit report (snapshot of form + photos), stored
 * in DMS once validated and locked. Corrections are addenda (jenis tambahan)
 * linked to the original — never edits (database trigger).
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> untuk(\App\Models\User $user)
 *
 * @property string $id
 * @property string $id_lawatan
 * @property string $kod_daerah
 * @property string $kod_mukim
 * @property string $klasifikasi
 * @property string $jenis
 * @property string|null $id_laporan_asal
 * @property string $format
 * @property array<string, mixed> $kandungan
 * @property string $cincangan_kandungan
 * @property string|null $catatan_pembetulan
 * @property string|null $id_dokumen_dms
 * @property string $status
 * @property string|null $id_aliran
 * @property Carbon $tarikh_jana
 * @property Carbon|null $tarikh_sah
 * @property int|null $id_pengesah
 * @property bool $status_kunci
 * @property int|null $id_pencipta
 * @property-read RekodLawatan|null $lawatan
 * @property-read User|null $pengesah
 */
class LaporanLawatan extends PtnModel
{
    use HasUuids, ScopedByAccess, UntukPengguna;

    protected $table = 'laporan_lawatan';

    /** @var array<string, string> */
    protected array $accessScopes = ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi'];

    protected $casts = ['kandungan' => 'array', 'tarikh_jana' => 'datetime', 'tarikh_sah' => 'datetime', 'status_kunci' => 'boolean'];

    public function auditExcept(): array
    {
        return ['updated_at', 'kandungan'];
    }

    /** @return BelongsTo<RekodLawatan, $this> */
    public function lawatan(): BelongsTo
    {
        return $this->belongsTo(RekodLawatan::class, 'id_lawatan');
    }

    /** @return BelongsTo<User, $this> */
    public function pengesah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengesah');
    }
}
