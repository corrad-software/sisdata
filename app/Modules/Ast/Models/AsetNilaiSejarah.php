<?php

namespace App\Modules\Ast\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-AST-18: value by year with the valuation report it is based on.
 *
 * @property int $id
 * @property string $id_aset
 * @property int $tahun
 * @property string $nilai
 * @property string|null $nilai_sebelum
 * @property string $kaedah_penilaian
 * @property string|null $id_laporan_penilaian
 * @property string|null $id_dokumen_dms
 * @property Carbon $tarikh_penilaian
 * @property Carbon $tarikh_rekod
 * @property int|null $id_pengguna
 * @property string|null $catatan
 * @property-read User|null $pengguna
 */
class AsetNilaiSejarah extends AstModel
{
    protected $table = 'aset_nilai_sejarah';

    public $timestamps = false;

    protected $casts = ['tarikh_penilaian' => 'date', 'tarikh_rekod' => 'datetime', 'nilai' => 'decimal:2', 'nilai_sebelum' => 'decimal:2'];

    /** @return BelongsTo<User, $this> */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengguna');
    }
}
