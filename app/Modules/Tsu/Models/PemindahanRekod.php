<?php

namespace App\Modules\Tsu\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-TSU-20: one record mapped from an original lot to a successor lot,
 * executed only after the two-level confirmation.
 *
 * @property int $id
 * @property string $urusan_id
 * @property string $id_lot_asal
 * @property string $id_lot_baharu
 * @property string $jenis_rekod
 * @property string $id_rekod
 * @property string|null $label_rekod
 * @property string $status_pemindahan
 * @property int|null $id_pencipta
 * @property int|null $id_pengesah_1
 * @property int|null $id_pengesah_2
 * @property Carbon|null $tarikh_pemindahan
 * @property string|null $id_aliran_kerja
 * @property string|null $catatan
 */
class PemindahanRekod extends TsuModel
{
    protected $table = 'pemindahan_rekod';

    protected $casts = ['tarikh_pemindahan' => 'datetime'];
}
