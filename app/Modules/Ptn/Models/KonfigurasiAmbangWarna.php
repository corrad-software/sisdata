<?php

namespace App\Modules\Ptn\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-PTN-15: colour thresholds (and risk weights) per category,
 * effective-dated — a change inserts a new row, so history is kept and
 * audited (AUD observer).
 *
 * @property int $id
 * @property string $kategori_risiko
 * @property string $ambang_hijau
 * @property string|null $ambang_kuning
 * @property string|null $ambang_merah
 * @property int|null $tempoh_kelewatan_kuning
 * @property int|null $tempoh_kelewatan_merah
 * @property string|null $pemberat
 * @property Carbon $tarikh_berkuat_kuasa
 * @property int|null $id_pengubah
 */
class KonfigurasiAmbangWarna extends PtnModel
{
    protected $table = 'konfigurasi_ambang_warna';

    protected $casts = ['tarikh_berkuat_kuasa' => 'datetime', 'tempoh_kelewatan_kuning' => 'integer', 'tempoh_kelewatan_merah' => 'integer'];
}
