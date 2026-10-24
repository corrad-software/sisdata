<?php

namespace App\Modules\Pjk\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-PJK-15: an online payment transaction with a unique reference linked
 * to the contract and charges; status menunggu → berjaya | gagal |
 * dibatalkan | tamat_masa. Card data is never stored (collected on the
 * gateway's page).
 *
 * @property string $id
 * @property string $no_rujukan
 * @property string $kontrak_id
 * @property list<int>|null $caj_ids
 * @property string $saluran
 * @property string $amaun
 * @property string $status
 * @property Carbon $masa_mula
 * @property Carbon|null $masa_pengesahan
 * @property string|null $kod_respons
 * @property string|null $rujukan_gateway
 * @property int|null $id_fail_penyelesaian
 * @property string|null $url_bayaran
 * @property string|null $kunci_idempotensi
 * @property int $bil_semakan_status
 * @property string|null $disahkan_melalui
 * @property string|null $nama_pembayar
 * @property string|null $emel_pembayar
 * @property string $sumber
 * @property int|null $id_pemula
 * @property-read Kontrak $kontrak
 */
class TransaksiGateway extends PjkModel
{
    use HasUuids;

    public const FINAL = ['berjaya', 'gagal', 'dibatalkan', 'tamat_masa'];

    protected $table = 'transaksi_gateway';

    protected function casts(): array
    {
        return ['caj_ids' => 'array', 'masa_mula' => 'datetime', 'masa_pengesahan' => 'datetime', 'semakan_terakhir' => 'datetime', 'bil_semakan_status' => 'integer'];
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

    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL, true);
    }
}
