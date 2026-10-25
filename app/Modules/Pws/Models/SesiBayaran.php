<?php

namespace App\Modules\Pws\Models;

use Illuminate\Support\Carbon;

/**
 * A portal-initiated payment (entity pws_sesi_bayaran). Mirrors the MOD-PJK
 * gateway transaction by reference; the ledger record lives in PJK. Only
 * references are kept — never card or banking data (SRS-PWS-16).
 *
 * @property int $id
 * @property string $no_rujukan
 * @property string $no_kontrak
 * @property int|null $klien_id
 * @property string $jumlah
 * @property string $mata_wang
 * @property string $saluran_bayaran
 * @property string|null $rujukan_gateway
 * @property string|null $url_bayaran
 * @property string $status
 * @property Carbon|null $cap_masa_mula
 * @property Carbon|null $cap_masa_pengesahan
 * @property string|null $disahkan_melalui
 * @property Carbon $created_at
 */
class SesiBayaran extends PwsModel
{
    protected $table = 'pws_sesi_bayaran';

    public const AKHIR = ['berjaya', 'gagal', 'dibatalkan', 'tamat_tempoh'];

    protected function casts(): array
    {
        return ['cap_masa_mula' => 'datetime', 'cap_masa_pengesahan' => 'datetime', 'jumlah' => 'decimal:2'];
    }
}
