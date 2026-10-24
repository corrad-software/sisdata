<?php

namespace App\Modules\Ntf\Models;

use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $pengguna_id
 * @property int $jenis_id
 * @property bool $aktif
 * @property list<string>|null $saluran
 * @property bool $ringkasan
 */
class KeutamaanPengguna extends Model implements Auditable
{
    protected $table = 'ntf_keutamaan_pengguna';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'ringkasan' => 'boolean', 'saluran' => 'array'];
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
