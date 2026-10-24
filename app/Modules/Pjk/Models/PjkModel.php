<?php

namespace App\Modules\Pjk\Models;

use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Base for MOD-PJK models: every write is recorded by MOD-AUD's global
 * observer under module "pjk" (SRS-PJK-28, SRS-GEN-02).
 */
abstract class PjkModel extends Model implements Auditable
{
    protected $guarded = ['id'];

    public function auditModule(): string
    {
        return 'pjk';
    }

    public function auditExcept(): array
    {
        return ['updated_at', 'created_at'];
    }

    public function auditSensitive(): array
    {
        return [];
    }
}
