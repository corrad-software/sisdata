<?php

namespace App\Modules\Adm\Models;

use App\Modules\Adm\Models\Concerns\AuditedByAdm;
use App\Modules\Aud\Contracts\Auditable;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A scheduled job (SRS-ADM-06). ADM_JOB.
 *
 * @property int $id
 * @property string $kod
 * @property string $nama
 * @property string|null $keterangan
 * @property string $kategori
 * @property string $arahan
 * @property array<string, mixed>|null $argumen
 * @property string $jenis_jadual cron | selang
 * @property string|null $ungkapan_cron
 * @property int|null $selang_minit
 * @property bool $aktif
 * @property int $cubaan_semula
 * @property int $tempoh_tamat_masa_saat
 * @property int|null $toleransi_lewat_minit
 * @property string $sumber adm | sistem
 * @property string|null $kunci_sistem
 * @property Carbon|null $terakhir_mula_pada
 * @property string|null $terakhir_status
 * @property Carbon $created_at
 */
class Tugasan extends Model implements Auditable
{
    use AuditedByAdm;

    public const KATEGORI = ['pengemaskinian_data', 'notifikasi', 'laporan', 'pengarkiban', 'integrasi', 'penyelenggaraan'];

    protected $table = 'adm_tugasan_berjadual';

    protected $fillable = ['kod', 'nama', 'keterangan', 'kategori', 'arahan', 'argumen', 'jenis_jadual', 'ungkapan_cron', 'selang_minit', 'aktif', 'cubaan_semula', 'tempoh_tamat_masa_saat', 'toleransi_lewat_minit', 'sumber', 'kunci_sistem', 'terakhir_mula_pada', 'terakhir_status', 'justifikasi', 'dikemas_kini_oleh'];

    protected function casts(): array
    {
        return [
            'argumen' => 'array',
            'aktif' => 'boolean',
            'cubaan_semula' => 'integer',
            'selang_minit' => 'integer',
            'tempoh_tamat_masa_saat' => 'integer',
            'toleransi_lewat_minit' => 'integer',
            'terakhir_mula_pada' => 'datetime',
        ];
    }

    /** @return HasMany<TugasanLarian, $this> */
    public function larian(): HasMany
    {
        return $this->hasMany(TugasanLarian::class, 'id_tugasan');
    }

    /** Cron expression equivalent used for "next/previous due" maths. */
    public function cronExpression(): string
    {
        if ($this->jenis_jadual === 'selang') {
            $minutes = max(1, (int) $this->selang_minit);

            return $minutes < 60 && 60 % $minutes === 0 ? "*/{$minutes} * * * *" : '* * * * *';
        }

        return (string) $this->ungkapan_cron;
    }

    /** Fixed-interval jobs are due once the interval has elapsed since the last start. */
    public function dueByInterval(?Carbon $now = null): bool
    {
        $now ??= now();

        return $this->terakhir_mula_pada === null
            || $this->terakhir_mula_pada->copy()->addMinutes(max(1, (int) $this->selang_minit))->lte($now->copy()->addSeconds(5));
    }

    /** When the job should most recently have started (for missed-schedule detection). */
    public function previousDue(?Carbon $now = null): Carbon
    {
        $now ??= now();

        if ($this->jenis_jadual === 'selang') {
            $base = $this->terakhir_mula_pada ?? $this->created_at;

            return $base->copy()->addMinutes(max(1, (int) $this->selang_minit));
        }

        return Carbon::instance((new CronExpression((string) $this->ungkapan_cron))->getPreviousRunDate($now, 0, true));
    }

    public function nextDue(?Carbon $now = null): ?Carbon
    {
        if (! $this->aktif) {
            return null;
        }

        if ($this->jenis_jadual === 'selang') {
            return ($this->terakhir_mula_pada ?? now())->copy()->addMinutes(max(1, (int) $this->selang_minit));
        }

        return Carbon::instance((new CronExpression((string) $this->ungkapan_cron))->getNextRunDate($now ?? now()));
    }
}
