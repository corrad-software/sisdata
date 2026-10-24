<?php

namespace App\Modules\Ptn\Models;

use App\Modules\Ptn\Models\Concerns\UntukPengguna;
use App\Modules\Rbc\Contracts\Concerns\ScopedByAccess;
use Illuminate\Support\Carbon;

/**
 * SRS-PTN-21: maintenance need identified during monitoring; linked to a
 * MOD-AST asset when that module exists (id_aset_fizikal).
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> untuk(\App\Models\User $user)
 *
 * @property int $id
 * @property string|null $id_lawatan
 * @property string|null $id_isu
 * @property string $lot_id
 * @property string $kod_daerah
 * @property string $kod_mukim
 * @property string $klasifikasi
 * @property string $jenis
 * @property string $keterangan
 * @property string $keutamaan
 * @property Carbon|null $tarikh_jadual
 * @property string $status
 * @property string|null $id_aset_fizikal
 * @property string|null $anggaran_kos
 * @property int|null $id_pencipta
 * @property bool $sintetik
 * @property Carbon $created_at
 */
class KeperluanPenyelenggaraan extends PtnModel
{
    use ScopedByAccess, UntukPengguna;

    protected $table = 'keperluan_penyelenggaraan';

    /** @var array<string, string> */
    protected array $accessScopes = ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi'];

    protected $casts = ['tarikh_jadual' => 'date', 'sintetik' => 'boolean'];
}
