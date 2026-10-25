<?php

use App\Modules\Adm\AdmServiceProvider;
use App\Modules\Adu\AduServiceProvider;
use App\Modules\Ast\AstServiceProvider;
use App\Modules\Aud\AudServiceProvider;
use App\Modules\Cki\CkiServiceProvider;
use App\Modules\Dms\DmsServiceProvider;
use App\Modules\Dsh\DshServiceProvider;
use App\Modules\Gis\GisServiceProvider;
use App\Modules\Hkm\HkmServiceProvider;
use App\Modules\Kba\KbaServiceProvider;
use App\Modules\Kes\KesServiceProvider;
use App\Modules\Lap\LapServiceProvider;
use App\Modules\Ntf\NtfServiceProvider;
use App\Modules\Per\PerServiceProvider;
use App\Modules\Pjk\PjkServiceProvider;
use App\Modules\Platform\PlatformServiceProvider;
use App\Modules\Pro\ProServiceProvider;
use App\Modules\Ptn\PtnServiceProvider;
use App\Modules\Pws\PwsServiceProvider;
use App\Modules\Rbc\RbcServiceProvider;
use App\Modules\Ref\RefServiceProvider;
use App\Modules\Tkt\TktServiceProvider;
use App\Modules\Tsu\TsuServiceProvider;
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
        // G2 — ruang
        GisServiceProvider::class,
        DshServiceProvider::class,
        // G3 — operasi
        PjkServiceProvider::class,
        CkiServiceProvider::class,
        PtnServiceProvider::class,
        AduServiceProvider::class,
        // G4 — undang-undang & aset
        KesServiceProvider::class,
        TsuServiceProvider::class,
        AstServiceProvider::class,
        // G5 — wawasan & portal
        KbaServiceProvider::class,
        LapServiceProvider::class,
        TktServiceProvider::class,
        PwsServiceProvider::class,
    ],
];
