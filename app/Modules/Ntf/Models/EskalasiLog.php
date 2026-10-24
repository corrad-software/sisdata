<?php

namespace App\Modules\Ntf\Models;

use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $status_pengguna_id
 * @property int $mesej_id
 * @property int|null $penerima_asal_id
 * @property int|null $penyelia_id
 * @property int|null $mesej_eskalasi_id
 * @property Carbon $tarikh_eskalasi
 */
class EskalasiLog extends Model implements Auditable
{
    protected $table = 'ntf_eskalasi_log';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tarikh_eskalasi' => 'datetime'];
    }

    public function auditModule(): string
    {
        return 'ntf';
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
