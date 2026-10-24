<?php

namespace App\Modules\Aud;

use App\Models\User;
use App\Modules\Aud\Console\ArchiveCommand;
use App\Modules\Aud\Console\DailyReportsCommand;
use App\Modules\Aud\Console\ReplaySpoolCommand;
use App\Modules\Aud\Console\SiemReplayCommand;
use App\Modules\Aud\Console\VerifyIntegrityCommand;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Aud\Listeners\RecordAuthActivity;
use App\Modules\Aud\Listeners\RecordModelChange;
use App\Modules\Aud\Services\AuditContext;
use App\Modules\Aud\Services\AuditWriter;
use App\Modules\Aud\Services\Geo\GeoIpResolver;
use App\Modules\Aud\Services\Geo\MockGeoIpResolver;
use App\Modules\Aud\Services\Siem\CefSiemSink;
use App\Modules\Aud\Services\Siem\JsonLinesSiemSink;
use App\Modules\Aud\Services\Siem\NullSiemSink;
use App\Modules\Aud\Services\Siem\SiemSink;
use App\Modules\Aud\Services\TrailAuditLogger;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

/**
 * MOD-AUD · Audit Trail (SRS Modul 19). Binds the AuditLogger contract and
 * hooks the global Eloquent listener — the application-infrastructure layer
 * every module's data changes pass through (SRS-AUD-09).
 */
class AudServiceProvider extends ModuleServiceProvider
{
    protected string $code = 'aud';

    public const PERMISSIONS = [
        'aud.rekod.lihat', 'aud.rekod.eksport', 'aud.arkib.lihat', 'aud.integriti.lihat',
        'aud.integriti.cipta', 'aud.laporan.lihat', 'aud.retensi.lihat', 'aud.retensi.kemas_kini',
    ];

    public function register(): void
    {
        $this->app->singleton(GeoIpResolver::class, fn () => new MockGeoIpResolver);

        $this->app->singleton(SiemSink::class, fn () => match (config('aud.siem.pemacu')) {
            'jsonl' => new JsonLinesSiemSink((string) config('aud.siem.laluan')),
            'cef' => new CefSiemSink((bool) config('aud.siem.syslog'), (string) config('aud.siem.laluan_cef')),
            default => new NullSiemSink,
        });

        $this->app->singleton(AuditContext::class);
        $this->app->singleton(AuditWriter::class);
        $this->app->singleton(TrailAuditLogger::class);
        $this->app->singleton(AuditLogger::class, fn ($app) => $app->make(TrailAuditLogger::class));
    }

    public function boot(): void
    {
        parent::boot();

        // SRS-AUD-09: one wildcard listener for every model, no per-model code.
        Event::listen(
            ['eloquent.created: *', 'eloquent.updated: *', 'eloquent.deleted: *', 'eloquent.restored: *'],
            fn (string $event, array $payload) => $this->app->make(RecordModelChange::class)->handle($event, $payload),
        );

        Event::subscribe(RecordAuthActivity::class);

        $this->registerPermissions();

        if ($this->app->runningInConsole()) {
            $this->commands([
                VerifyIntegrityCommand::class,
                ArchiveCommand::class,
                DailyReportsCommand::class,
                SiemReplayCommand::class,
                ReplaySpoolCommand::class,
            ]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('aud:semak-integriti')->cron((string) config('aud.jadual.semak_integriti'))->withoutOverlapping()->onOneServer();
            $schedule->command('aud:arkib')->cron((string) config('aud.jadual.arkib'))->withoutOverlapping()->onOneServer();
            $schedule->command('aud:laporan')->cron((string) config('aud.jadual.laporan'))->onOneServer();
            $schedule->command('aud:ulang-spool')->everyFiveMinutes()->withoutOverlapping();
        });
    }

    private function registerPermissions(): void
    {
        /** @var array<string, string> $labels */
        $labels = (array) trans('aud::aud.kebenaran', [], 'ms');
        $this->app->call(fn (PermissionRegistry $registry) => $registry->register(
            'aud', 'Audit Trail', array_intersect_key($labels, array_flip(self::PERMISSIONS)),
        ));

        // `can:aud.*` route middleware asks AccessControl (MOD-RBC wires the
        // Gate globally too; these definitions only make the keys resolvable).
        foreach (self::PERMISSIONS as $permission) {
            Gate::define($permission, fn (User $user) => $this->app->call(
                fn (AccessControl $access) => $access->allows($user, $permission),
            ));
        }
    }
}
