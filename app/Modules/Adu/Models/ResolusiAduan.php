<?php

namespace App\Modules\Adu\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-ADU-19: recorded resolution awaiting / given supervisor approval.
 *
 * @property int $id
 * @property string $aduan_id
 * @property string|null $no_rujukan_aduan
 * @property string $kategori_resolusi
 * @property string $keterangan_resolusi
 * @property string $justifikasi
 * @property int|null $id_pegawai_penyelesai
 * @property int|null $id_penyelia_pelulus
 * @property string $status
 * @property string|null $ulasan_pelulus
 * @property Carbon|null $tarikh_tutup
 * @property string $status_makluman_pengadu
 * @property string|null $id_instans_aliran
 * @property Carbon $created_at
 * @property-read User|null $penyelesai
 * @property-read User|null $pelulus
 */
class ResolusiAduan extends AduModel
{
    protected $table = 'resolusi_aduan';

    protected function casts(): array
    {
        return ['tarikh_tutup' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function penyelesai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai_penyelesai');
    }

    /** @return BelongsTo<User, $this> */
    public function pelulus(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_penyelia_pelulus');
    }
}
