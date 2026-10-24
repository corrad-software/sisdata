<?php

namespace App\Modules\Tsu\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-TSU-03: one stage of an urusan, generated from the template version.
 *
 * @property int $id
 * @property string $urusan_id
 * @property int $no_urutan
 * @property string $kod
 * @property string $nama_peringkat
 * @property string|null $tindakan_diperlukan
 * @property string $pihak_bertanggungjawab
 * @property int $tempoh_sasaran_hari
 * @property Carbon|null $tarikh_mula
 * @property Carbon|null $tarikh_sasaran
 * @property Carbon|null $tarikh_selesai
 * @property string $status
 * @property list<string> $senarai_dokumen_wajib
 * @property list<string> $senarai_kategori_kos
 * @property bool $perlu_kelulusan
 * @property string|null $kesan
 * @property string|null $id_aliran_kerja
 * @property string|null $catatan
 * @property int $aras_eskalasi
 * @property int|null $id_pegawai_selesai
 * @property-read UrusanTanah $urusan
 */
class UrusanPeringkat extends TsuModel
{
    protected $table = 'urusan_peringkat';

    protected $casts = [
        'tarikh_mula' => 'date', 'tarikh_sasaran' => 'date', 'tarikh_selesai' => 'date',
        'senarai_dokumen_wajib' => 'array', 'senarai_kategori_kos' => 'array', 'perlu_kelulusan' => 'boolean',
    ];

    /** @return BelongsTo<UrusanTanah, $this> */
    public function urusan(): BelongsTo
    {
        return $this->belongsTo(UrusanTanah::class, 'urusan_id')->withoutGlobalScope('access');
    }

    public function lewat(): bool
    {
        return in_array($this->status, ['aktif', 'menunggu_kelulusan'], true) && $this->tarikh_sasaran !== null && $this->tarikh_sasaran->lt(today());
    }
}
