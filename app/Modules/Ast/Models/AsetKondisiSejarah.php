<?php

namespace App\Modules\Ast\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-AST-05: condition change history (date, officer, inspection).
 *
 * @property int $id
 * @property string $id_aset
 * @property string|null $status_lama
 * @property string $status_baharu
 * @property Carbon $tarikh_perubahan
 * @property string|null $id_pemeriksaan
 * @property int|null $id_pengguna
 * @property string|null $catatan
 * @property-read User|null $pengguna
 */
class AsetKondisiSejarah extends AstModel
{
    protected $table = 'aset_kondisi_sejarah';

    public $timestamps = false;

    protected $casts = ['tarikh_perubahan' => 'datetime'];

    /** @return BelongsTo<User, $this> */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengguna');
    }
}
