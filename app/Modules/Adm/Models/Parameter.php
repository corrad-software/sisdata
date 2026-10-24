<?php

namespace App\Modules\Adm\Models;

use App\Modules\Adm\Models\Concerns\AuditedByAdm;
use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $kunci
 * @property string $modul
 * @property string|null $nilai JSON-encoded
 * @property string|null $justifikasi
 * @property int|null $dikemas_kini_oleh
 * @property Carbon|null $updated_at
 */
class Parameter extends Model implements Auditable
{
    use AuditedByAdm;

    protected $table = 'adm_parameter';

    protected $fillable = ['kunci', 'modul', 'nilai', 'justifikasi', 'dikemas_kini_oleh'];
}
