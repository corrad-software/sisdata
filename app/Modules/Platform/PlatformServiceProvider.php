<?php

namespace App\Modules\Platform;

use App\Support\Modules\ModuleServiceProvider;

/**
 * Reference module: the smallest thing that exercises every convention a
 * real SISDATA module uses (routes, lang, migrations folder, tests). Copy
 * this folder when starting a new module.
 */
class PlatformServiceProvider extends ModuleServiceProvider
{
    protected string $code = 'platform';
}
