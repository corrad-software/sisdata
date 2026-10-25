<?php

namespace App\Modules\Tkt\Models;

use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;

/** Base for MOD-TKT models: every change is audited by MOD-AUD under module "tkt" (SRS-TKT-18). */
abstract class TktModel extends Model implements Auditable
{
    protected $guarded = ['id'];

    public function auditModule(): string
    {
        return 'tkt';
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
