<?php

namespace App\Modules\Pjk\Models;

use App\Modules\Pjk\Support\Wang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-PJK-12: one scheduled charge. `umur_tunggakan` is derived (days past
 * tarikh_akhir while unpaid), not stored, so it is never stale.
 *
 * @property int $id
 * @property string $kontrak_id
 * @property string $no_caj
 * @property string $kod_caj
 * @property string $tempoh
 * @property Carbon $tarikh_caj
 * @property Carbon $tarikh_akhir
 * @property string $amaun
 * @property string $amaun_dibayar
 * @property string $amaun_dikecualikan
 * @property string $status_caj
 * @property bool $dikeluarkan
 * @property string|null $keterangan
 * @property-read Kontrak $kontrak
 */
class JadualCaj extends PjkModel
{
    protected $table = 'jadual_caj';

    protected function casts(): array
    {
        return ['tarikh_caj' => 'date:Y-m-d', 'tarikh_akhir' => 'date:Y-m-d', 'dikeluarkan' => 'boolean'];
    }

    /** @return BelongsTo<Kontrak, $this> */
    public function kontrak(): BelongsTo
    {
        return $this->belongsTo(Kontrak::class, 'kontrak_id');
    }

    public function bakiSen(): int
    {
        return Wang::sen($this->amaun) - Wang::sen($this->amaun_dibayar) - Wang::sen($this->amaun_dikecualikan);
    }

    public function umurTunggakan(?Carbon $today = null): int
    {
        $today ??= Carbon::today();
        if (! $this->dikeluarkan || $this->bakiSen() <= 0 || ! $this->tarikh_akhir->lt($today)) {
            return 0;
        }

        return (int) $this->tarikh_akhir->diffInDays($today);
    }

    /** Recompute status from the amounts (keeps dijadualkan until posted). */
    public function refreshStatus(): void
    {
        if (in_array($this->status_caj, ['dibatalkan'], true)) {
            return;
        }
        $baki = $this->bakiSen();
        $paid = Wang::sen($this->amaun_dibayar);
        $this->status_caj = match (true) {
            ! $this->dikeluarkan && $paid === 0 => 'dijadualkan',
            $baki <= 0 && $paid === 0 && Wang::sen($this->amaun_dikecualikan) > 0 => 'dikecualikan',
            $baki <= 0 => 'dibayar',
            $paid > 0 => 'separa',
            default => 'belum_bayar',
        };
    }
}
