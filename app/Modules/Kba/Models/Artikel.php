<?php

namespace App\Modules\Kba\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Knowledge-base article (SRS-KBA-11..14): versioned, with owner, review
 * dates and a draf → dalam_semakan → diterbitkan → lapuk lifecycle. Only
 * published articles are indexed for users.
 *
 * @property int $id
 * @property string $tajuk
 * @property string|null $kategori
 * @property string|null $modul
 * @property string $kandungan
 * @property string $status
 * @property int $versi
 * @property int|null $versi_terbit
 * @property string $klasifikasi
 * @property list<string> $senarai_peranan_dibenarkan
 * @property int|null $id_pemilik
 * @property int|null $id_penyemak
 * @property Carbon|null $tarikh_terbit
 * @property Carbon|null $tarikh_semakan_akhir
 * @property Carbon|null $tarikh_semakan_seterusnya
 * @property string $sumber_asal
 * @property string|null $id_tiket_asal
 * @property string|null $versi_sistem_dirujuk
 * @property bool $perlu_semakan
 * @property string|null $sebab_perlu_semakan
 * @property string|null $id_instans_aliran
 * @property string|null $catatan_semakan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Artikel extends KbaModel
{
    protected $table = 'kba_artikel';

    public const STATUS = ['draf', 'dalam_semakan', 'diterbitkan', 'lapuk'];

    protected $attributes = ['senarai_peranan_dibenarkan' => '[]', 'perlu_semakan' => false, 'status' => 'draf', 'versi' => 1, 'klasifikasi' => 'dalaman', 'sumber_asal' => 'manual'];

    protected function casts(): array
    {
        return [
            'senarai_peranan_dibenarkan' => 'array',
            'tarikh_terbit' => 'datetime',
            'tarikh_semakan_akhir' => 'date',
            'tarikh_semakan_seterusnya' => 'date',
            'perlu_semakan' => 'boolean',
            'versi' => 'integer',
            'versi_terbit' => 'integer',
        ];
    }

    /** @return HasMany<VersiArtikel, $this> */
    public function sejarahVersi(): HasMany
    {
        return $this->hasMany(VersiArtikel::class, 'id_artikel')->orderByDesc('nombor_versi');
    }
}
