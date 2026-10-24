<?php

namespace App\Modules\Ptn\Models;

use App\Modules\Ptn\Models\Concerns\UntukPengguna;
use App\Modules\Rbc\Contracts\Concerns\ScopedByAccess;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * SRS-PTN-13/17: a monitoring issue (finding with severity), opened from a
 * visit's "isu"/"kritikal" checklist item or manually; tracked through its
 * corrective actions until closed.
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> untuk(\App\Models\User $user)
 *
 * @property string $id
 * @property string $no_rujukan
 * @property string|null $id_lawatan
 * @property string $lot_id
 * @property string $kod_daerah
 * @property string $kod_mukim
 * @property string $klasifikasi
 * @property string|null $kod_item
 * @property string $kategori_isu
 * @property string $tahap_keterukan
 * @property string $keterangan
 * @property Carbon $tarikh_dibuka
 * @property string $status_isu
 * @property Carbon|null $tarikh_ditutup
 * @property string|null $id_aduan_berkaitan
 * @property string|null $id_kes_berkaitan
 * @property int|null $id_pencipta
 * @property bool $sintetik
 * @property Carbon $created_at
 * @property-read RekodLawatan|null $lawatan
 * @property-read Collection<int, TindakanPembetulan> $tindakan
 */
class IsuPemantauan extends PtnModel
{
    use HasUuids, ScopedByAccess, UntukPengguna;

    protected $table = 'isu_pemantauan';

    /** @var array<string, string> */
    protected array $accessScopes = ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi'];

    protected $casts = ['tarikh_dibuka' => 'date', 'tarikh_ditutup' => 'date', 'sintetik' => 'boolean'];

    public const TERBUKA = ['terbuka', 'dalam_tindakan'];

    public function terbuka(): bool
    {
        return in_array($this->status_isu, self::TERBUKA, true);
    }

    /** @return BelongsTo<RekodLawatan, $this> */
    public function lawatan(): BelongsTo
    {
        return $this->belongsTo(RekodLawatan::class, 'id_lawatan');
    }

    /** @return HasMany<TindakanPembetulan, $this> */
    public function tindakan(): HasMany
    {
        return $this->hasMany(TindakanPembetulan::class, 'id_isu')->orderBy('tarikh_sasaran');
    }
}
