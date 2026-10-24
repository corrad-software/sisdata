<?php

use App\Modules\Platform\PlatformServiceProvider;

/*
| SISDATA modules (app/Modules/*). Order matters only for migrations that
| depend on another module's tables — list platform modules (G0) first.
*/

return [
    'enabled' => [
        PlatformServiceProvider::class,
    ],
];
