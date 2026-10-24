<?php

namespace App\Modules\Cki\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-CKI-14 `kelulusan_bayaran`: the approval record of a payment. The
 * decision itself is made in MOD-WFE (SRS-GEN-03); this keeps the required
 * level (from the configurable value thresholds) and the outcome.
 *
 * @property int $id
 * @property string|null $id_transaksi
 * @property string $id_bil
 * @property string $amaun
 * @property string $peringkat_pelulus
 * @property int $tahap
 * @property string|null $id_aliran
 * @property int|null $id_pegawai_pemohon
 * @property int|null $id_pegawai_pelulus
 * @property Carbon|null $tarikh_kelulusan
 * @property string|null $justifikasi
 * @property string $status
 */
class Kelulusan extends CkiModel
{
    protected $table = 'kelulusan_bayaran';

    protected $casts = ['tarikh_kelulusan' => 'datetime', 'tahap' => 'integer'];
}
