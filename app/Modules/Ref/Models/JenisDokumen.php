<?php

namespace App\Modules\Ref\Models;

/**
 * Jenis dokumen (DMS / pemfailan).
 */
class JenisDokumen extends RefModel
{
    protected $table = 'ref_jenis_dokumen';

    public const EXTRA = ['klasifikasi_lalai'];
}
