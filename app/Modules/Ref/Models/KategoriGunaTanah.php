<?php

namespace App\Modules\Ref\Models;

/**
 * Kategori kegunaan tanah (KTN s.52).
 */
class KategoriGunaTanah extends RefModel
{
    protected $table = 'ref_kategori_guna_tanah';

    public const EXTRA = ['rujukan_undang'];
}
