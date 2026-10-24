<?php

namespace App\Modules\Cki\Models;

use App\Modules\Cki\Support\Wang;
use App\Modules\Rbc\Contracts\Concerns\ScopedByAccess;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * SRS-CKI-02: a tax bill — MOD-PRO's §4.1 `cukai` skeleton (the module
 * entity `bil_cukai`), extended by MOD-CKI. `jenis` = jenis_cukai, `jumlah`
 * = amaun_pokok + amaun_denda (kept for the skeleton's other readers).
 * Bills are never deleted (DB trigger); corrections go to pelarasan_cukai.
 *
 * @property string $id
 * @property string $lot_id
 * @property string|null $obligasi_id
 * @property string $jenis
 * @property string|null $no_bil
 * @property int|null $tahun_taksiran
 * @property string|null $pihak_pengeluar
 * @property string|null $kod_agensi
 * @property string|null $kod_pbt
 * @property string|null $kod_daerah
 * @property string|null $kod_mukim
 * @property string|null $jumlah
 * @property string $amaun_pokok
 * @property string $amaun_denda
 * @property string $amaun_dibayar
 * @property Carbon|null $tarikh_bil
 * @property Carbon|null $tarikh_akhir
 * @property Carbon|null $tarikh_bayar
 * @property string $status_bayaran
 * @property string|null $kaedah_bayaran
 * @property string|null $no_resit
 * @property string $sumber_rekod
 * @property string|null $id_dokumen_bil
 * @property Carbon|null $disegerak_pada
 * @property bool $denda_dikenakan
 * @property bool $sintetik
 * @property int $versi
 * @property string $klasifikasi
 * @property int|null $id_pencipta
 * @property Carbon $created_at
 * @property-read Obligasi|null $obligasi
 */
class Cukai extends CkiModel
{
    use HasUuids, ScopedByAccess, SoftDeletes;

    public const SUBJECT = 'cukai';

    protected $table = 'cukai';

    protected array $accessScopes = ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi'];

    protected $casts = [
        'tarikh_bil' => 'date', 'tarikh_akhir' => 'date', 'tarikh_bayar' => 'date', 'disegerak_pada' => 'datetime',
        'denda_dikenakan' => 'boolean', 'sintetik' => 'boolean', 'tahun_taksiran' => 'integer', 'versi' => 'integer',
    ];

    /** @return BelongsTo<Obligasi, $this> */
    public function obligasi(): BelongsTo
    {
        return $this->belongsTo(Obligasi::class, 'obligasi_id')->withoutGlobalScope('access');
    }

    /** @return HasMany<Transaksi, $this> */
    public function transaksi(): HasMany
    {
        return $this->hasMany(Transaksi::class, 'id_bil');
    }

    /** @return HasMany<Resit, $this> */
    public function resit(): HasMany
    {
        return $this->hasMany(Resit::class, 'id_bil');
    }

    public function jumlahPerlu(): string
    {
        return Wang::add($this->amaun_pokok, $this->amaun_denda);
    }

    public function baki(): string
    {
        return Wang::max(Wang::sub($this->jumlahPerlu(), $this->amaun_dibayar), '0');
    }

    public function dibayar(): bool
    {
        return $this->status_bayaran === 'dibayar';
    }

    public function tertunggak(?Carbon $today = null): bool
    {
        $today ??= Carbon::today();

        return ! $this->dibayar() && $this->tarikh_akhir !== null && $this->tarikh_akhir->lt($today);
    }

    /** Fields compared during re-synchronisation with the central API (SRS-CKI-09). */
    public const MEDAN_SEGERAK = ['no_bil', 'amaun_pokok', 'amaun_denda', 'tarikh_akhir', 'status_bayaran'];

    /** @return array<string, string|null> */
    public function nilaiSegerak(): array
    {
        return [
            'no_bil' => $this->no_bil,
            'amaun_pokok' => Wang::norm($this->amaun_pokok),
            'amaun_denda' => Wang::norm($this->amaun_denda),
            'tarikh_akhir' => $this->tarikh_akhir?->format('Y-m-d'),
            'status_bayaran' => $this->dibayar() ? 'dibayar' : 'belum_bayar',
        ];
    }
}
