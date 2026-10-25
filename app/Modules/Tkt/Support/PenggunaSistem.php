<?php

namespace App\Modules\Tkt\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Inactive service account that owns what MOD-TKT stores on its own (the
 * generated monthly report in MOD-DMS, which needs an uploader). It can
 * never log in (is_active false, random password).
 */
final class PenggunaSistem
{
    public const EMEL = 'tiket-sokongan@sistem.sisdata.local';

    public static function akaun(): User
    {
        return User::query()->firstOrCreate(['email' => self::EMEL], [
            'name' => 'Tiket Sokongan (akaun sistem)',
            'password' => Str::random(64),
            'role' => 'user',
            'is_active' => false,
        ]);
    }
}
