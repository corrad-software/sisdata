<?php

namespace App\Modules\Wfe\Models;

use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Wfe\Models\Concerns\AuditedByWfe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * SRS-WFE-06: one public holiday in the admin-maintained calendar.
 *
 * @property int $id
 * @property Carbon $tarikh
 * @property string $nama
 * @property string $negeri
 * @property string $jenis persekutuan | negeri | ganti | khas
 * @property string|null $sumber
 * @property string|null $catatan
 */
class CutiUmum extends Model implements Auditable
{
    use AuditedByWfe;

    protected $table = 'wf_cuti_umum';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tarikh' => 'date'];
    }
}
