<?php

namespace App\Modules\Ntf;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Aud\Events\AuditIntegrityBroken;
use App\Modules\Ntf\Console\EscalateCommand;
use App\Modules\Ntf\Console\RemindersCommand;
use App\Modules\Ntf\Console\RequeueCommand;
use App\Modules\Ntf\Console\SendDigestsCommand;
use App\Modules\Ntf\Console\TestNotificationCommand;
use App\Modules\Ntf\Contracts\NotificationTypes;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Ntf\Contracts\RecipientDirectory;
use App\Modules\Ntf\Contracts\Reminders;
use App\Modules\Ntf\Contracts\SmsGateway;
use App\Modules\Ntf\Contracts\SupervisorResolver;
use App\Modules\Ntf\Contracts\WhatsAppGateway;
use App\Modules\Ntf\Listeners\SystemEventNotifier;
use App\Modules\Ntf\Services\Channels\LogSmsGateway;
use App\Modules\Ntf\Services\Channels\LogWhatsAppGateway;
use App\Modules\Ntf\Services\DefaultRecipientDirectory;
use App\Modules\Ntf\Services\NotificationEngine;
use App\Modules\Ntf\Services\ReminderRegistry;
use App\Modules\Ntf\Services\RoleSupervisorResolver;
use App\Modules\Ntf\Services\Settings;
use App\Modules\Ntf\Services\TypeRegistry;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use App\Modules\Rbc\Events\AccountStatusChanged;
use App\Modules\Rbc\Events\SuspiciousActivityDetected;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

class NtfServiceProvider extends ModuleServiceProvider
{
    protected string $code = 'ntf';

    /** Permission catalogue (SRS-RBC-09). Reading your own inbox needs only login. */
    public const PERMISSIONS = [
        'ntf.jenis.lihat' => 'Lihat jenis, peraturan dan templat notifikasi',
        'ntf.jenis.kemas_kini' => 'Kemas kini jenis, peraturan penerima dan templat notifikasi',
        'ntf.pemantauan.lihat' => 'Lihat papan pemuka pemantauan dan log penghantaran notifikasi',
        'ntf.saluran.kemas_kini' => 'Urus saluran penghantaran (cuba semula, terima amaran kegagalan)',
        'ntf.pengumuman.cipta' => 'Hantar pengumuman kepada kumpulan pengguna',
    ];

    public function register(): void
    {
        $this->app->singleton(TypeRegistry::class);
        $this->app->singleton(NotificationTypes::class, fn ($app) => $app->make(TypeRegistry::class));
        $this->app->bind(Notifier::class, NotificationEngine::class);

        $this->app->singleton(ReminderRegistry::class);
        $this->app->singleton(Reminders::class, fn ($app) => $app->make(ReminderRegistry::class));

        // Defaults another module (RBC/WFE, an SMS integration…) may replace.
        $this->app->bindIf(RecipientDirectory::class, DefaultRecipientDirectory::class);
        $this->app->bindIf(SupervisorResolver::class, RoleSupervisorResolver::class);
        $this->app->bindIf(SmsGateway::class, LogSmsGateway::class);
        $this->app->bindIf(WhatsAppGateway::class, LogWhatsAppGateway::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->loadViewsFrom(__DIR__.'/resources/views', 'ntf');

        $this->app->call(function (PermissionRegistry $permissions, Parameters $parameters) {
            $permissions->register('ntf', 'Notifikasi', self::PERMISSIONS);
            Settings::declare($parameters);
        });

        // `can:ntf.…` middleware → AccessControl, also before MOD-RBC wires the Gate globally.
        foreach (array_keys(self::PERMISSIONS) as $permission) {
            if (! Gate::has($permission)) {
                Gate::define($permission, fn (User $user) => $this->app->call(
                    fn (AccessControl $access) => $access->allows($user, $permission),
                ));
            }
        }

        $this->registerTypes($this->app->make(TypeRegistry::class));

        Event::listen(AuditIntegrityBroken::class, [SystemEventNotifier::class, 'auditIntegrityBroken']);
        Event::listen(AccountStatusChanged::class, [SystemEventNotifier::class, 'accountStatusChanged']);
        Event::listen(SuspiciousActivityDetected::class, [SystemEventNotifier::class, 'suspiciousActivity']);

        if ($this->app->runningInConsole()) {
            $this->commands([
                TestNotificationCommand::class,
                RemindersCommand::class,
                EscalateCommand::class,
                SendDigestsCommand::class,
                RequeueCommand::class,
            ]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $settings = $this->app->make(Settings::class);
            $schedule->command('ntf:peringatan')->dailyAt((string) $settings->get('ntf.masa_peringatan'))->withoutOverlapping()->onOneServer();
            $schedule->command('ntf:ringkasan')->dailyAt((string) $settings->get('ntf.masa_ringkasan'))->withoutOverlapping()->onOneServer();
            $schedule->command('ntf:eskalasi')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
            $schedule->command('ntf:proses-tertunggak')->everyTenMinutes()->withoutOverlapping()->onOneServer();
        });
    }

    /** MOD-NTF's own types; other modules use them too (e.g. announcements). */
    private function registerTypes(TypeRegistry $types): void
    {
        $admins = [[RecipientDirectory::KEBENARAN, 'ntf.saluran.kemas_kini']];

        $types->register('ntf.pengumuman', 'Pengumuman', NotificationTypes::BIASA,
            '{{tajuk}}', '{{kandungan}}', ['tajuk', 'kandungan']);

        $types->register('ntf.penghantaran_gagal', 'Amaran kegagalan penghantaran notifikasi', NotificationTypes::TINGGI,
            'Penghantaran notifikasi gagal: {{jenis_notifikasi}}',
            "Penghantaran notifikasi \"{{jenis_notifikasi}}\" melalui saluran {{saluran}} kepada {{penerima}} gagal secara muktamad selepas {{bilangan_cubaan}} cubaan (kod ralat: {{kod_ralat}}).\n\nSila semak papan pemuka pemantauan notifikasi.",
            ['jenis_notifikasi', 'saluran', 'penerima', 'kod_ralat', 'bilangan_cubaan', 'id_penghantaran'], true);
        // In-app only: an e-mail outage must not swallow its own alert.
        $types->seedDefaults('ntf.penghantaran_gagal', ['dalam_sistem'], $admins);

        $types->register('ntf.eskalasi', 'Eskalasi notifikasi kritikal belum dibaca', NotificationTypes::TINGGI,
            'Eskalasi: {{subjek_asal}}',
            'Notifikasi kritikal "{{subjek_asal}}" kepada {{penerima_asal}} belum dibaca selepas {{tempoh_minit}} minit dan dieskalasi kepada anda sebagai penyelia.',
            ['subjek_asal', 'penerima_asal', 'tempoh_minit'], true);

        $types->register('ntf.integriti_audit', 'Amaran integriti log audit', NotificationTypes::KRITIKAL,
            'Amaran: integriti log audit terjejas',
            'Semakan rantaian cincangan log audit menemui ketidakpadanan bermula pada jujukan {{jujukan}}. Sila siasat segera.',
            ['jujukan'], true);
        $types->seedDefaults('ntf.integriti_audit', null, $admins);

        $types->register('ntf.status_akaun', 'Perubahan status akaun', NotificationTypes::TINGGI,
            'Status akaun anda telah berubah',
            'Status akaun SISDATA anda telah berubah daripada {{status_lama}} kepada {{status_baharu}}. Hubungi Pentadbir Sistem jika ini tidak dijangka.',
            ['status_lama', 'status_baharu'], true);

        // SRS-RBC-22: MOD-RBC's suspicious activity alerts → whoever can action them (Pentadbir Sistem).
        $types->register('ntf.amaran_keselamatan', 'Amaran aktiviti log masuk mencurigakan', NotificationTypes::KRITIKAL,
            'Amaran keselamatan: {{jenis}}',
            'Aktiviti mencurigakan dikesan: {{jenis}} melibatkan {{pengguna}} dari alamat IP {{alamat_ip}} pada {{masa}}. Sila semak amaran keselamatan di modul Capaian.',
            ['jenis', 'pengguna', 'alamat_ip', 'masa'], true);
        $types->seedDefaults('ntf.amaran_keselamatan', null, [[RecipientDirectory::KEBENARAN, 'rbc.amaran.kemas_kini']]);

        $types->register('ntf.ujian', 'Notifikasi ujian', NotificationTypes::BIASA,
            'Notifikasi ujian SISDATA',
            'Ini adalah notifikasi ujian yang dihantar pada {{tarikh}}. {{catatan}}',
            ['catatan']);
    }
}
