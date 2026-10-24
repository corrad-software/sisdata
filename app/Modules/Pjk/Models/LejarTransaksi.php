<?php

namespace App\Modules\Pjk\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-PJK-11: ledger entry (append-only at DB level). Positive running
 * balance = amount the tenant owes; corrections are new rows.
 *
 * @property int $id
 * @property string $kontrak_id
 * @property string $no_transaksi
 * @property Carbon $tarikh_transaksi
 * @property string $jenis
 * @property string|null $kod_caj
 * @property int|null $caj_id
 * @property string $debit
 * @property string $kredit
 * @property string $baki_berjalan
 * @property string|null $kaedah_bayaran
 * @property string|null $no_resit
 * @property int|null $bayaran_id
 * @property string|null $transaksi_gateway_id
 * @property int|null $id_transaksi_asal
 * @property int|null $pelarasan_id
 * @property string $status_pelarasan
 * @property string|null $keterangan
 * @property int|null $id_pegawai
 * @property Carbon $created_at
 */
class LejarTransaksi extends PjkModel
{
    public const UPDATED_AT = null;

    protected $table = 'lejar_transaksi';

    protected function casts(): array
    {
        return ['tarikh_transaksi' => 'date:Y-m-d'];
    }
}
