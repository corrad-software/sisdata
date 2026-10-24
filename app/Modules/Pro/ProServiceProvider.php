<?php

namespace App\Modules\Pro;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adm\Contracts\SearchProviders;
use App\Modules\Dms\Contracts\RecordLinks;
use App\Modules\Ntf\Contracts\NotificationTypes;
use App\Modules\Pro\Console\ArkibRetensiCommand;
use App\Modules\Pro\Console\ImportStagingCommand;
use App\Modules\Pro\Console\JanaDokumenDataCommand;
use App\Modules\Pro\Console\KesanDuplikasiCommand;
use App\Modules\Pro\Console\LaporanKualitiCommand;
use App\Modules\Pro\Console\SeedTadbirCommand;
use App\Modules\Pro\Contracts\LotRegistry;
use App\Modules\Pro\Contracts\ProfileSections;
use App\Modules\Pro\Http\Middleware\BolehPro;
use App\Modules\Pro\Http\Middleware\KunciApiPro;
use App\Modules\Pro\Sections\DokumenSection;
use App\Modules\Pro\Sections\IdentitiSection;
use App\Modules\Pro\Sections\JejakAuditSection;
use App\Modules\Pro\Sections\KualitiSection;
use App\Modules\Pro\Sections\LogAksesSection;
use App\Modules\Pro\Sections\SpatialSection;
use App\Modules\Pro\Services\LotSearchProvider;
use App\Modules\Pro\Services\LotService;
use App\Modules\Pro\Services\PengesanDuplikasi;
use App\Modules\Pro\Services\PengurusArkib;
use App\Modules\Pro\Services\ProfileSectionRegistry;
use App\Modules\Pro\Services\ProLotRegistry;
use App\Modules\Pro\Services\RujukanInduk;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use App\Modules\Wfe\Contracts\SubjectLinks;
use App\Modules\Wfe\Contracts\WorkflowFlows;
use App\Modules\Wfe\Events\WorkflowCompleted;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

/**
 * MOD-PRO · Pangkalan Data Induk dan Profil Hartanah 360° (SRS-PRO-01..30,
 * SRS-DAT-01..09). Owns the LotRegistry and ProfileSections contracts; uses
 * RBC, AUD, ADM, NTF, DMS and WFE only through theirs.
 */
class ProServiceProvider extends ModuleServiceProvider
{
    protected string $code = 'pro';

    public const PERMISSIONS = [
        'pro.lot.lihat' => 'Lihat lot tanah dan Profil Hartanah 360° (mengikut skop)',
        'pro.lot.cipta' => 'Cipta rekod lot tanah',
        'pro.lot.kemas_kini' => 'Kemas kini rekod lot tanah (perubahan kritikal perlu pengesahan pegawai kedua)',
        'pro.lot.padam' => 'Padam rekod lot berstatus draf',
        'pro.lot.eksport' => 'Eksport senarai lot dan profil (Excel, CSV, PDF, JSON)',
        'pro.spatial.cipta' => 'Muat naik / pautkan geometri lot',
        'pro.spatial.lulus' => 'Sahkan geometri lot (disahkan juruukur / PTG)',
        'pro.import.cipta' => 'Import data pukal (Excel / CSV)',
        'pro.duplikasi.lihat' => 'Lihat cadangan duplikasi rekod',
        'pro.duplikasi.kemas_kini' => 'Putuskan cadangan duplikasi (sah, gabung, tolak)',
        'pro.stewardship.lihat' => 'Lihat daftar stewardship data',
        'pro.stewardship.kemas_kini' => 'Urus daftar stewardship data',
        'pro.kualiti.lihat' => 'Lihat laporan kualiti data dan tugasan steward',
        'pro.kualiti.kemas_kini' => 'Urus peraturan kualiti data dan selesaikan tugasan',
        'pro.kamus.lihat' => 'Lihat kamus data dan model data fizikal',
        'pro.kamus.kemas_kini' => 'Sunting metadata kamus data',
        'pro.arkib.lihat' => 'Lihat rekod arkib dan jadual retensi',
        'pro.arkib.cipta' => 'Cadang / hantar rekod untuk diarkib',
        'pro.arkib.kemas_kini' => 'Pulihkan rekod diarkib dan urus jadual retensi',
        'pro.jejak.lihat' => 'Lihat jejak audit, log akses profil dan salasilah data lot',
        'pro.api.cipta' => 'Jana dan batalkan kunci API profil hartanah sendiri',
        'pro.api.kemas_kini' => 'Urus semua kunci API profil hartanah',
        'pro.sulit.lihat' => 'Capai lot dan medan berklasifikasi Sulit',
        'pro.rahsia.lihat' => 'Capai lot dan medan berklasifikasi Rahsia',
    ];

    public function register(): void
    {
        $this->app->singleton(ProfileSectionRegistry::class);
        $this->app->singleton(ProfileSections::class, fn ($app) => $app->make(ProfileSectionRegistry::class));
        $this->app->bind(LotRegistry::class, ProLotRegistry::class);
        $this->app->scoped(RujukanInduk::class);
    }

    public function boot(): void
    {
        parent::boot();

        $router = $this->app['router'];
        $router->aliasMiddleware('pro.boleh', BolehPro::class);
        $router->aliasMiddleware('pro.api', KunciApiPro::class);

        $this->app->call(function (PermissionRegistry $permissions, Parameters $parameters, NotificationTypes $types, WorkflowFlows $flows, SearchProviders $search, ProfileSections $sections) {
            $permissions->register('pro', 'Pangkalan Data Induk dan Profil Hartanah 360°', self::PERMISSIONS);
            $this->declareParameters($parameters);
            $this->registerNotificationTypes($types);

            // SRS-PRO-22 / SRS-GEN-03: the second-officer confirmation is a WFE flow.
            $flows->declare(LotService::FLOW_CRITICAL, 'Pengesahan pegawai kedua — perubahan kritikal rekod induk lot', 'pro', LotService::SUBJECT_VERSION, ['kod_daerah', 'bahagian_pemilik', 'medan']);
            $flows->declare(PengurusArkib::FLOW, 'Kelulusan pengarkiban rekod lot (retensi)', 'pro', PengurusArkib::SUBJECT, ['kod_daerah', 'bahagian_pemilik']);

            $search->register(LotSearchProvider::class);

            foreach ([IdentitiSection::class, SpatialSection::class, KualitiSection::class, DokumenSection::class, JejakAuditSection::class, LogAksesSection::class] as $section) {
                $sections->register($section);
            }
        });

        // Lazily: resolving these registries at boot would pin their dependencies too early.
        $this->callAfterResolving(RecordLinks::class, function (RecordLinks $links) {
            $links->register('lot_tanah', 'Lot Tanah', '/admin/pro/lot/{id}', 'pro.lot.lihat');
            $links->register('sesi_import', 'Sesi Import Data', '/admin/pro/import/{id}', 'pro.import.cipta');
        });
        $this->callAfterResolving(SubjectLinks::class, fn (SubjectLinks $links) => $links->register(PengurusArkib::SUBJECT, '/admin/pro/arkib'));

        Event::listen(WorkflowCompleted::class, [LotService::class, 'onWorkflowCompleted']);
        Event::listen(WorkflowCompleted::class, [PengurusArkib::class, 'onWorkflowCompleted']);

        if ($this->app->runningInConsole()) {
            $this->commands([ImportStagingCommand::class, LaporanKualitiCommand::class, KesanDuplikasiCommand::class, ArkibRetensiCommand::class, JanaDokumenDataCommand::class, SeedTadbirCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('pro:laporan-kualiti')->weeklyOn(1, '06:30')->withoutOverlapping();
            $schedule->command('pro:kesan-duplikasi')->dailyAt('02:30')->withoutOverlapping();
            $schedule->command('pro:arkib-retensi')->monthlyOn(1, '03:00')->withoutOverlapping();
        });
    }

    private function declareParameters(Parameters $parameters): void
    {
        $parameters->declare('pro.medan_kritikal', 'json', LotService::DEFAULT_CRITICAL, 'Medan lot yang perlu pengesahan pegawai kedua apabila diubah', critical: true);
        $parameters->declare('pro.toleransi_keluasan_peratus', 'int', 10, 'Toleransi beza keluasan dikira berbanding diisytihar (%)');
        $parameters->declare('pro.ambang_pertindihan_peratus', 'int', 5, 'Ambang pertindihan geometri untuk status "bertindih" (% poligon lebih kecil)');
        $parameters->declare('pro.kriteria_duplikasi', 'json', PengesanDuplikasi::DEFAULT_CRITERIA, 'Kriteria dan pemberat padanan duplikasi lot');
        $parameters->declare('pro.import_baris_maks', 'int', 5000, 'Bilangan baris maksimum bagi satu fail import');
    }

    private function registerNotificationTypes(NotificationTypes $types): void
    {
        $types->register('pro.perubahan_kritikal_keputusan', 'Keputusan pengesahan perubahan kritikal rekod lot', NotificationTypes::TINGGI,
            'Perubahan kritikal lot {{no_lot}} {{keputusan}}',
            'Perubahan kritikal yang anda mohon bagi lot {{no_lot}} telah {{keputusan}} oleh pegawai pengesah.',
            ['no_lot', 'keputusan']);
        $types->register('pro.kualiti_data_gagal', 'Rekod gagal kawalan kualiti data', NotificationTypes::BIASA,
            'Tugasan kualiti data: {{rekod}}',
            '{{bilangan}} isu kualiti data bagi {{rekod}} ({{komponen}}) memerlukan tindakan anda sebagai data steward.',
            ['rekod', 'bilangan', 'komponen']);
        $types->register('pro.arkib_keputusan', 'Keputusan pengarkiban rekod lot', NotificationTypes::BIASA,
            'Pengarkiban lot {{no_lot}} {{keputusan}}',
            'Permohonan pengarkiban lot {{no_lot}} telah {{keputusan}}.',
            ['no_lot', 'keputusan']);
    }
}
