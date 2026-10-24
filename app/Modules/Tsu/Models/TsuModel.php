<?php

namespace App\Modules\Tsu\Models;

use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;

/** Base for MOD-TSU models: audited by MOD-AUD's global observer under module "tsu" (SRS-GEN-02). */
abstract class TsuModel extends Model implements Auditable
{
    protected $guarded = ['id'];

    public function auditModule(): string
    {
        return 'tsu';
    }

    public function auditExcept(): array
    {
        return ['updated_at', 'geometri'];
    }

    public function auditSensitive(): array
    {
        return [];
    }
}
