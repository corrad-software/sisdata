<?php

namespace App\Modules\Per;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adm\Contracts\SearchProviders;
use App\Modules\Dms\Contracts\RecordLinks;
use App\Modules\Ntf\Contracts\NotificationTypes;
use App\Modules\Ntf\Contracts\Reminders;
use App\Modules\Per\Console\EskalasiCommand;
use App\Modules\Per\Console\PautkanLotCommand;
use App\Modules\Per\Console\PortalWakafCommand;
use App\Modules\Per\Console\SampleCasesCommand;
use App\Modules\Per\Contracts\EFaraid;
use App\Modules\Per\Contracts\WakafPortal;
use App\Modules\Per\Integration\MockEFaraid;
use App\Modules\Per\Integration\MockWakafPortal;
use App\Modules\Per\Integration\Notis5aReminder;
use App\Modules\Per\Integration\PemerolehanProfileSection;
use App\Modules\Per\Integration\PemerolehanSearchProvider;
use App\Modules\Per\Services\ApprovalService;
use App\Modules\Per\Services\Escalator;
use App\Modules\Pro\Contracts\ProfileSections;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use App\Modules\Wfe\Contracts\SubjectLinks;
use App\Modules\Wfe\Contracts\WorkflowFlows;
use App\Modules\Wfe\Events\StepDecided;
use App\Modules\Wfe\Events\WorkflowCompleted;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

/**
 * MOD-PER · Rekod Pemerolehan (SRS Modul 4, SRS-PER-01..30).
 *
 * Uses: RBC (permissions, scope), AUD (automatic + explicit audit), DMS
 * (documents), WFE (approval flow `per.kelulusan`, SLA escalation of the
 * approval stage), NTF (5A deadline reminder, stalled-case escalation), ADM
 * (parameters, global search, integration log), PRO (LotRegistry,
 * ProfileSections), HKM (TitleRegistry on closure).
 * Provides: Contracts\WakafPortal and Contracts\EFaraid (mock adapters
 * bound here), Events\AcquisitionCompleted.
 */
class PerServiceProvider extends ModuleServiceProvider
{
    protected string $code = 'per';

    public const PERMISSIONS = [
        'per.kes.lihat' => 'Lihat kes pemerolehan',
        'per.kes.cipta' => 'Daftar kes pemerolehan baharu',
        'per.kes.kemas_kini' => 'Kemas kini, majukan peringkat dan hantar kes untuk kelulusan',
        'per.kes.padam' => 'Batalkan kes pemerolehan',
        'per.dokumen.cipta' => 'Rekod dokumen sokongan dan lokasi simpanan fizikal',
        'per.bayaran.cipta' => 'Rekod bayaran notis 5A',
        'per.bayaran.lulus' => 'Sahkan bayaran notis 5A (Bahagian Kewangan)',
        'per.hak_milik.cipta' => 'Tutup kes dan jana rekod hak milik',
        'per.sulit.lihat' => 'Lihat maklumat peribadi pihak berkaitan (Sulit)',
        'per.laporan.lihat' => 'Lihat laporan dan statistik pemerolehan',
        'per.laporan.eksport' => 'Eksport laporan pemerolehan',
        'per.saluran.kemas_kini' => 'Konfigurasi saluran, peringkat dan senarai semak',
        'per.portal_wakaf.cipta' => 'Hantar permohonan wakaf daripada Portal Hartanah Wakaf (akaun integrasi)',
    ];

    public function register(): void
    {
        $this->app->bindIf(WakafPortal::class, MockWakafPortal::class);
        $this->app->bindIf(EFaraid::class, MockEFaraid::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->call(function (PermissionRegistry $permissions, NotificationTypes $types, Parameters $parameters, WorkflowFlows $flows, SearchProviders $search, ProfileSections $profile) {
            $permissions->register('per', 'Rekod Pemerolehan', self::PERMISSIONS);
            $this->declareNotificationTypes($types);
            $parameters->declare('per.eskalasi_aktif', 'bool', true, 'Eskalasi automatik kes pemerolehan yang melebihi tempoh sasaran peringkat');

            // SRS-PER-29: one flow; layers by channel and value are WFE branch conditions on these fields.
            $flows->declare(ApprovalService::FLOW, 'Kelulusan berlapis kes pemerolehan', 'per', ApprovalService::SUBJECT,
                ['kod_saluran', 'nilai_pemerolehan', 'kod_bahagian', 'kod_daerah']);

            $search->register(PemerolehanSearchProvider::class);
            $profile->register(PemerolehanProfileSection::class);
        });

        // Registered when (or if) the owning module's registry is resolved, so
        // these registries are not built early with stale dependencies.
        $this->callAfterResolving(SubjectLinks::class, fn (SubjectLinks $links) => $links->register(ApprovalService::SUBJECT, '/admin/per/kes/{id}'));
        $this->callAfterResolving(RecordLinks::class, fn (RecordLinks $links) => $links->register('pemerolehan', 'Kes Pemerolehan', '/admin/per/kes/{id}', 'per.kes.lihat'));
        $this->callAfterResolving(Reminders::class, fn (Reminders $reminders) => $reminders->register(Notis5aReminder::class));

        Event::listen(StepDecided::class, [ApprovalService::class, 'onStepDecided']);
        Event::listen(WorkflowCompleted::class, [ApprovalService::class, 'onWorkflowCompleted']);

        if ($this->app->runningInConsole()) {
            $this->commands([SampleCasesCommand::class, EskalasiCommand::class, PautkanLotCommand::class, PortalWakafCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('per:eskalasi')->dailyAt('07:30')->withoutOverlapping()->onOneServer()
                ->when(fn () => (bool) $this->app->make(Parameters::class)->get('per.eskalasi_aktif', true));
            $schedule->command('per:pautkan-lot')->hourly()->withoutOverlapping()->onOneServer();
            $schedule->command('per:portal-wakaf')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
        });
    }

    private function declareNotificationTypes(NotificationTypes $types): void
    {
        $types->register(Escalator::TYPE, 'Kes pemerolehan melebihi tempoh sasaran', NotificationTypes::TINGGI,
            'Eskalasi kes {{no_kes}}: {{peringkat}}',
            'Kes pemerolehan {{no_kes}} ({{saluran}}) telah berada di peringkat "{{peringkat}}" melebihi tempoh sasaran {{tempoh_sasaran_hari}} hari sebanyak {{hari_lewat}} hari. Sila ambil tindakan.',
            ['no_kes', 'saluran', 'peringkat', 'hari_lewat', 'tempoh_sasaran_hari'], true);
        $types->register(Notis5aReminder::TYPE, 'Tarikh akhir bayaran notis 5A', NotificationTypes::TINGGI,
            'Bayaran notis 5A {{no_notis_5a}} perlu dijelaskan dalam {{baki_hari}} hari',
            'Notis 5A {{no_notis_5a}} bagi kes {{no_kes}} perlu dibayar sebelum {{tarikh_akhir_bayaran}}. Baki tertunggak: RM {{baki_tertunggak}}.',
            ['no_kes', 'no_notis_5a', 'tarikh_akhir_bayaran', 'baki_hari', 'baki_tertunggak'], true);
    }
}
