<?php

namespace App\Modules\Ptn\Models;

use App\Models\User;
use App\Modules\Ptn\Models\Concerns\UntukPengguna;
use App\Modules\Rbc\Contracts\Concerns\ScopedByAccess;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-PTN-13/14/19: corrective action for an issue (responsible officer,
 * target date, steps, progress, completion verification). The colour is
 * derived from the current thresholds (Support\KodWarna) at read time;
 * kod_warna_status keeps the last computed value for indexes and exports.
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> untuk(\App\Models\User $user)
 *
 * @property int $id
 * @property string $id_isu
 * @property string $kod_daerah
 * @property string $kod_mukim
 * @property string $klasifikasi
 * @property string $langkah_tindakan
 * @property int|null $id_pegawai_bertanggungjawab
 * @property Carbon $tarikh_sasaran
 * @property Carbon $tarikh_sasaran_asal
 * @property Carbon|null $tarikh_siap
 * @property string $status_kemajuan
 * @property int $peratus_kemajuan
 * @property string $kod_warna_status
 * @property string|null $justifikasi_lanjutan
 * @property string|null $catatan_kemajuan
 * @property int|null $id_pengesah
 * @property Carbon|null $tarikh_disahkan
 * @property int|null $id_pencipta
 * @property bool $sintetik
 * @property-read IsuPemantauan|null $isu
 * @property-read User|null $pegawai
 */
class TindakanPembetulan extends PtnModel
{
    use ScopedByAccess, UntukPengguna;

    protected $table = 'tindakan_pembetulan';

    /** @var array<string, string> */
    protected array $accessScopes = ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi'];

    protected $casts = [
        'tarikh_sasaran' => 'date', 'tarikh_sasaran_asal' => 'date', 'tarikh_siap' => 'date', 'tarikh_disahkan' => 'datetime',
        'peratus_kemajuan' => 'integer', 'sintetik' => 'boolean',
    ];

    public function selesai(): bool
    {
        return $this->status_kemajuan === 'selesai';
    }

    /** @return BelongsTo<IsuPemantauan, $this> */
    public function isu(): BelongsTo
    {
        return $this->belongsTo(IsuPemantauan::class, 'id_isu');
    }

    /** @return BelongsTo<User, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai_bertanggungjawab');
    }
}
