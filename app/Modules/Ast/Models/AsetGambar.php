<?php

namespace App\Modules\Ast\Models;

use App\Models\User;
use App\Modules\Ast\Models\Concerns\SkopLot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-AST-12: dated, geotagged photo; the file itself is in DMS (laluan_fail = document id).
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> untuk(\App\Models\User $user)
 *
 * @property int $id
 * @property string $id_aset
 * @property string|null $id_pemeriksaan
 * @property string|null $id_rekod_penyelenggaraan
 * @property string|null $laluan_fail
 * @property Carbon $tarikh_gambar
 * @property string|null $latitud
 * @property string|null $longitud
 * @property string|null $ketepatan_m
 * @property int|null $id_pegawai_rakam
 * @property string $kategori_gambar
 * @property string|null $keterangan
 * @property string|null $nama_fail
 * @property string|null $id_luar_talian
 * @property string $kod_daerah
 * @property string $kod_mukim
 * @property string $klasifikasi
 * @property bool $sintetik
 * @property-read User|null $perakam
 */
class AsetGambar extends AstModel
{
    use SkopLot;

    protected $table = 'aset_gambar';

    protected $casts = ['tarikh_gambar' => 'datetime', 'sintetik' => 'boolean'];

    /** @return BelongsTo<User, $this> */
    public function perakam(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai_rakam');
    }
}
