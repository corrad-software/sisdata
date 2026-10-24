<?php

namespace App\Modules\Ptn\Models;

use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;

/** Base for MOD-PTN models: audited by MOD-AUD's global observer under module "ptn" (SRS-GEN-02). */
abstract class PtnModel extends Model implements Auditable
{
    protected $guarded = ['id'];

    public function auditModule(): string
    {
        return 'ptn';
    }

    public function auditExcept(): array
    {
        return ['updated_at'];
    }

    public function auditSensitive(): array
    {
        return [];
    }
}
