<?php

namespace App\Modules\Adm\Models;

use App\Models\User;
use App\Modules\Adm\Models\Concerns\AuditedByAdm;
use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A pending critical configuration change (SRS-ADM-18). ADM_KONFIG_KELULUSAN.
 *
 * @property int $id
 * @property string $jenis_konfig parameter | integrasi | rahsia
 * @property string $kunci
 * @property mixed $nilai_sebelum
 * @property mixed $nilai_selepas
 * @property string|null $nilai_rahsia_tersulit
 * @property string $justifikasi
 * @property int $id_pemohon
 * @property string|null $alamat_ip_pemohon
 * @property int|null $id_pelulus
 * @property string|null $catatan_pelulus
 * @property string $status menunggu | lulus | tolak | dibatalkan
 * @property string|null $id_instans_aliran
 * @property Carbon|null $diputuskan_pada
 * @property Carbon $created_at
 */
class KonfigKelulusan extends Model implements Auditable
{
    use AuditedByAdm;

    public const MENUNGGU = 'menunggu';

    public const LULUS = 'lulus';

    public const TOLAK = 'tolak';

    public const DIBATALKAN = 'dibatalkan';

    /** @var list<string> */
    protected array $auditMasked = ['nilai_rahsia_tersulit'];

    protected $table = 'adm_konfig_kelulusan';

    protected $fillable = ['jenis_konfig', 'kunci', 'nilai_sebelum', 'nilai_selepas', 'nilai_rahsia_tersulit', 'justifikasi', 'id_pemohon', 'alamat_ip_pemohon', 'id_pelulus', 'catatan_pelulus', 'status', 'id_instans_aliran', 'diputuskan_pada'];

    protected $hidden = ['nilai_rahsia_tersulit'];

    protected function casts(): array
    {
        return [
            'nilai_sebelum' => 'json',
            'nilai_selepas' => 'json',
            'diputuskan_pada' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function pemohon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pemohon');
    }

    /** @return BelongsTo<User, $this> */
    public function pelulus(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pelulus');
    }
}
