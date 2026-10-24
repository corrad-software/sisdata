<?php

namespace App\Modules\Adu\Models\Concerns;

use LogicException;

/** Model-level mirror of the adu_tolak_ubah() trigger: insert only. */
trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::updating(fn () => throw new LogicException(__('adu::adu.ralat.hanya_tambah')));
        static::deleting(fn () => throw new LogicException(__('adu::adu.ralat.hanya_tambah')));
    }
}
