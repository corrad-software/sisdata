<?php

namespace App\Modules\Adm;

use App\Modules\Adm\Console\PangkasLog;
use App\Modules\Adm\Console\SemakKesihatan;
use App\Modules\Adm\Console\SemakTugasan;
use App\Modules\Adm\Console\TetapkanRahsia;
use App\Modules\Adm\Contracts\Integrations;
use App\Modules\Adm\Contracts\JobProgress;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adm\Contracts\ReferenceCodes;
use App\Modules\Adm\Contracts\ReportTemplates;
use App\Modules\Adm\Contracts\SearchProviders;
use App\Modules\Adm\Contracts\SearchSettings;
use App\Modules\Adm\Contracts\Secrets;
use App\Modules\Adm\Http\Middleware\AdmBoleh;
use App\Modules\Adm\Http\Middleware\ModPenyelenggaraan;
use App\Modules\Adm\Http\Middleware\ProksiDipercayai;
use App\Modules\Adm\Http\Middleware\TokenPentadbir;
use App\Modules\Adm\Listeners\HantarPeristiwaKeselamatan;
use App\Modules\Adm\Services\Carian\KodRujukanSearchProvider;
use App\Modules\Adm\Services\Carian\PendaftaranCarian;
use App\Modules\Adm\Services\IntegrasiService;
use App\Modules\Adm\Services\KelulusanKonfigurasi;
use App\Modules\Adm\Services\Kesihatan\PemantauKesihatan;
use App\Modules\Adm\Services\KodRujukanService;
use App\Modules\Adm\Services\Logging\DatabaseLogHandler;
use App\Modules\Adm\Services\Logging\RedactingJsonFormatter;
use App\Modules\Adm\Services\Logging\RedactLogs;
use App\Modules\Adm\Services\ParameterService;
use App\Modules\Adm\Services\SecretStore;
use App\Modules\Adm\Services\Siem\SiemLogHandler;
use App\Modules\Adm\Services\TemplatService;
use App\Modules\Adm\Services\Tugasan\PembilangRekod;
use App\Modules\Adm\Services\Tugasan\PenjadualTugasan;
use App\Modules\Ntf\Contracts\NotificationTypes;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use App\Modules\Wfe\Contracts\WorkflowFlows;
use App\Modules\Wfe\Events\WorkflowCompleted;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;

/**
 * MOD-ADM · Pentadbiran Sistem (SRS-ADM-01..29). Owns and binds the
 * Parameters and SearchProviders contracts (plus Secrets, Integrations,
 * ReferenceCodes, ReportTemplates, SearchSettings, JobProgress), the job
 * scheduler, structured/redacted logging, health monitoring, SIEM shipping,
 * maintenance mode and trusted proxies.
 */
class AdmServiceProvider extends ModuleServiceProvider
{
    protected string $code = 'adm';

    public const PERMISSIONS = [
        'adm.parameter.lihat' => 'Lihat parameter sistem',
        'adm.parameter.kemas_kini' => 'Kemas kini parameter sistem',
        'adm.keselamatan.kemas_kini' => 'Ubah tetapan keselamatan / sesi / retensi (parameter kritikal)',
        'adm.kod_rujukan.lihat' => 'Lihat kod rujukan',
        'adm.kod_rujukan.cipta' => 'Tambah & import kod rujukan',
        'adm.kod_rujukan.kemas_kini' => 'Pinda & aktifkan semula kod rujukan',
        'adm.kod_rujukan.padam' => 'Nyahaktif kod rujukan',
        'adm.templat.lihat' => 'Lihat templat laporan',
        'adm.templat.cipta' => 'Tambah templat laporan',
        'adm.templat.kemas_kini' => 'Pinda templat laporan',
        'adm.konfigurasi.lihat' => 'Lihat permohonan perubahan konfigurasi',
        'adm.konfigurasi.lulus' => 'Lulus/tolak perubahan konfigurasi kritikal (pentadbir kedua)',
        'adm.tugasan.lihat' => 'Lihat tugasan berjadual & sejarah larian',
        'adm.tugasan.cipta' => 'Tambah tugasan berjadual',
        'adm.tugasan.kemas_kini' => 'Pinda, aktif/nyahaktif & jalankan tugasan berjadual',
        'adm.tugasan.padam' => 'Padam tugasan berjadual',
        'adm.log.lihat' => 'Lihat log ralat & log integrasi',
        'adm.log.eksport' => 'Eksport log',
        'adm.kesihatan.lihat' => 'Lihat skrin kesihatan sistem',
        'adm.amaran.lihat' => 'Terima amaran sistem (Bahagian ICT)',
        'adm.integrasi.lihat' => 'Lihat tetapan integrasi',
        'adm.integrasi.cipta' => 'Tambah integrasi',
        'adm.integrasi.kemas_kini' => 'Pinda & uji integrasi',
        'adm.rahsia.lihat' => 'Lihat senarai rahsia (tanpa nilai)',
        'adm.rahsia.kemas_kini' => 'Tetapkan rahsia sistem',
        'adm.carian.lihat' => 'Lihat tetapan carian',
        'adm.carian.kemas_kini' => 'Ubah tetapan carian, sinonim & indeks semula',
        'adm.penyelenggaraan.kemas_kini' => 'Hidup/matikan mod penyelenggaraan (dan berurus niaga semasa penyelenggaraan)',
        'adm.api.cipta' => 'Cipta token API pentadbiran',
    ];

    public function register(): void
    {
        $this->app->singleton(ParameterService::class);
        $this->app->singleton(Parameters::class, fn ($app) => $app->make(ParameterService::class));

        $this->app->singleton(PendaftaranCarian::class);
        $this->app->singleton(SearchProviders::class, fn ($app) => $app->make(PendaftaranCarian::class));
        $this->app->singleton(SearchSettings::class, fn ($app) => $app->make(PendaftaranCarian::class));

        $this->app->singleton(PembilangRekod::class);
        $this->app->singleton(JobProgress::class, fn ($app) => $app->make(PembilangRekod::class));

        $this->app->singleton(SecretStore::class);
        $this->app->singleton(Secrets::class, fn ($app) => $app->make(SecretStore::class));
        $this->app->singleton(IntegrasiService::class);
        $this->app->singleton(Integrations::class, fn ($app) => $app->make(IntegrasiService::class));
        $this->app->singleton(KodRujukanService::class);
        $this->app->singleton(ReferenceCodes::class, fn ($app) => $app->make(KodRujukanService::class));
        $this->app->singleton(TemplatService::class);
        $this->app->singleton(ReportTemplates::class, fn ($app) => $app->make(TemplatService::class));

        $this->app->singleton(PenjadualTugasan::class);
        $this->app->singleton(PemantauKesihatan::class);

        $this->configureLogging();
    }

    public function boot(): void
    {
        parent::boot();

        $this->declareParameters();
        $this->declareToOtherModules();

        $this->app->make(SearchProviders::class)->register(KodRujukanSearchProvider::class);

        Event::listen(WorkflowCompleted::class, fn (WorkflowCompleted $e) => $this->app->make(KelulusanKonfigurasi::class)->onWorkflowCompleted($e));
        Event::subscribe(HantarPeristiwaKeselamatan::class);

        // Job scheduler (SRS-ADM-06): ADM jobs into Laravel's scheduler; adopted foreign jobs tracked.
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $this->app->make(PenjadualTugasan::class)->register($schedule));
        Event::listen(CommandStarting::class, function (CommandStarting $e) {
            if (in_array($e->command, ['schedule:run', 'schedule:work', 'schedule:test'], true)) {
                $this->app->make(PenjadualTugasan::class)->decorateForeign($this->app->make(Schedule::class));
            }
        });
        Event::listen(ScheduledTaskStarting::class, fn ($e) => $this->app->make(PenjadualTugasan::class)->onStarting($e));
        Event::listen(ScheduledTaskFinished::class, fn ($e) => $this->app->make(PenjadualTugasan::class)->onFinished($e));
        Event::listen(ScheduledTaskFailed::class, fn ($e) => $this->app->make(PenjadualTugasan::class)->onFailed($e));
        Event::listen(ScheduledTaskSkipped::class, fn ($e) => $this->app->make(PenjadualTugasan::class)->onSkipped($e));

        $this->commands([TetapkanRahsia::class, SemakKesihatan::class, SemakTugasan::class, PangkasLog::class]);

        $this->registerMiddleware();
    }

    private function declareParameters(): void
    {
        $p = $this->app->make(Parameters::class);

        // Retention (SRS-ADM-14) — critical per SRS-ADM-18 ("polisi retensi").
        $p->declare('adm.log_ralat_simpan_hari', 'int', 180, 'Tempoh simpan log ralat aplikasi (hari)', true);
        $p->declare('adm.log_integrasi_simpan_hari', 'int', 180, 'Tempoh simpan log integrasi (hari)', true);
        $p->declare('adm.sejarah_tugasan_simpan_hari', 'int', 365, 'Tempoh simpan sejarah larian tugasan (hari)', true);
        $p->declare('adm.kesihatan_simpan_hari', 'int', 30, 'Tempoh simpan rekod semakan kesihatan (hari)', true);

        // Monitoring thresholds (SRS-ADM-17).
        $p->declare('adm.ambang_baris_gilir', 'int', 1000, 'Ambang amaran saiz baris gilir (bilangan kerja)');
        $p->declare('adm.ambang_storan_peratus', 'int', 85, 'Ambang amaran penggunaan storan (%)');
        $p->declare('adm.ambang_integrasi_gagal', 'int', 3, 'Ambang amaran kegagalan integrasi berturut-turut');
        $p->declare('adm.tugasan_toleransi_lewat_minit', 'int', 15, 'Toleransi lewat sebelum tugasan dianggap terlepas jadual (minit)');

        // Security (SRS-ADM-26/27) — critical.
        $p->declare('adm.proksi_dipercayai', 'string', '', 'Alamat IP/CIDR WAF & proksi dipercayai untuk X-Forwarded-For (pisah dengan koma, * = semua; 127.0.0.1 dan ::1 sentiasa dipercayai)', true);
        $p->declare('adm.siem_pemacu', 'string', 'fail', 'Penghantaran log keselamatan ke SIEM: fail | syslog | tiada', true);
        $p->declare('adm.siem_hos', 'string', '', 'Hos:port syslog SIEM (jika pemacu syslog)', true);

        // Maintenance (SRS-ADM-29).
        $p->declare('adm.mod_penyelenggaraan', 'bool', false, 'Mod penyelenggaraan aktif');
        $p->declare('adm.notis_penyelenggaraan', 'string', 'Sistem sedang dalam penyelenggaraan berjadual. Transaksi baharu tidak dapat dibuat buat sementara waktu.', 'Notis mod penyelenggaraan kepada pengguna');
        $p->declare('adm.penyelenggaraan_tamat', 'string', '', 'Jangkaan tamat penyelenggaraan (tarikh/masa)');

        // Search (SRS-ADM-21).
        $p->declare('adm.carian_had_hasil', 'int', 10, 'Bilangan maksimum hasil carian bagi setiap entiti');
    }

    private function declareToOtherModules(): void
    {
        $this->contract(PermissionRegistry::class)->register('adm', 'Pentadbiran Sistem', self::PERMISSIONS);

        $types = $this->contract(NotificationTypes::class);
        $types->register('adm.tugasan_gagal', 'Tugasan berjadual gagal', NotificationTypes::TINGGI,
            'Tugasan berjadual gagal: {{tugasan}}', 'Tugasan {{tugasan}} ({{kod}}) gagal selepas {{cubaan}} cubaan. Ralat: {{ralat}}', ['tugasan', 'kod', 'cubaan', 'ralat'], true);
        $types->register('adm.tugasan_terlepas', 'Tugasan berjadual terlepas jadual', NotificationTypes::TINGGI,
            'Tugasan tidak dilaksanakan: {{tugasan}}', 'Tugasan {{tugasan}} ({{kod}}) yang dijadualkan pada {{dijadualkan}} tidak dilaksanakan.', ['tugasan', 'kod', 'dijadualkan'], true);
        $types->register('adm.amaran_kesihatan', 'Amaran kesihatan sistem', NotificationTypes::KRITIKAL,
            'Amaran sistem: {{komponen}} ({{status}})', 'Komponen {{komponen}} berstatus {{status}}. {{mesej}}', ['komponen', 'status', 'mesej'], true);
        $types->register('adm.integrasi_gagal', 'Integrasi gagal berulang', NotificationTypes::KRITIKAL,
            'Integrasi gagal: {{integrasi}}', 'Integrasi {{integrasi}} ({{kod}}) gagal {{bilangan}} kali berturut-turut.', ['integrasi', 'kod', 'bilangan'], true);
        $types->register('adm.kelulusan_konfigurasi', 'Perubahan konfigurasi menunggu kelulusan', NotificationTypes::TINGGI,
            'Kelulusan diperlukan: {{kunci}}', '{{pemohon}} memohon perubahan konfigurasi kritikal ({{jenis}}: {{kunci}}). Sila semak dan putuskan.', ['jenis', 'kunci', 'pemohon'], true);
        $types->register('adm.keputusan_konfigurasi', 'Keputusan perubahan konfigurasi', NotificationTypes::BIASA,
            'Permohonan konfigurasi {{kunci}}: {{keputusan}}', 'Permohonan perubahan konfigurasi {{kunci}} telah {{keputusan}}.', ['kunci', 'keputusan']);

        $this->contract(WorkflowFlows::class)->declare(
            KelulusanKonfigurasi::FLOW, 'Kelulusan konfigurasi kritikal (pentadbir kedua)', 'adm', KelulusanKonfigurasi::SUBJECT, ['jenis_konfig', 'kunci'],
        );
    }

    /**
     * Resolve another module's contract (typed by the contract, not by whichever
     * stand-in happens to be bound).
     *
     * @template T of object
     *
     * @param  class-string<T>  $contract
     * @return T
     */
    private function contract(string $contract): object
    {
        return $this->app->make($contract);
    }

    private function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('adm.boleh', AdmBoleh::class);
        $router->aliasMiddleware('adm.token', TokenPentadbir::class);
        $router->pushMiddlewareToGroup('api', ModPenyelenggaraan::class);

        $this->app->make(HttpKernel::class)->prependMiddleware(ProksiDipercayai::class);
    }

    /**
     * SRS-ADM-11/15: add the structured DB + JSON channels to the default
     * stack and a redaction tap to every channel; plus the "keselamatan"
     * channel that ships to the SIEM (SRS-ADM-27).
     */
    private function configureLogging(): void
    {
        $config = $this->app->make('config');
        $channels = (array) $config->get('logging.channels', []);

        foreach ($channels as $name => $channel) {
            if (is_array($channel) && isset($channel['driver'])) {
                $channels[$name]['tap'] = array_values(array_unique([...($channel['tap'] ?? []), RedactLogs::class]));
            }
        }

        $extra = ['adm_pangkalan_data'];
        $channels['adm_pangkalan_data'] = [
            'driver' => 'monolog',
            'handler' => DatabaseLogHandler::class,
            'level' => $config->get('adm.log.aras_pangkalan_data', 'warning'),
            'tap' => [RedactLogs::class],
        ];

        if ($config->get('adm.log.json', true)) {
            $extra[] = 'adm_json';
            $channels['adm_json'] = [
                'driver' => 'daily',
                'path' => storage_path('logs/sisdata.json.log'),
                'level' => 'info',
                'days' => 14,
                'formatter' => RedactingJsonFormatter::class,
                'tap' => [RedactLogs::class],
            ];
        }

        $channels['keselamatan'] = ['driver' => 'monolog', 'handler' => SiemLogHandler::class, 'level' => 'info'];

        $default = (string) $config->get('logging.default', 'stack');
        if (($channels[$default]['driver'] ?? null) === 'stack') {
            $channels[$default]['channels'] = array_values(array_unique([...(array) $channels[$default]['channels'], ...$extra]));
        } elseif (isset($channels[$default])) {
            $channels['sisdata'] = ['driver' => 'stack', 'channels' => [$default, ...$extra], 'ignore_exceptions' => false];
            $config->set('logging.default', 'sisdata');
        }

        $config->set('logging.channels', $channels);
    }
}
