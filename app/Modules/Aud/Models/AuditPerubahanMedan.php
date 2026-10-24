<?php

namespace App\Modules\Aud\Models;

/**
 * @property int $id_audit
 * @property string $nama_medan
 * @property string|null $nilai_asal
 * @property string|null $nilai_baharu
 * @property bool $sensitif_ditapis
 */
class AuditPerubahanMedan extends ReadOnlyAuditModel
{
    protected $table = 'audit_perubahan_medan';

    protected function casts(): array
    {
        return ['sensitif_ditapis' => 'boolean'];
    }
}
