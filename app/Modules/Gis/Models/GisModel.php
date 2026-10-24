<?php

namespace App\Modules\Gis\Models;

use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;

/** Base for MOD-GIS models: audited under module "gis" by MOD-AUD's global observer (SRS-GIS-31). */
abstract class GisModel extends Model implements Auditable
{
    protected $guarded = ['id'];

    public function auditModule(): string
    {
        return 'gis';
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
