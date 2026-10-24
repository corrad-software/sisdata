<?php

namespace App\Modules\Pjk\Models;

/** SRS-PJK-17: receipt delivery attempts (kept apart from the immutable receipt). */
class PenghantaranResit extends PjkModel
{
    public const UPDATED_AT = null;

    protected $table = 'penghantaran_resit';
}
