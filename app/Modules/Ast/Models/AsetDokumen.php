<?php

namespace App\Modules\Ast\Models;

use App\Modules\Ast\Models\Concerns\SkopLot;
use Illuminate\Support\Carbon;

/**
 * SRS-AST-22: supporting document link (file in DMS) with category and expiry.
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> untuk(\App\Models\User $user)
 *
 * @property int $id
 * @property string $id_aset
 * @property string $id_dokumen_dms
 * @property string $kategori_dokumen
 * @property string|null $tajuk
 * @property Carbon|null $tarikh_luput
 * @property Carbon $tarikh_muat_naik
 * @property int|null $id_pengguna
 * @property string $kod_daerah
 * @property string $kod_mukim
 * @property string $klasifikasi
 */
class AsetDokumen extends AstModel
{
    use SkopLot;

    protected $table = 'aset_dokumen';

    protected $casts = ['tarikh_luput' => 'date', 'tarikh_muat_naik' => 'datetime'];
}
