<?php

namespace App\Modules\Ref\Models;

/**
 * Aras klasifikasi data (SRS §4.2).
 */
class KlasifikasiData extends RefModel
{
    protected $table = 'ref_klasifikasi_data';

    public const EXTRA = ['aras'];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'aras' => 'integer',
        ];
    }
}
