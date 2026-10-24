<?php

namespace App\Modules\Dsh;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adm\Contracts\SearchProviders;
use App\Modules\Dsh\Console\RingkasanBerkalaCommand;
use App\Modules\Dsh\Contracts\InsightGenerator;
use App\Modules\Dsh\Contracts\KpiProviders;
use App\Modules\Dsh\Insight\OllamaInsight;
use App\Modules\Dsh\Insight\PeraturanInsight;
use App\Modules\Dsh\Kpi\BilanganLot;
use App\Modules\Dsh\Kpi\DrafSaya;
use App\Modules\Dsh\Kpi\HakMilikAktif;
use App\Modules\Dsh\Kpi\HakMilikBelumDisahkan;
use App\Modules\Dsh\Kpi\HakMilikHampirTamat;
use App\Modules\Dsh\Kpi\HasilKos;
use App\Modules\Dsh\Kpi\KeluasanTanah;
use App\Modules\Dsh\Kpi\KesMahkamahAktif;
use App\Modules\Dsh\Kpi\LotMengikutBahagian;
use App\Modules\Dsh\Kpi\NilaiPortfolio;
use App\Modules\Dsh\Kpi\NotifikasiBelumDibaca;
use App\Modules\Dsh\Kpi\PemerolehanDalamProses;
use App\Modules\Dsh\Kpi\PemerolehanMelebihiTempoh;
use App\Modules\Dsh\Kpi\PemerolehanTahunan;
use App\Modules\Dsh\Kpi\StatusTanah;
use App\Modules\Dsh\Kpi\StoranDokumen;
use App\Modules\Dsh\Kpi\TugasanDalamSla;
use App\Modules\Dsh\Kpi\TugasanLewatSla;
use App\Modules\Dsh\Kpi\TugasanSaya;
use App\Modules\Dsh\Kpi\TugasanTertunggak;
use App\Modules\Dsh\Services\Ambang;
use App\Modules\Dsh\Services\Aras;
use App\Modules\Dsh\Services\Baca;
use App\Modules\Dsh\Services\DrafSearchProvider;
use App\Modules\Dsh\Services\KpiRegistry;
use App\Modules\Dsh\Services\Ringkasan;
use App\Modules\Ntf\Contracts\NotificationTypes;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Throwable;

/**
 * MOD-DSH · Dashboard Eksekutif (SRS-DSH-01..25). Owns the KpiProviders
 * registry (every module registers its KPIs through it) and the
 * InsightGenerator contract (local AI only, SRS-DSH-18). Provides portfolio
 * KPIs for modules that have none yet by READING their tables (never
 * writing, never importing their classes). Uses RBC, AUD, ADM, NTF only
 * through their contracts; the map widget reuses MOD-GIS layers.
 */
class DshServiceProvider extends ModuleServiceProvider
{
    protected string $code = 'dsh';

    public const PERMISSIONS = [
        'dsh.dashboard.lihat' => 'Lihat dashboard aras Operasi, inbox tugasan, draf transaksi dan carian',
        'dsh.pengurusan.lihat' => 'Lihat dashboard aras Pengurusan (prestasi bahagian dan daerah)',
        'dsh.eksekutif.lihat' => 'Lihat dashboard aras Eksekutif (KPI portfolio seluruh negeri)',
        'dsh.dashboard.eksport' => 'Eksport dashboard (PDF / Excel) dan jana snapshot KPI',
        'dsh.ai.lihat' => 'Guna medan prompt AI dan pemerhatian automatik dashboard',
        'dsh.langganan.cipta' => 'Langgan ringkasan dashboard berkala melalui e-mel',
        'dsh.widget.kemas_kini' => 'Konfigurasi widget dan ambang indikator dashboard',
        'dsh.log_ai.lihat' => 'Lihat log prompt AI semua pengguna',
        'dsh.rahsia.lihat' => 'Lihat ringkasan data Rahsia pada dashboard (nilai portfolio, kes mahkamah)',
    ];

    /** MOD-DSH's own KPI providers (portfolio of G0/G1 modules without KPIs). */
    public const KPIS = [
        BilanganLot::class, KeluasanTanah::class, LotMengikutBahagian::class, NilaiPortfolio::class,
        HakMilikAktif::class, StatusTanah::class, HakMilikHampirTamat::class, HakMilikBelumDisahkan::class,
        PemerolehanTahunan::class, PemerolehanDalamProses::class, PemerolehanMelebihiTempoh::class,
        TugasanTertunggak::class, TugasanLewatSla::class, TugasanDalamSla::class, TugasanSaya::class,
        NotifikasiBelumDibaca::class, DrafSaya::class, StoranDokumen::class, KesMahkamahAktif::class, HasilKos::class,
    ];

    public function register(): void
    {
        // Always resolve through the contract, so every module's
        // callAfterResolving(KpiProviders::class, …) registration runs first.
        $this->app->singleton(KpiProviders::class, fn ($app) => new KpiRegistry($app));
        $this->app->singleton(KpiRegistry::class, fn ($app) => $app->make(KpiProviders::class));
        $this->app->singleton(Ambang::class);

        $this->app->singleton(InsightGenerator::class, function ($app) {
            $rules = $app->make(PeraturanInsight::class);
            if (config('dsh.ai.pemacu') !== 'ollama') {
                return $rules;
            }
            try {
                return new OllamaInsight($rules, (string) config('dsh.ai.ollama_url'), (string) config('dsh.ai.model'), (int) config('dsh.ai.timeout', 20));
            } catch (Throwable $e) {
                // A non-local endpoint is refused (SRS-DSH-18) → rule-based engine.
                report($e);

                return $rules;
            }
        });
    }

    public function boot(): void
    {
        parent::boot();
        Baca::configure();

        $this->app->call(function (PermissionRegistry $permissions, Parameters $parameters, NotificationTypes $types, SearchProviders $search) {
            $permissions->register('dsh', 'Dashboard Eksekutif', self::PERMISSIONS);
            $this->declareParameters($parameters);
            $types->register(Ringkasan::JENIS_NOTIFIKASI, 'Ringkasan dashboard berkala', NotificationTypes::MAKLUMAT,
                'Ringkasan dashboard {{aras}} — {{tarikh}}',
                "Ringkasan dashboard {{aras}} pada {{tarikh}}: {{bil_kpi}} indikator, {{bil_merah}} merah, {{bil_kuning}} kuning.\n{{ringkasan}}\nButiran penuh dan eksport PDF/Excel tersedia dalam SISDATA.",
                ['aras', 'tarikh', 'bil_kpi', 'bil_merah', 'bil_kuning', 'ringkasan']);
            $search->register(DrafSearchProvider::class);
        });

        $this->callAfterResolving(KpiProviders::class, function (KpiProviders $kpi) {
            foreach (self::KPIS as $provider) {
                $kpi->register($provider);
            }
        });

        if ($this->app->runningInConsole()) {
            $this->commands([RingkasanBerkalaCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('dsh:ringkasan-berkala')->dailyAt('07:00')->withoutOverlapping()->name('dsh:ringkasan-berkala');
        });
    }

    private function declareParameters(Parameters $parameters): void
    {
        $parameters->declare('dsh.cache_saat', 'int', 300, 'Tempoh cache nilai KPI dashboard (saat, maksimum 900 — ketinggalan data ≤ 15 minit, SRS-DSH-13)');
        $parameters->declare('dsh.aras_peranan', 'json', Aras::PEMETAAN_LALAI, 'Pemetaan kod peranan → aras dashboard lalai (eksekutif / pengurusan / operasi)');
        $parameters->declare('dsh.hari_hampir_tamat', 'int', 365, 'Tempoh (hari) hak milik dianggap hampir tamat tempoh pada dashboard');
        $parameters->declare('dsh.sasaran_sla_peratus', 'int', 90, 'Sasaran peratus tugasan diselesaikan dalam SLA');
        $parameters->declare('dsh.url_manual', 'string', '', 'Pautan manual pengguna SISDATA (bantuan dalam talian)');
    }
}
