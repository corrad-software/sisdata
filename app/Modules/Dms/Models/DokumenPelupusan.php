<?php

namespace App\Modules\Dms\Models;

use App\Models\User;
use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-DMS-23: a disposal request, approved only through the WFE flow "dms.pelupusan".
 *
 * @property int $id
 * @property string $dokumen_id
 * @property string $justifikasi
 * @property int|null $dimohon_oleh
 * @property string|null $id_aliran_kerja
 * @property string $status menunggu | lulus | tolak | ditarik_balik | dibatalkan
 * @property int|null $diputuskan_oleh
 * @property string|null $catatan_keputusan
 * @property Carbon|null $tarikh_keputusan
 * @property Carbon $created_at
 * @property-read Dokumen $dokumen
 */
class DokumenPelupusan extends Model implements Auditable
{
    protected $table = 'dokumen_pelupusan';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tarikh_keputusan' => 'datetime'];
    }

    /** @return BelongsTo<Dokumen, $this> */
    public function dokumen(): BelongsTo
    {
        return $this->belongsTo(Dokumen::class, 'dokumen_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function pemohon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dimohon_oleh');
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
