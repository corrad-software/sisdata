<?php

namespace App\Modules\Kba\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One indexed chunk (SRS-KBA-06/08). Written in bulk by the indexer through
 * the query builder (derived data, not audited row by row); read here.
 *
 * @property int $id
 * @property int $id_sumber
 * @property int $no_versi_sumber
 * @property int $urutan
 * @property string|null $nombor_seksyen
 * @property string|null $tajuk_seksyen
 * @property string|null $laluan_seksyen
 * @property string $kandungan_teks
 * @property string $klasifikasi
 * @property list<string> $senarai_peranan_dibenarkan
 * @property string|null $kod_daerah
 * @property string|null $id_dokumen_dms
 * @property string|null $versi_sistem_dirujuk
 * @property string|null $modul
 * @property bool $mengandungi_arahan
 * @property string $status
 */
class Segmen extends KbaModel
{
    protected $table = 'kba_segmen';

    protected $hidden = ['embedding', 'carian', 'cincangan'];

    protected function casts(): array
    {
        return ['senarai_peranan_dibenarkan' => 'array', 'mengandungi_arahan' => 'boolean'];
    }

    /** @return BelongsTo<SumberDokumen, $this> */
    public function sumber(): BelongsTo
    {
        return $this->belongsTo(SumberDokumen::class, 'id_sumber');
    }
}
