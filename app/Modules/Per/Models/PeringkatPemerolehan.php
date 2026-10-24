<?php

namespace App\Modules\Per\Models;

use App\Models\User;
use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Aud\Contracts\HasClassification;
use App\Modules\Per\Models\Concerns\AuditedByPer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-PER-17/18: one stage of one case (snapshotted from peringkat_saluran).
 *
 * @property int $id
 * @property string $id_pemerolehan
 * @property string $kod_peringkat
 * @property string $nama_peringkat
 * @property string $jenis
 * @property int $susunan
 * @property int|null $id_pegawai_bertanggungjawab
 * @property Carbon|null $tarikh_masuk
 * @property Carbon|null $tarikh_keluar
 * @property int|null $tempoh_hari
 * @property int $tempoh_sasaran_hari
 * @property string $status_peringkat
 * @property string $status_eskalasi
 * @property Carbon|null $tarikh_eskalasi
 * @property string|null $catatan
 * @property-read User|null $pegawai
 * @property-read Pemerolehan $pemerolehan
 */
class PeringkatPemerolehan extends Model implements Auditable, HasClassification
{
    use AuditedByPer;

    public const BELUM = 'belum';

    public const SEMASA = 'semasa';

    public const SELESAI = 'selesai';

    protected $table = 'peringkat_pemerolehan';

    protected $guarded = ['id'];

    protected $casts = [
        'tarikh_masuk' => 'datetime', 'tarikh_keluar' => 'datetime', 'tarikh_eskalasi' => 'datetime',
        'susunan' => 'integer', 'tempoh_hari' => 'integer', 'tempoh_sasaran_hari' => 'integer',
    ];

    /** @return BelongsTo<User, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai_bertanggungjawab');
    }

    /** @return BelongsTo<Pemerolehan, $this> */
    public function pemerolehan(): BelongsTo
    {
        return $this->belongsTo(Pemerolehan::class, 'id_pemerolehan');
    }

    /** Days spent so far (open stage) or in total (closed stage). */
    public function hariBerjalan(): ?int
    {
        if ($this->tarikh_masuk === null) {
            return null;
        }

        return (int) $this->tarikh_masuk->copy()->startOfDay()->diffInDays(($this->tarikh_keluar ?? now())->copy()->startOfDay());
    }

    public function lewat(): bool
    {
        $days = $this->hariBerjalan();

        return $days !== null && $days > $this->tempoh_sasaran_hari;
    }
}
