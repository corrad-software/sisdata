<?php

namespace App\Modules\Ref\Models;

/**
 * Mukim. Kod = 6 digit negeri+daerah+mukim, contoh "100803" Petaling, Daerah Petaling.
 */
class Mukim extends RefModel
{
    protected $table = 'ref_mukim';

    public const EXTRA = ['daerah_kod', 'kod_pendek', 'kod_disahkan', 'nama_lain'];

    public const FILTERS = ['daerah' => 'daerah_kod'];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'kod_disahkan' => 'boolean',
            'nama_lain' => 'array',
        ];
    }
}
