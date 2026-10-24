<?php

namespace App\Modules\Ptn\Models;

use App\Models\User;
use App\Modules\Ptn\Models\Concerns\UntukPengguna;
use App\Modules\Rbc\Contracts\Concerns\ScopedByAccess;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * SRS-PTN-01: one planned monitoring task (lot, date, officer, objective,
 * risk priority, status). Scoped by daerah/mukim/klasifikasi of the lot.
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> untuk(\App\Models\User $user)
 *
 * @property string $id
 * @property string $lot_id
 * @property string $kod_daerah
 * @property string $kod_mukim
 * @property string $klasifikasi
 * @property int $tahun
 * @property int $kekerapan
 * @property int $bil_dalam_tahun
 * @property Carbon $tarikh_sasaran
 * @property int|null $id_pegawai_bertugas
 * @property string $objektif
 * @property string $jenis_pemeriksaan
 * @property int|null $id_templat
 * @property string $keutamaan_risiko
 * @property string $skor_keutamaan
 * @property array<string, mixed> $faktor_keutamaan
 * @property string $status_pelaksanaan
 * @property bool $dijana_automatik
 * @property int $versi
 * @property int|null $id_pencipta
 * @property bool $sintetik
 * @property Carbon $created_at
 * @property-read User|null $pegawai
 * @property-read TemplatSenaraiSemak|null $templat
 */
class JadualPemantauan extends PtnModel
{
    use HasUuids, ScopedByAccess, UntukPengguna;

    protected $table = 'jadual_pemantauan';

    /** @var array<string, string> */
    protected array $accessScopes = ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi'];

    protected $casts = [
        'tarikh_sasaran' => 'date', 'faktor_keutamaan' => 'array', 'dijana_automatik' => 'boolean', 'sintetik' => 'boolean',
        'tahun' => 'integer', 'kekerapan' => 'integer', 'versi' => 'integer', 'bil_dalam_tahun' => 'integer',
    ];

    public const TERBUKA = ['dirancang', 'ditugaskan', 'dalam_pelaksanaan'];

    /** @return BelongsTo<User, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai_bertugas');
    }

    /** @return BelongsTo<TemplatSenaraiSemak, $this> */
    public function templat(): BelongsTo
    {
        return $this->belongsTo(TemplatSenaraiSemak::class, 'id_templat');
    }

    /** @return HasMany<RekodLawatan, $this> */
    public function lawatan(): HasMany
    {
        return $this->hasMany(RekodLawatan::class, 'id_jadual');
    }

    public function terbuka(): bool
    {
        return in_array($this->status_pelaksanaan, self::TERBUKA, true);
    }
}
