<?php

namespace App\Modules\Aud\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $no_urutan
 * @property string $cincangan_rekod
 * @property string $cincangan_sebelum
 * @property string $modul
 * @property string $jenis_tindakan
 * @property string $status_transaksi
 * @property string|null $nama_entiti
 * @property string|null $id_rekod
 * @property int|null $id_pengguna
 * @property array<string, mixed>|null $meta
 * @property list<string> $peranan_semasa
 */
class AuditRekod extends ReadOnlyAuditModel
{
    protected $table = 'audit_rekod';

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'peranan_semasa' => 'array',
            'no_urutan' => 'integer',
        ];
    }

    /** @return HasMany<AuditPerubahanMedan, $this> */
    public function perubahan(): HasMany
    {
        return $this->hasMany(AuditPerubahanMedan::class, 'id_audit')->orderBy('id');
    }
}
