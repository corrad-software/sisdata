<?php

namespace App\Modules\Ref\Models;

/**
 * Jenis hak milik / lesen (Geran, PN, GM, PM, HSD, HSM, TOL, Rizab).
 */
class JenisHakMilik extends RefModel
{
    protected $table = 'ref_jenis_hak_milik';

    public const EXTRA = ['singkatan', 'peringkat', 'sementara', 'hak_milik', 'rujukan_undang'];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'sementara' => 'boolean',
            'hak_milik' => 'boolean',
        ];
    }
}
