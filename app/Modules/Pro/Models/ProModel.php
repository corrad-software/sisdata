<?php

namespace App\Modules\Pro\Models;

use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;

/** Base for MOD-PRO models: audited under module "pro" by MOD-AUD's global observer. */
abstract class ProModel extends Model implements Auditable
{
    protected $guarded = ['id'];

    public function auditModule(): string
    {
        return 'pro';
    }

    public function auditExcept(): array
    {
        return ['created_at', 'updated_at'];
    }

    public function auditSensitive(): array
    {
        return [];
    }
}
