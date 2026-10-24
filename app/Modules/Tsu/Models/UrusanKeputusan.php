<?php

namespace App\Modules\Tsu\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-TSU-24: immutable decision history (trigger blocks update/delete).
 *
 * @property int $id
 * @property string $urusan_id
 * @property int|null $peringkat_id
 * @property string $konteks
 * @property string $jenis_keputusan
 * @property string|null $kod_peringkat_wfe
 * @property Carbon $tarikh_keputusan
 * @property string|null $justifikasi
 * @property int|null $id_pelulus
 * @property bool $indikator_mfa
 * @property string|null $id_dokumen_kelulusan
 * @property string|null $id_aliran
 */
class UrusanKeputusan extends TsuModel
{
    protected $table = 'urusan_keputusan';

    public const UPDATED_AT = null;

    protected $casts = ['tarikh_keputusan' => 'datetime', 'indikator_mfa' => 'boolean'];
}
