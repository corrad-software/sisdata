<?php

namespace App\Modules\Adm\Models;

use App\Modules\Adm\Models\Concerns\AuditedByAdm;
use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Per-entity search settings (SRS-ADM-20). ADM_INDEKS_CARIAN.
 *
 * @property int $id
 * @property string $entiti
 * @property string|null $label
 * @property list<string>|null $medan
 * @property float $pemberat
 * @property bool $aktif
 * @property string|null $status_indeks
 * @property Carbon|null $diindeks_semula_pada
 */
class IndeksCarian extends Model implements Auditable
{
    use AuditedByAdm;

    protected $table = 'adm_indeks_carian';

    protected $fillable = ['entiti', 'label', 'medan', 'pemberat', 'aktif', 'status_indeks', 'diindeks_semula_pada', 'justifikasi', 'dikemas_kini_oleh'];

    protected function casts(): array
    {
        return [
            'medan' => 'array',
            'pemberat' => 'float',
            'aktif' => 'boolean',
            'diindeks_semula_pada' => 'datetime',
        ];
    }
}
