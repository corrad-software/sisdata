<?php

namespace App\Modules\Adu\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Inactive service account that owns uploads/records made by the public
 * portal and the central-system import (DocumentStore needs a User). It can
 * never log in (is_active false, random password).
 */
final class PenggunaSistem
{
    public const EMEL = 'portal-aduan@sistem.sisdata.local';

    public static function portal(): User
    {
        return User::query()->firstOrCreate(['email' => self::EMEL], [
            'name' => 'Portal Aduan Awam (akaun sistem)',
            'password' => Str::random(64),
            'role' => 'user',
            'is_active' => false,
        ]);
    }
}
