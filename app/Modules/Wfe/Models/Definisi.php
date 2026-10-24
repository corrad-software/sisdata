<?php

namespace App\Modules\Wfe\Models;

use App\Models\User;
use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Wfe\Models\Concerns\AuditedByWfe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One version of a flow definition (WF_DEFINISI).
 *
 * @property int $id
 * @property string $kod_aliran
 * @property string $nama
 * @property string|null $keterangan
 * @property string $modul
 * @property string $jenis_transaksi
 * @property int $no_versi
 * @property string $status
 * @property bool $templat
 * @property string $kaedah_cipta
 * @property int|null $id_pencipta
 * @property int|null $id_asal
 * @property int|null $diminta_aktif_oleh
 * @property int|null $diluluskan_oleh
 * @property array<string, mixed>|null $susun_atur
 * @property Carbon|null $tarikh_berkuat_kuasa
 * @property Carbon|null $diminta_aktif_pada
 * @property Carbon|null $diluluskan_pada
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Definisi extends Model implements Auditable
{
    use AuditedByWfe;

    public const DRAF = 'draf';

    public const MENUNGGU = 'menunggu_pengaktifan';

    public const AKTIF = 'aktif';

    public const ARKIB = 'arkib';

    protected $table = 'wf_definisi';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'templat' => 'boolean',
            'susun_atur' => 'array',
            'tarikh_berkuat_kuasa' => 'datetime',
            'diminta_aktif_pada' => 'datetime',
            'diluluskan_pada' => 'datetime',
        ];
    }

    /** @return HasMany<Peringkat, $this> */
    public function peringkat(): HasMany
    {
        return $this->hasMany(Peringkat::class, 'id_definisi')->orderBy('no_urutan');
    }

    /** @return HasMany<Peralihan, $this> */
    public function peralihan(): HasMany
    {
        return $this->hasMany(Peralihan::class, 'id_definisi')->orderBy('keutamaan')->orderBy('id');
    }

    /** @return HasMany<Susulan, $this> */
    public function susulan(): HasMany
    {
        return $this->hasMany(Susulan::class, 'id_definisi');
    }

    /** @return HasMany<DefinisiLog, $this> */
    public function log(): HasMany
    {
        return $this->hasMany(DefinisiLog::class, 'id_definisi')->orderByDesc('id');
    }

    /** @return BelongsTo<User, $this> */
    public function pencipta(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pencipta');
    }

    /** @param Builder<Definisi> $query */
    public function scopeAktif(Builder $query): void
    {
        $query->where('status', self::AKTIF);
    }
}
