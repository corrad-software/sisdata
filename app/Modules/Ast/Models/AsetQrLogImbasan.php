<?php

namespace App\Modules\Ast\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-AST-10: one QR scan (timestamp, scanner identity if registered, device GPS if allowed).
 *
 * @property int $id
 * @property string $qr_ref
 * @property string|null $id_aset
 * @property Carbon $cap_masa
 * @property int|null $id_pengguna
 * @property string|null $peranan_pengimbas
 * @property string|null $latitud_peranti
 * @property string|null $longitud_peranti
 * @property string|null $ketepatan_m
 * @property string $jenis_paparan
 * @property string|null $ejen_pengguna
 * @property string|null $kod_daerah
 * @property-read User|null $pengguna
 */
class AsetQrLogImbasan extends AstModel
{
    protected $table = 'aset_qr_log_imbasan';

    public $timestamps = false;

    protected $casts = ['cap_masa' => 'datetime'];

    /** @return BelongsTo<User, $this> */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengguna');
    }
}
