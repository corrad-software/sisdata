<?php

namespace App\Modules\Adm\Models;

use App\Modules\Adm\Models\Concerns\AuditedByAdm;
use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $istilah
 * @property list<string> $sinonim
 */
class SinonimCarian extends Model implements Auditable
{
    use AuditedByAdm;

    protected $table = 'adm_sinonim_carian';

    protected $fillable = ['istilah', 'sinonim', 'dikemas_kini_oleh'];

    protected function casts(): array
    {
        return ['sinonim' => 'array'];
    }
}
