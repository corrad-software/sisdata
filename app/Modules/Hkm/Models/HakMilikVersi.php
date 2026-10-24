<?php

namespace App\Modules\Hkm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-HKM-19 / SRS-DAT-04: one applied version of a title, with before/after.
 * Append-only (DB trigger).
 *
 * @property int $id
 * @property string $hak_milik_id
 * @property int $versi
 * @property string $jenis_perubahan
 * @property array<string, mixed>|null $data_sebelum
 * @property array<string, mixed> $data_selepas
 * @property list<string> $medan_berubah
 * @property string|null $justifikasi
 * @property string|null $id_dokumen_rujukan
 * @property string|null $no_rujukan_dokumen
 * @property string|null $id_perubahan
 * @property int|null $id_pengguna
 * @property int|null $id_pelulus
 * @property Carbon $created_at
 * @property-read User|null $pengguna
 * @property-read User|null $pelulus
 */
class HakMilikVersi extends Model
{
    use Concerns\AppendOnly;

    public const UPDATED_AT = null;

    protected $table = 'hak_milik_versi';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data_sebelum' => 'array', 'data_selepas' => 'array', 'medan_berubah' => 'array', 'versi' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengguna');
    }

    /** @return BelongsTo<User, $this> */
    public function pelulus(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pelulus');
    }
}
