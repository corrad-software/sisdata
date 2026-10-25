<?php

namespace App\Modules\Lap\Models;

use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;

/** Base of MOD-LAP's own models (definitions, schedules, logs, analytic output). */
abstract class LapModel extends Model implements Auditable
{
    protected $guarded = ['id'];

    public function auditModule(): string
    {
        return 'lap';
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
