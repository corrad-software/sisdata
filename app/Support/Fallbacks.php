<?php

namespace App\Support;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adm\Contracts\SearchProviders;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Hkm\Contracts\TitleRegistry;
use App\Modules\Ntf\Contracts\NotificationTypes;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Pro\Contracts\LotRegistry;
use App\Modules\Pro\Contracts\ProfileSections;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use App\Modules\Wfe\Contracts\WorkflowFlows;
use Illuminate\Contracts\Foundation\Application;

/**
 * Bindings used only when the module that owns a contract isn't enabled
 * (e.g. a worktree building one module in isolation). Real modules bind their
 * implementation in their own provider, which runs later and wins.
 */
class Fallbacks
{
    public static function register(Application $app): void
    {
        $app->bindIf(AuditLogger::class, Fallbacks\FallbackAuditLogger::class);
        $app->bindIf(AccessControl::class, Fallbacks\FallbackAccessControl::class);
        $app->singletonIf(PermissionRegistry::class, Fallbacks\FallbackPermissionRegistry::class);
        $app->bindIf(Notifier::class, Fallbacks\FallbackNotifier::class);
        $app->singletonIf(NotificationTypes::class, Fallbacks\FallbackNotificationTypes::class);
        $app->singletonIf(WorkflowFlows::class, Fallbacks\FallbackWorkflowFlows::class);
        $app->singletonIf(SearchProviders::class, Fallbacks\FallbackSearchProviders::class);
        $app->singletonIf(Parameters::class, Fallbacks\FallbackParameters::class);
        $app->bindIf(LotRegistry::class, Fallbacks\FallbackLotRegistry::class);
        $app->singletonIf(ProfileSections::class, Fallbacks\FallbackProfileSections::class);
        $app->bindIf(TitleRegistry::class, Fallbacks\FallbackTitleRegistry::class);
    }
}
