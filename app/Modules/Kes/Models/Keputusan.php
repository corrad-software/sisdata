<?php

namespace App\Modules\Kes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * SRS-KES-16: court decision and its implications.
 *
 * @property int $id
 * @property string $kes_mahkamah_id
 * @property string $jenis_keputusan
 * @property Carbon $tarikh_keputusan
 * @property string|null $peringkat_mahkamah
 * @property string|null $ringkasan_perintah
 * @property string|null $implikasi
 * @property string|null $id_dokumen_penghakiman
 * @property int|null $id_pengguna
 * @property bool $sintetik
 */
class Keputusan extends KesModel
{
    protected $table = 'kes_keputusan';

    public const JENIS = ['menang', 'kalah', 'selesai', 'penyelesaian_luar_mahkamah', 'ditolak'];

    protected function casts(): array
    {
        return ['tarikh_keputusan' => 'date:Y-m-d', 'sintetik' => 'boolean'];
    }

    /** @return BelongsTo<KesMahkamah, $this> */
    public function kes(): BelongsTo
    {
        return $this->belongsTo(KesMahkamah::class, 'kes_mahkamah_id')->withoutGlobalScope('access');
    }

    /** @return HasMany<TindakanSusulan, $this> */
    public function tindakan(): HasMany
    {
        return $this->hasMany(TindakanSusulan::class, 'kes_keputusan_id');
    }
}
