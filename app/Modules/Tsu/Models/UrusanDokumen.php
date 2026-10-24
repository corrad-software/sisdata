<?php

namespace App\Modules\Tsu\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-TSU-25: a document of an urusan stage, stored in MOD-DMS (id_dokumen_dms).
 *
 * @property int $id
 * @property string $urusan_id
 * @property int|null $peringkat_id
 * @property string $id_dokumen_dms
 * @property string $kategori_dokumen
 * @property string|null $nama_fail
 * @property Carbon|null $tarikh_dokumen
 * @property Carbon|null $tarikh_luput
 * @property Carbon $tarikh_muat_naik
 * @property int|null $id_pemuat_naik
 */
class UrusanDokumen extends TsuModel
{
    protected $table = 'urusan_dokumen';

    public $timestamps = false;

    protected $casts = ['tarikh_dokumen' => 'date', 'tarikh_luput' => 'date', 'tarikh_muat_naik' => 'datetime'];

    public function sah(?Carbon $on = null): bool
    {
        return $this->tarikh_luput === null || $this->tarikh_luput->gte($on ?? today());
    }
}
