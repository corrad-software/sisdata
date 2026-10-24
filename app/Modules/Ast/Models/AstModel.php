<?php

namespace App\Modules\Ast\Models;

use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;

/** Base for MOD-AST models: audited by MOD-AUD's global observer under module "ast" (SRS-GEN-02, SRS-AST-23). */
abstract class AstModel extends Model implements Auditable
{
    protected $guarded = ['id'];

    public function auditModule(): string
    {
        return 'ast';
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
