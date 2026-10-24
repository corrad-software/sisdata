<?php

namespace App\Modules\Ref\Models;

/**
 * Pihak Berkuasa Tempatan. Kod = singkatan huruf besar, contoh "MBSA".
 */
class Pbt extends RefModel
{
    protected $table = 'ref_pbt';

    public const EXTRA = ['singkatan', 'jenis', 'negeri_kod', 'daerah_kod', 'wikidata', 'osm_relation_id'];

    public const FILTERS = ['negeri' => 'negeri_kod', 'daerah' => 'daerah_kod'];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'daerah_kod' => 'array',
            'osm_relation_id' => 'integer',
        ];
    }
}
