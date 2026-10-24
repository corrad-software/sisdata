<?php

namespace App\Modules\Hkm\Reminders;

/**
 * SRS-HKM-17: Temporary Occupation Licences (TOL) due for renewal.
 */
class TolPerluDiperbaharui extends HakMilikSementaraTamat
{
    public const TYPE = 'hkm.tol_pembaharuan';

    public function type(): string
    {
        return self::TYPE;
    }

    public function defaultDays(): array
    {
        return [90, 30, 7];
    }

    protected function types(): array
    {
        return ['TOL'];
    }
}
