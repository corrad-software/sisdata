<?php

namespace App\Modules\Kba\Models;

use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;

/** Base of MOD-KBA's models; data changes are audited by MOD-AUD's global observer. */
abstract class KbaModel extends Model implements Auditable
{
    protected $guarded = ['id'];

    public function auditModule(): string
    {
        return 'kba';
    }

    public function auditExcept(): array
    {
        return ['created_at', 'updated_at', 'teks_sumber', 'kandungan'];
    }

    public function auditSensitive(): array
    {
        return [];
    }
}
