<?php

namespace App\Modules\Rbc\Contracts;

/**
 * SRS-RBC-15 account statuses (users.status_akaun). Only AKTIF may log in.
 * Other modules may read the column (e.g. to skip suspended recipients) but
 * must change it only through MOD-RBC.
 */
final class AccountStatus
{
    public const AKTIF = 'aktif';

    public const GANTUNG = 'gantung';

    public const NYAHAKTIF = 'nyahaktif';

    public const TAMAT_TEMPOH = 'tamat_tempoh';

    public const ALL = [self::AKTIF, self::GANTUNG, self::NYAHAKTIF, self::TAMAT_TEMPOH];
}
