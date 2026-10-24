<?php

namespace App\Modules\Pjk\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SRS-PJK-09: a generated letter with its reference number.
 *
 * @property int $id
 * @property string $kontrak_id
 * @property int $templat_id
 * @property string $no_rujukan_surat
 * @property string $kandungan
 * @property string|null $id_dokumen
 * @property-read TemplatSurat $templat
 */
class SuratDijana extends PjkModel
{
    public const UPDATED_AT = null;

    protected $table = 'surat_dijana';

    /** @return BelongsTo<TemplatSurat, $this> */
    public function templat(): BelongsTo
    {
        return $this->belongsTo(TemplatSurat::class, 'templat_id');
    }
}
