<?php

namespace App\Modules\Ast\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-AST-06: unique QR reference — never deleted or reassigned (DB trigger); deactivatable individually.
 *
 * @property string $qr_ref
 * @property string $id_aset
 * @property Carbon $tarikh_jana
 * @property string $pautan_awam
 * @property bool $status_aktif
 * @property Carbon|null $tarikh_nyahaktif
 * @property string|null $sebab_nyahaktif
 * @property string|null $templat_label
 * @property int $bilangan_cetakan
 * @property Carbon|null $tarikh_cetakan_terakhir
 * @property-read AsetFizikal|null $aset
 */
class AsetQr extends AstModel
{
    protected $table = 'aset_qr';

    protected $primaryKey = 'qr_ref';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = ['tarikh_jana' => 'datetime', 'tarikh_nyahaktif' => 'datetime', 'tarikh_cetakan_terakhir' => 'datetime', 'status_aktif' => 'boolean'];

    /** @return BelongsTo<AsetFizikal, $this> */
    public function aset(): BelongsTo
    {
        return $this->belongsTo(AsetFizikal::class, 'id_aset');
    }
}
