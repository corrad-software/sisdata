<?php

namespace App\Modules\Ref\Models;

/**
 * Negeri / wilayah persekutuan. Kod = kod negeri JPN 2 digit (Selangor "10").
 */
class Negeri extends RefModel
{
    protected $table = 'ref_negeri';

    public const EXTRA = ['kod_iso', 'jenis'];
}
