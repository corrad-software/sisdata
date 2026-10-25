<?php

namespace App\Modules\Wfe;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Ntf\Contracts\NotificationTypes;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use App\Modules\Wfe\Console\SemakSla;
use App\Modules\Wfe\Contracts\ApproverDirectory;
use App\Modules\Wfe\Contracts\SubjectLinks;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Contracts\WorkflowFlows;
use App\Modules\Wfe\Http\Middleware\RequirePermission;
use App\Modules\Wfe\Services\DefaultApproverDirectory;
use App\Modules\Wfe\Services\Engine;
use App\Modules\Wfe\Services\FlowRegistry;
use App\Modules\Wfe\Services\WorkingCalendar;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

/**
 * MOD-WFE · Workflow dan Kelulusan (SRS Modul 14).
 *
 * Implements WorkflowEngine + WorkflowFlows (+ its own SubjectLinks and a
 * default ApproverDirectory that MOD-RBC may replace).
 */
class WfeServiceProvider extends ModuleServiceProvider
{
    protected string $code = 'wfe';

    public const PERMISSIONS = [
        'wfe.tugasan.lihat' => 'Lihat papan tugasan sendiri',
        'wfe.tugasan.lulus' => 'Ambil tindakan ke atas tugasan (lulus, tolak, kembalikan, tangguh)',
        'wfe.permohonan.lihat' => 'Lihat permohonan sendiri dan sejarahnya',
        'wfe.permohonan.kemas_kini' => 'Tarik balik atau hantar semula permohonan sendiri',
        'wfe.pemantauan.lihat' => 'Lihat papan pemuka pemantauan workflow',
        'wfe.laporan.lihat' => 'Lihat laporan prestasi workflow',
        'wfe.definisi.lihat' => 'Lihat definisi dan templat aliran kerja',
        'wfe.definisi.cipta' => 'Cipta atau salin definisi aliran kerja',
        'wfe.definisi.kemas_kini' => 'Ubah draf definisi, cipta versi baharu, mohon pengaktifan dan pindah versi',
        'wfe.definisi.lulus' => 'Luluskan pengaktifan definisi dan pindah versi (pentadbir kedua)',
        'wfe.kalendar.lihat' => 'Lihat kalendar cuti umum',
        'wfe.kalendar.kemas_kini' => 'Kemas kini kalendar cuti umum',
        'wfe.pemangku.cipta' => 'Tetapkan pemangku sendiri',
        'wfe.pemangku.kemas_kini' => 'Urus pemangku bagi mana-mana pelulus',
        'wfe.kumpulan.lihat' => 'Lihat kumpulan pelulus',
        'wfe.kumpulan.kemas_kini' => 'Urus kumpulan pelulus',
        'wfe.contoh.cipta' => 'Mulakan aliran contoh (demo)',
    ];

    public function register(): void
    {
        $this->app->singleton(FlowRegistry::class);
        $this->app->alias(FlowRegistry::class, WorkflowFlows::class);
        $this->app->alias(FlowRegistry::class, SubjectLinks::class);

        // MOD-RBC (registered earlier) may bind the real directory; keep it.
        $this->app->bindIf(ApproverDirectory::class, DefaultApproverDirectory::class);

        $this->app->scoped(WorkingCalendar::class);
        $this->app->bind(WorkflowEngine::class, Engine::class);
    }

    public function boot(): void
    {
        parent::boot();

        /** @var Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('wfe.boleh', RequirePermission::class);

        if (! $this->app->routesAreCached()) {
            Route::middleware('web')->group($this->modulePath().'/routes-web.php');
        }

        $this->resolve(PermissionRegistry::class)->register('wfe', 'Workflow dan Kelulusan', self::PERMISSIONS);

        $this->declareNotificationTypes($this->resolve(NotificationTypes::class));
        $this->declareParameters($this->resolve(Parameters::class));

        $this->resolve(WorkflowFlows::class)->declare('contoh.kelulusan', 'Contoh kelulusan dua peringkat', 'wfe', 'contoh_permohonan', ['nilai', 'kod_daerah', 'kategori_tanah']);
        $this->resolve(SubjectLinks::class)->register('contoh_permohonan', '/admin/wfe/permohonan?rekod={id}');

        if ($this->app->runningInConsole()) {
            $this->commands([SemakSla::class]);
        }

        // SRS-WFE-07: scheduled SLA check (ADM job scheduler runs schedule:run).
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('wfe:semak-sla')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
        });
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $abstract
     * @return T
     */
    private function resolve(string $abstract): object
    {
        return $this->app->make($abstract);
    }

    private function declareNotificationTypes(NotificationTypes $types): void
    {
        $types->register('wfe.tugasan_baharu', 'Tugasan workflow baharu', NotificationTypes::TINGGI,
            'Tugasan baharu: {{tajuk}}',
            'Anda mempunyai tugasan baharu bagi "{{tajuk}}" di peringkat {{peringkat}}. Tarikh akhir tindakan: {{tarikh_akhir}}.',
            ['tajuk', 'peringkat', 'tarikh_akhir']);
        $types->register('wfe.sla_hampir', 'Amaran SLA hampir tamat', NotificationTypes::TINGGI,
            'SLA hampir tamat: {{tajuk}}',
            'Tugasan "{{tajuk}}" ({{peringkat}}) perlu diambil tindakan dalam {{baki_jam}} jam bekerja lagi, sebelum {{tarikh_akhir}}.',
            ['tajuk', 'peringkat', 'baki_jam', 'tarikh_akhir']);
        $types->register('wfe.eskalasi', 'Eskalasi tugasan workflow', NotificationTypes::KRITIKAL,
            'Eskalasi aras {{aras}}: {{tajuk}}',
            'Tugasan "{{tajuk}}" di peringkat {{peringkat}} yang ditugaskan kepada {{penerima_asal}} telah melepasi had masa SLA dan dieskalasi kepada anda (aras {{aras}}).',
            ['tajuk', 'peringkat', 'aras', 'penerima_asal'], true);
        $types->register('wfe.keputusan', 'Keputusan permohonan', NotificationTypes::BIASA,
            'Keputusan permohonan: {{tajuk}}',
            'Permohonan "{{tajuk}}" telah {{keputusan}}. Ulasan: {{ulasan}}',
            ['tajuk', 'keputusan', 'ulasan']);
    }

    private function declareParameters(Parameters $parameters): void
    {
        $parameters->declare('wfe.hari_hujung_minggu', 'json', [6, 7], 'Hari hujung minggu (ISO: 6 = Sabtu, 7 = Ahad) — Selangor: Sabtu & Ahad');
        $parameters->declare('wfe.waktu_kerja_mula', 'string', '08:00', 'Waktu mula bekerja (SLA dalam jam)');
        $parameters->declare('wfe.waktu_kerja_tamat', 'string', '17:00', 'Waktu tamat bekerja (SLA dalam jam)');
        $parameters->declare('wfe.negeri_kalendar', 'string', 'selangor', 'Negeri bagi kalendar cuti umum');
        $parameters->declare('wfe.sla_amaran_jam', 'int', 24, 'Amaran SLA dihantar apabila baki masa (jam bekerja) kurang daripada');
        $parameters->declare('wfe.tangguh_hari_lalai', 'int', 5, 'Tempoh tangguh lalai (hari bekerja)');
        $parameters->declare('wfe.mfa_sah_minit', 'int', 10, 'Tempoh sah pengesahan MFA step-up (minit)', true);
        $parameters->declare('wfe.mfa_wajib_otp', 'bool', false, 'Wajibkan bukti OTP (amr/acr) dalam token step-up', true);
        $parameters->declare('wfe.mfa_acr_diterima', 'json', ['mfa', 'otp', '2'], 'Nilai acr yang diterima sebagai MFA', true);
        $parameters->declare('wfe.mfa_pintas_uat', 'bool', false, 'UAT SAHAJA (APP_ENV=uat): anggap MFA step-up sah tanpa SSO MAIS — diabaikan dalam persekitaran lain', true);
        $parameters->declare('wfe.mfa_acr_values', 'string', '', 'acr_values dihantar semasa step-up (kosong = tiada)', true);
        $parameters->declare('wfe.peranan_penyelia_lalai', 'string', 'admin', 'Peranan penyelia lalai jika hierarki tidak ditetapkan');
    }
}
