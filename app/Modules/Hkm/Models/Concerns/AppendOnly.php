<?php

namespace App\Modules\Hkm\Models\Concerns;

use LogicException;

/**
 * Model-level guard mirroring the DB trigger hkm_tolak_ubah(): history rows
 * can be inserted, never updated or deleted.
 */
trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::updating(fn () => throw new LogicException(__('hkm::hkm.ralat.sejarah_hanya_tambah')));
        static::deleting(fn () => throw new LogicException(__('hkm::hkm.ralat.sejarah_hanya_tambah')));
    }
}
