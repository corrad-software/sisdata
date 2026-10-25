<?php

namespace App\Modules\Pws\Models;

use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;

/** Base of MOD-PWS models; data changes are audited by MOD-AUD's global observer. */
abstract class PwsModel extends Model implements Auditable
{
    protected $guarded = ['id'];

    public function auditModule(): string
    {
        return 'pws';
    }

    public function auditExcept(): array
    {
        return ['created_at', 'updated_at', 'kandungan', 'terakhir_digunakan'];
    }

    public function auditSensitive(): array
    {
        return ['cincangan_rahsia', 'cincangan_rahsia_lama', 'cincangan_emel'];
    }
}
