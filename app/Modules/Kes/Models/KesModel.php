<?php

namespace App\Modules\Kes\Models;

use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Aud\Contracts\HasClassification;
use Illuminate\Database\Eloquent\Model;

/**
 * Base for MOD-KES models: audited by MOD-AUD under module "kes"; every case
 * record is Rahsia (SRS-KES-14, SRS §4.2) so each audit entry carries it.
 */
abstract class KesModel extends Model implements Auditable, HasClassification
{
    protected $guarded = ['id'];

    public function auditModule(): string
    {
        return 'kes';
    }

    public function auditExcept(): array
    {
        return ['updated_at'];
    }

    public function auditSensitive(): array
    {
        return [];
    }

    public function auditClassification(): string
    {
        return 'rahsia';
    }
}
