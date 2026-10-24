<?php

namespace App\Modules\Adm\Models;

use App\Modules\Adm\Models\Concerns\AuditedByAdm;
use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $kod
 * @property string $nama
 * @property string $jenis
 * @property string $format_output
 * @property string $kandungan
 * @property list<string>|null $medan
 * @property int $versi
 * @property string $status
 * @property string|null $justifikasi
 * @property int|null $dikemas_kini_oleh
 */
class Templat extends Model implements Auditable
{
    use AuditedByAdm;

    public const FORMATS = ['pdf', 'xlsx', 'csv', 'html', 'docx'];

    protected $table = 'adm_templat';

    protected $fillable = ['kod', 'nama', 'jenis', 'format_output', 'kandungan', 'medan', 'versi', 'status', 'justifikasi', 'dikemas_kini_oleh'];

    protected function casts(): array
    {
        return ['medan' => 'array', 'versi' => 'integer'];
    }

    /** @return HasMany<TemplatVersi, $this> */
    public function versiList(): HasMany
    {
        return $this->hasMany(TemplatVersi::class, 'id_templat');
    }
}
