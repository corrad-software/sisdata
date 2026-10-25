<?php

namespace App\Modules\Tkt\Models\Concerns;

use LogicException;

/** Model-level mirror of the tkt_tolak_ubah() trigger: insert only. */
trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::updating(fn () => throw new LogicException(__('tkt::tkt.ralat.hanya_tambah')));
        static::deleting(fn () => throw new LogicException(__('tkt::tkt.ralat.hanya_tambah')));
    }
}
