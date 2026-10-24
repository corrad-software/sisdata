<?php

use App\Modules\Adm\AdmServiceProvider;
use App\Modules\Aud\AudServiceProvider;
use App\Modules\Dms\DmsServiceProvider;
use App\Modules\Hkm\HkmServiceProvider;
use App\Modules\Ntf\NtfServiceProvider;
use App\Modules\Per\PerServiceProvider;
use App\Modules\Platform\PlatformServiceProvider;
use App\Modules\Pro\ProServiceProvider;
use App\Modules\Rbc\RbcServiceProvider;
use App\Modules\Ref\RefServiceProvider;
use App\Modules\Wfe\WfeServiceProvider;

/*
| SISDATA modules (app/Modules/*). Order matters only for migrations that
| depend on another module's tables — list platform modules (G0) first.
*/

return [
    'enabled' => [
        PlatformServiceProvider::class,
        RbcServiceProvider::class,
        AudServiceProvider::class,
        RefServiceProvider::class,
        AdmServiceProvider::class,
        NtfServiceProvider::class,
        DmsServiceProvider::class,
        WfeServiceProvider::class,
        // G1 — domain teras
        ProServiceProvider::class,
        HkmServiceProvider::class,
        PerServiceProvider::class,
    ],
];
