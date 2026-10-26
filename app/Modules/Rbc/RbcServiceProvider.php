<?php

namespace App\Modules\Rbc;

use App\Models\User;
use App\Modules\Ntf\Contracts\RecipientDirectory;
use App\Modules\Rbc\Console\CheckAccountStatuses;
use App\Modules\Rbc\Console\ExpireIdleSessions;
use App\Modules\Rbc\Console\RunRecertification;
use App\Modules\Rbc\Console\SyncPermissions;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Rbc\Contracts\GeoIpLocator;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use App\Modules\Rbc\Events\AccessDenied;
use App\Modules\Rbc\Http\Middleware\EnforceSession;
use App\Modules\Rbc\Http\Middleware\RecordAccessDenied;
use App\Modules\Rbc\Http\Middleware\RequirePasswordChange;
use App\Modules\Rbc\Listeners\DetectRepeatedDenials;
use App\Modules\Rbc\Services\MockGeoIpLocator;
use App\Modules\Rbc\Services\PanduanService;
use App\Modules\Rbc\Services\PermissionCatalogue;
use App\Modules\Rbc\Services\PermissionSync;
use App\Modules\Rbc\Services\RbcAccessControl;
use App\Modules\Rbc\Services\SecurityPolicy;
use App\Modules\Rbc\Services\SessionManager;
use App\Modules\Rbc\Services\UserDirectory;
use App\Modules\Rbc\Support\DeniedAbilities;
use App\Modules\Wfe\Contracts\ApproverDirectory;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\NoPendingMigrations;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

/**
 * MOD-RBC (Modul 18): the single authorisation service for SISDATA.
 *
 * - binds AccessControl (RbcAccessControl) and PermissionRegistry
 *   (PermissionCatalogue), overriding app/Support/Fallbacks, plus NTF's
 *   RecipientDirectory and WFE's ApproverDirectory (UserDirectory);
 * - wires Laravel's Gate so `$user->can('x.y.z')` / `can:x.y.z` go through
 *   AccessControl, and records refusals as AccessDenied events;
 * - adds session enforcement (idle timeout, binding, forced termination)
 *   and the forced-password-change gate to the `api` middleware group.
 */
class RbcServiceProvider extends ModuleServiceProvider
{
    protected string $code = 'rbc';

    /** MOD-RBC's own permission catalogue (SRS-RBC-06: modul × fungsi × tindakan). */
    public const PERMISSIONS = [
        'rbc.pengguna.lihat' => 'Lihat senarai dan butiran pengguna',
        'rbc.pengguna.cipta' => 'Cipta akaun pengguna',
        'rbc.pengguna.kemas_kini' => 'Kemas kini pengguna, status akaun, peranan dan skop',
        'rbc.pengguna.padam' => 'Padam akaun pengguna',
        'rbc.peranan.lihat' => 'Lihat peranan dan kebenaran',
        'rbc.peranan.cipta' => 'Cipta dan klon peranan',
        'rbc.peranan.kemas_kini' => 'Kemas kini peranan, kebenaran dan skop peranan',
        'rbc.peranan.padam' => 'Padam peranan',
        'rbc.sesi.lihat' => 'Lihat sesi aktif',
        'rbc.sesi.padam' => 'Tamatkan sesi pengguna secara paksa',
        'rbc.log_masuk.lihat' => 'Lihat log log masuk',
        'rbc.log_masuk.eksport' => 'Eksport log log masuk',
        'rbc.amaran.lihat' => 'Lihat amaran keselamatan',
        'rbc.amaran.kemas_kini' => 'Tandakan amaran keselamatan sebagai disemak',
        'rbc.matriks.lihat' => 'Lihat matriks capaian',
        'rbc.matriks.eksport' => 'Eksport matriks capaian (Excel/PDF)',
        'rbc.laporan.lihat' => 'Lihat laporan capaian',
        'rbc.laporan.eksport' => 'Eksport laporan capaian',
        'rbc.pemetaan.lihat' => 'Lihat peraturan pemetaan tuntutan SSO',
        'rbc.pemetaan.kemas_kini' => 'Urus peraturan pemetaan tuntutan SSO',
        'rbc.polisi.lihat' => 'Lihat polisi keselamatan (sesi, kata laluan, kunci akaun)',
        'rbc.polisi.kemas_kini' => 'Ubah polisi keselamatan',
        'rbc.recert.lihat' => 'Lihat kitaran pengesahan semula capaian',
        'rbc.recert.cipta' => 'Buka dan tutup kitaran pengesahan semula capaian',
        'rbc.recert.lulus' => 'Sahkan atau tarik capaian dalam kitaran pengesahan semula',
        'rbc.pentadbiran.akses' => 'Akses pentadbiran laman (tetapan laman, status platform)',
    ];

    public function register(): void
    {
        $this->app->singleton(PermissionCatalogue::class);
        $this->app->alias(PermissionCatalogue::class, PermissionRegistry::class);

        $this->app->singleton(AccessControl::class, RbcAccessControl::class);
        $this->app->singleton(SecurityPolicy::class);
        $this->app->singleton(SessionManager::class);
        $this->app->singleton(DeniedAbilities::class);
        $this->app->singleton(GeoIpLocator::class, MockGeoIpLocator::class);

        // Query-based "who holds role/permission/scope/jawatan X" for the
        // contracts MOD-NTF and MOD-WFE defined (their defaults are bindIf).
        // NTF's SupervisorResolver keeps NTF's default: RBC has no hierarchy.
        $this->app->bind(RecipientDirectory::class, UserDirectory::class);
        $this->app->bind(ApproverDirectory::class, UserDirectory::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->make(PermissionRegistry::class)->register('rbc', 'Pengguna, Peranan dan Capaian', self::PERMISSIONS);
        $this->app->make(SecurityPolicy::class)->declareToAdm();
        $this->app->make(PanduanService::class)->declareToAdm();

        $this->bootGate();
        $this->bootMiddleware();
        $this->bootSessionHardening();
        $this->bootEvents();

        if ($this->app->runningInConsole()) {
            $this->commands([SyncPermissions::class, CheckAccountStatuses::class, ExpireIdleSessions::class, RunRecertification::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('rbc:tamat-sesi')->everyMinute()->withoutOverlapping();
            $schedule->command('rbc:semak-status')->hourly()->withoutOverlapping();
            $schedule->command('rbc:recert')->dailyAt('01:00')->withoutOverlapping();
        });
    }

    /** SRS-RBC-08 / SRS-GEN-01: every "<modul>.<fungsi>.<tindakan>" ability is decided by AccessControl. */
    private function bootGate(): void
    {
        Gate::before(function ($user, string $ability) {
            if (! $user instanceof User || ! preg_match(PermissionCatalogue::KEY_PATTERN, $ability)) {
                return null; // not a permission key — let policies/other gates decide
            }

            return $this->app->make(AccessControl::class)->allows($user, $ability);
        });

        Gate::after(function ($user, string $ability, ?bool $result) {
            if ($result === false) {
                $this->app->make(DeniedAbilities::class)->record($ability);
            }
        });
    }

    private function bootMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->pushMiddlewareToGroup('api', RecordAccessDenied::class);
        $router->pushMiddlewareToGroup('api', EnforceSession::class);
        $router->pushMiddlewareToGroup('api', RequirePasswordChange::class);
    }

    /**
     * SRS-RBC-20 cookie flags. HttpOnly always; SameSite=Lax (Strict would
     * drop the cookie on the SSO MAIS redirect back to the OIDC callback);
     * Secure whenever the app is served over HTTPS (always in production).
     * Laravel's own session lifetime must outlast the longest configurable
     * idle timeout — EnforceSession is what actually times sessions out.
     */
    private function bootSessionHardening(): void
    {
        $secure = config('session.secure');
        if ($secure === null || $this->app->environment('production')) {
            $secure = $this->app->environment('production') || str_starts_with((string) config('app.url'), 'https://');
        }

        config([
            'session.http_only' => true,
            'session.same_site' => in_array(config('session.same_site'), ['lax', 'strict'], true) ? config('session.same_site') : 'lax',
            'session.secure' => (bool) $secure,
            'session.expire_on_close' => false,
            'session.lifetime' => max((int) config('session.lifetime'), SecurityPolicy::DEFINITIONS['sesi_tamat_minit']['max']),
        ]);

        // Trusted proxies (incl. loopback for the Next.js rewrite, which the
        // login log and session binding rely on) are configured in ONE place:
        // MOD-ADM's ProksiDipercayai middleware, from adm.proksi_dipercayai.
    }

    private function bootEvents(): void
    {
        Event::listen(AccessDenied::class, DetectRepeatedDenials::class);

        // Persist the permission catalogue + base roles after every migrate.
        $sync = function (MigrationsEnded|NoPendingMigrations $event) {
            if ($event->method === 'up') {
                $this->app->make(PermissionSync::class)->sync();
            }
        };
        Event::listen(MigrationsEnded::class, $sync);
        Event::listen(NoPendingMigrations::class, $sync);

        // Memoised permissions/policy must not leak across requests in one process.
        Event::listen(RequestHandled::class, function () {
            RbcAccessControl::flushAll();
            $this->app->make(SecurityPolicy::class)->flush();
        });
    }
}
