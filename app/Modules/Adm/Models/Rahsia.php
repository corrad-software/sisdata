<?php

namespace App\Modules\Adm\Models;

use App\Modules\Adm\Models\Concerns\AuditedByAdm;
use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Secret store entry (SRS-ADM-28). `nilai_tersulit` is encrypted with the
 * application key by the `encrypted` cast; it is hidden from serialisation and
 * masked in audit.
 *
 * @property int $id
 * @property string $kunci
 * @property string $nilai_tersulit decrypted value when read through the model
 * @property string|null $keterangan
 * @property int|null $dikemas_kini_oleh
 */
class Rahsia extends Model implements Auditable
{
    use AuditedByAdm;

    /** @var list<string> */
    protected array $auditMasked = ['nilai_tersulit'];

    protected $table = 'adm_rahsia';

    protected $fillable = ['kunci', 'nilai_tersulit', 'keterangan', 'dikemas_kini_oleh'];

    protected $hidden = ['nilai_tersulit'];

    protected function casts(): array
    {
        return ['nilai_tersulit' => 'encrypted'];
    }
}
