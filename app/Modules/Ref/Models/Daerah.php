<?php

namespace App\Modules\Ref\Models;

/**
 * Daerah. Kod = 4 digit negeri+daerah, contoh "1008" Petaling.
 */
class Daerah extends RefModel
{
    protected $table = 'ref_daerah';

    public const EXTRA = ['negeri_kod', 'kod_pendek', 'kod_disahkan', 'nama_lain', 'wikidata', 'osm_relation_id'];

    public const FILTERS = ['negeri' => 'negeri_kod'];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'kod_disahkan' => 'boolean',
            'nama_lain' => 'array',
            'osm_relation_id' => 'integer',
        ];
    }
}
