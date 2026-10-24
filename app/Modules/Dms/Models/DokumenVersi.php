<?php

namespace App\Modules\Dms\Models;

use App\Models\User;
use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-DMS-14: immutable file version. Never overwritten; disposal only clears
 * the storage key and the wrapped data key (crypto-shredding).
 *
 * @property int $id
 * @property string $dokumen_id
 * @property int $no_versi
 * @property string $nama_fail
 * @property string $jenis_mime
 * @property int $saiz_bait
 * @property string $cincangan_fail
 * @property string|null $kunci_storan
 * @property string|null $kunci_data_bersulit
 * @property string|null $id_kunci_induk
 * @property string $algoritma
 * @property int|null $dimuat_naik_oleh
 * @property string|null $catatan
 * @property array<string, mixed> $metadata_snapshot
 * @property Carbon $created_at
 * @property-read User|null $pemuatNaik
 */
class DokumenVersi extends Model implements Auditable
{
    protected $table = 'dokumen_versi';

    protected $guarded = ['id'];

    protected $hidden = ['kunci_storan', 'kunci_data_bersulit', 'id_kunci_induk'];

    protected function casts(): array
    {
        return [
            'metadata_snapshot' => 'array',
            'saiz_bait' => 'integer',
            'no_versi' => 'integer',
        ];
    }

    /** @return BelongsTo<Dokumen, $this> */
    public function dokumen(): BelongsTo
    {
        return $this->belongsTo(Dokumen::class, 'dokumen_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function pemuatNaik(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dimuat_naik_oleh');
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
        return ['kunci_data_bersulit'];
    }
}
