<?php

namespace App\Modules\Pjk\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * SRS-PJK-14/19/20: one payment (online or offline). Credits the ledger only
 * once confirmed: by the gateway (online) or by a Finance officer other than
 * the recorder (offline).
 *
 * @property int $id
 * @property string $no_bayaran
 * @property string $kontrak_id
 * @property string $saluran
 * @property string $amaun
 * @property Carbon $tarikh_bayaran
 * @property string|null $rujukan_luar
 * @property string|null $id_dokumen_bukti
 * @property list<array{caj_id: int, amaun: string}>|null $agihan_manual
 * @property string $status
 * @property string|null $transaksi_gateway_id
 * @property string|null $nama_pembayar
 * @property string|null $emel_pembayar
 * @property int|null $id_perekod
 * @property int|null $id_pengesah
 * @property Carbon|null $tarikh_pengesahan
 * @property string|null $catatan
 * @property bool $sintetik
 * @property-read Kontrak $kontrak
 */
class Bayaran extends PjkModel
{
    protected $table = 'bayaran';

    protected function casts(): array
    {
        return ['tarikh_bayaran' => 'date:Y-m-d', 'tarikh_pengesahan' => 'datetime', 'agihan_manual' => 'array', 'sintetik' => 'boolean'];
    }

    public function auditSensitive(): array
    {
        return ['emel_pembayar'];
    }

    /** @return BelongsTo<Kontrak, $this> */
    public function kontrak(): BelongsTo
    {
        return $this->belongsTo(Kontrak::class, 'kontrak_id');
    }

    /** @return HasMany<AgihanBayaran, $this> */
    public function agihan(): HasMany
    {
        return $this->hasMany(AgihanBayaran::class, 'bayaran_id');
    }

    /** @return HasMany<Resit, $this> */
    public function resit(): HasMany
    {
        return $this->hasMany(Resit::class, 'bayaran_id');
    }
}
