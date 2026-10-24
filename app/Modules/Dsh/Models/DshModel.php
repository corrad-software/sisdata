<?php

namespace App\Modules\Dsh\Models;

use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;

/** Base of MOD-DSH's own models (configuration and per-user state only). */
abstract class DshModel extends Model implements Auditable
{
    protected $guarded = ['id'];

    public function auditModule(): string
    {
        return 'dsh';
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
