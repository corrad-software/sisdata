<?php

namespace App\Modules\Dms\Models;

use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-DMS-19: explicit per-user or per-role grant on one document.
 *
 * @property int $id
 * @property string $dokumen_id
 * @property string $jenis_penerima pengguna | peranan
 * @property string $penerima
 * @property int|null $diberi_oleh
 * @property Carbon|null $tamat_pada
 * @property Carbon $created_at
 */
class DokumenKebenaran extends Model implements Auditable
{
    protected $table = 'dokumen_kebenaran';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tamat_pada' => 'datetime'];
    }

    /** @return BelongsTo<Dokumen, $this> */
    public function dokumen(): BelongsTo
    {
        return $this->belongsTo(Dokumen::class, 'dokumen_id');
    }

    public function auditModule(): string
    {
        return 'dms';
    }

    public function auditExcept(): array
    {
        return ['updated_at'];
    }

    public function auditSensitive(): array
    {
        return [];
    }
}
