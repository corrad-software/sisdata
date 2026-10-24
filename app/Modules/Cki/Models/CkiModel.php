<?php

namespace App\Modules\Cki\Models;

use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;

/** Base for MOD-CKI models: every write is recorded by MOD-AUD's global observer under module "cki" (SRS-GEN-02). */
abstract class CkiModel extends Model implements Auditable
{
    protected $guarded = ['id'];

    public function auditModule(): string
    {
        return 'cki';
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
