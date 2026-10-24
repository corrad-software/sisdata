<?php

namespace App\Modules\Hkm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-HKM-16: one stage step of a permanent-title application.
 *
 * @property int $id
 * @property string $permohonan_id
 * @property string $peringkat
 * @property string $status
 * @property Carbon $tarikh
 * @property string|null $catatan
 * @property int|null $id_pegawai
 * @property Carbon $created_at
 * @property-read User|null $pegawai
 */
class PermohonanPeringkat extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'permohonan_hak_milik_kekal_peringkat';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tarikh' => 'date:Y-m-d'];
    }

    /** @return BelongsTo<User, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai');
    }
}
