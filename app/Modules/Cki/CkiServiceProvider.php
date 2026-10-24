<?php

namespace App\Modules\Cki;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adm\Contracts\SearchProviders;
use App\Modules\Cki\Console\HarianCommand;
use App\Modules\Cki\Console\LiputanLalaiCommand;
use App\Modules\Cki\Console\RekonsiliasiCommand;
use App\Modules\Cki\Console\SampleCommand;
use App\Modules\Cki\Console\SemakBilCommand;
use App\Modules\Cki\Console\SemakGrpCommand;
use App\Modules\Cki\Contracts\GrpPembayaran;
use App\Modules\Cki\Contracts\PenyediaCukaiBerpusat;
use App\Modules\Cki\Integration\BilSearchProvider;
use App\Modules\Cki\Integration\Kpi\BayarTepatMasa;
use App\Modules\Cki\Integration\Kpi\CukaiTertunggak;
use App\Modules\Cki\Integration\Kpi\JumlahCukaiTahunan;
use App\Modules\Cki\Integration\Kpi\Penalti;
use App\Modules\Cki\Integration\MockGrpPembayaran;
use App\Modules\Cki\Integration\MockPenyediaMbi;
use App\Modules\Cki\Integration\StatusCukaiLayer;
use App\Modules\Cki\Profile\CukaiSection;
use App\Modules\Cki\Reminders\CukaiAkanMatang;
use App\Modules\Cki\Services\BayaranService;
use App\Modules\Cki\Support\Aliran;
use App\Modules\Dms\Contracts\RecordLinks;
use App\Modules\Dsh\Contracts\KpiProviders;
use App\Modules\Gis\Contracts\MapLayers;
use App\Modules\Ntf\Contracts\NotificationTypes;
use App\Modules\Ntf\Contracts\Reminders;
use App\Modules\Pro\Contracts\ProfileSections;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use App\Modules\Wfe\Contracts\SubjectLinks;
use App\Modules\Wfe\Contracts\WorkflowFlows;
use App\Modules\Wfe\Events\WorkflowCompleted;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

/**
 * MOD-CKI · Pemantauan Cukai Tanah (SRS Modul 6, SRS-CKI-01..24).
 *
 * Provides: Contracts\PenyediaCukaiBerpusat (central provider "MBI" for all
 * PTD / PBT in Selangor — mock bound) and Contracts\GrpPembayaran (outgoing
 * payment requests to GRP MAIS — mock bound).
 * Uses: RBC, AUD, ADM (parameters, integration log, search), NTF (types,
 * reminders), DMS (bills, receipts, vouchers, approvals), WFE (payment
 * approval by value threshold), PRO (LotRegistry, ProfileSections +
 * FinancialSeries), GIS (MapLayers — "Status Cukai" slot), DSH (KpiProviders).
 */
class CkiServiceProvider extends ModuleServiceProvider
{
    protected string $code = 'cki';

    public const PERMISSIONS = [
        'cki.obligasi.lihat' => 'Lihat daftar obligasi cukai mengikut skop',
        'cki.obligasi.cipta' => 'Daftar obligasi cukai bagi lot / premis',
        'cki.obligasi.kemas_kini' => 'Kemas kini obligasi cukai',
        'cki.bil.lihat' => 'Lihat bil cukai, lejar, bayaran dan resit mengikut skop',
        'cki.bil.cipta' => 'Rekod bil manual (mod sandaran) dan import bil',
        'cki.bil.kemas_kini' => 'Pelarasan bil berjustifikasi dan lampiran dokumen',
        'cki.bil.eksport' => 'Eksport garis masa dan senarai bil',
        'cki.bayaran.cipta' => 'Mohon bayaran, laksana bayaran yang diluluskan, rekod bayaran dan muat naik resit',
        'cki.konflik.kemas_kini' => 'Sahkan resolusi konflik penyegerakan',
        'cki.integrasi.lihat' => 'Lihat daftar liputan, mod operasi dan log integrasi',
        'cki.integrasi.kemas_kini' => 'Selenggara daftar liputan dan jalankan semakan API',
        'cki.rekonsiliasi.lihat' => 'Lihat rekonsiliasi dan senarai sisihan',
        'cki.rekonsiliasi.cipta' => 'Jalankan rekonsiliasi bayaran cukai dengan GRP MAIS',
        'cki.laporan.lihat' => 'Lihat dashboard dan laporan cukai',
        'cki.laporan.eksport' => 'Eksport laporan cukai (PDF / Excel)',
    ];

    public function register(): void
    {
        $this->app->singletonIf(PenyediaCukaiBerpusat::class, MockPenyediaMbi::class);
        $this->app->singletonIf(GrpPembayaran::class, MockGrpPembayaran::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->call(function (PermissionRegistry $permissions, Parameters $parameters, NotificationTypes $types, WorkflowFlows $flows, SearchProviders $search, ProfileSections $profile) {
            $permissions->register('cki', 'Pemantauan Cukai Tanah', self::PERMISSIONS);
            $this->declareParameters($parameters);
            $this->declareNotificationTypes($types);
            foreach (Aliran::FLOWS as $code => [$name, $subject, $fields]) {
                $flows->declare($code, $name, 'cki', $subject, $fields);
            }
            $search->register(BilSearchProvider::class);
            $profile->register(CukaiSection::class);
        });

        $this->callAfterResolving(MapLayers::class, fn (MapLayers $layers) => $layers->register(StatusCukaiLayer::class));
        $this->callAfterResolving(KpiProviders::class, function (KpiProviders $kpi) {
            foreach ([JumlahCukaiTahunan::class, CukaiTertunggak::class, BayarTepatMasa::class, Penalti::class] as $p) {
                $kpi->register($p);
            }
        });
        $this->callAfterResolving(Reminders::class, fn (Reminders $r) => $r->register(CukaiAkanMatang::class));
        $this->callAfterResolving(SubjectLinks::class, fn (SubjectLinks $links) => $links->register('transaksi_bayaran_cukai', '/admin/cki/transaksi/{id}'));
        $this->callAfterResolving(RecordLinks::class, fn (RecordLinks $links) => $links->register('cukai', 'Bil Cukai', '/admin/cki/bil/{id}', 'cki.bil.lihat'));

        Event::listen(WorkflowCompleted::class, [BayaranService::class, 'onWorkflowCompleted']);

        if ($this->app->runningInConsole()) {
            $this->commands([SemakBilCommand::class, HarianCommand::class, SemakGrpCommand::class, RekonsiliasiCommand::class, SampleCommand::class, LiputanLalaiCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('cki:semak-bil')->dailyAt('02:00')->withoutOverlapping()->onOneServer();
            $schedule->command('cki:semak-bil --hanya-sandaran')->hourly()->withoutOverlapping()->onOneServer();
            $schedule->command('cki:harian')->dailyAt('03:00')->withoutOverlapping()->onOneServer();
            $schedule->command('cki:semak-grp')->everyThirtyMinutes()->withoutOverlapping()->onOneServer();
            $schedule->command('cki:rekonsiliasi')->dailyAt('06:30')->withoutOverlapping()->onOneServer();
        });
    }

    private function declareParameters(Parameters $p): void
    {
        $p->declare('cki.had_kelulusan', 'json', Aliran::HAD_LALAI, 'Had nilai kelulusan bayaran cukai (RM) → peringkat pelulus (SRS-CKI-14)', critical: true);
        $p->declare('cki.hari_perancangan', 'int', 90, 'Tempoh perancangan jadual bayaran (hari) — SRS-CKI-03');
        $p->declare('cki.denda_peratus', 'string', '10.00', 'Kadar denda lewat bayar (%) bagi bil manual / import selepas tarikh akhir', critical: true);
        $p->declare('cki.denda_minimum', 'string', '10.00', 'Denda minimum (RM) bagi bil manual / import');
        $p->declare('cki.cuba_semula', 'int', 3, 'Bilangan cubaan panggilan API berpusat sebelum dianggap gagal (SRS-CKI-10)');
        $p->declare('cki.backoff_ms', 'int', 200, 'Tempoh tunggu awal (ms) antara cubaan semula — berganda setiap cubaan');
        $p->declare('cki.ambang_eskalasi', 'int', 3, 'Kegagalan berturut-turut sebelum dieskalasi kepada Pentadbir Sistem');
        $p->declare('cki.ambang_tunggakan_hari', 'int', 365, 'Ambang MAIS: tunggakan melebihi (hari) diwarnakan berbeza dalam peta');
        $p->declare('cki.hari_risiko_lewat', 'int', 30, 'Bil belum dibayar yang matang dalam tempoh (hari) dikira "risiko kelewatan"');
        $p->declare('cki.kadar_anggaran_peratus', 'string', '3.00', 'Kadar kenaikan anggaran obligasi tahun hadapan (%) — SRS-CKI-22');
        $p->declare('cki.kod_vot_grp', 'string', 'B29000-CUKAI', 'Kod vot GRP MAIS bagi permohonan bayaran cukai');
    }

    private function declareNotificationTypes(NotificationTypes $t): void
    {
        $t->register(CukaiAkanMatang::TYPE, 'Cukai akan matang', NotificationTypes::TINGGI,
            'Bil cukai {{no_bil}} matang dalam {{baki_hari}} hari',
            '{{jenis_cukai}} bil {{no_bil}} (daerah {{kod_daerah}}) berjumlah {{amaun}} perlu dibayar sebelum {{tarikh_akhir}} ({{baki_hari}} hari lagi).',
            ['no_bil', 'jenis_cukai', 'amaun', 'tarikh_akhir', 'baki_hari', 'kod_daerah']);
        $t->register('cki.tertunggak', 'Cukai tertunggak', NotificationTypes::TINGGI,
            'Bil cukai {{no_bil}} tertunggak',
            '{{jenis_cukai}} bil {{no_bil}} (daerah {{kod_daerah}}) melepasi tarikh akhir {{tarikh_akhir}}. Baki tertunggak {{amaun}}.',
            ['no_bil', 'jenis_cukai', 'amaun', 'tarikh_akhir', 'kod_daerah']);
        $t->register('cki.denda', 'Denda cukai dikenakan', NotificationTypes::TINGGI,
            'Denda {{denda}} dikenakan ke atas bil {{no_bil}}',
            'Bil {{no_bil}} (daerah {{kod_daerah}}) telah melepasi tarikh akhir {{tarikh_akhir}} dan dikenakan denda {{denda}}.',
            ['no_bil', 'denda', 'tarikh_akhir', 'kod_daerah']);
        $t->register('cki.luar_liputan', 'Lot di luar liputan perlu rekod manual', NotificationTypes::BIASA,
            '{{bil_lot}} obligasi cukai daerah {{kod_daerah}} perlu rekod manual',
            '{{bil_lot}} obligasi cukai dalam daerah {{kod_daerah}} berada di luar liputan API atau dalam mod sandaran dan belum mempunyai bil bagi tahun {{tahun}}.',
            ['kod_daerah', 'bil_lot', 'tahun']);
        $t->register('cki.mod_sandaran', 'API cukai tidak tersedia — mod sandaran', NotificationTypes::KRITIKAL,
            'Mod sandaran: {{agensi}}',
            'Integrasi API berpusat bagi {{agensi}} tidak tersedia sejak {{masa}} ({{sebab}}). Sila rekod bil dan resit secara manual sehingga perkhidmatan pulih.',
            ['agensi', 'sebab', 'masa'], true);
        $t->register('cki.mod_pulih', 'API cukai pulih', NotificationTypes::BIASA,
            'API pulih: {{agensi}}',
            'Integrasi API bagi {{agensi}} pulih pada {{masa}}. Penyegerakan semula dijalankan; sila semak konflik jika ada.',
            ['agensi', 'masa']);
        $t->register('cki.kegagalan_integrasi', 'Kegagalan integrasi cukai berterusan', NotificationTypes::KRITIKAL,
            'Integrasi cukai {{agensi}} gagal {{bil_kegagalan}} kali berturut-turut',
            'Panggilan API berpusat bagi {{agensi}} gagal {{bil_kegagalan}} kali berturut-turut ({{sebab}}). Tindakan Pentadbir Sistem diperlukan.',
            ['agensi', 'bil_kegagalan', 'sebab'], true);
        $t->register('cki.konflik', 'Konflik penyegerakan bil cukai', NotificationTypes::TINGGI,
            'Konflik penyegerakan bil {{no_bil}}',
            'Rekod manual bil {{no_bil}} ({{tahun}}, daerah {{kod_daerah}}) berbeza dengan rekod API pada medan: {{medan}}. Pengesahan pegawai diperlukan.',
            ['no_bil', 'tahun', 'medan', 'kod_daerah']);
        $t->register('cki.keputusan_kelulusan', 'Keputusan kelulusan bayaran cukai', NotificationTypes::BIASA,
            'Permohonan bayaran {{no_permohonan}}: {{keputusan}}',
            'Permohonan bayaran {{no_permohonan}} berjumlah {{amaun}} — {{keputusan}}.',
            ['no_permohonan', 'keputusan', 'amaun']);
        $t->register('cki.sisihan_rekonsiliasi', 'Sisihan rekonsiliasi cukai', NotificationTypes::TINGGI,
            'Rekonsiliasi cukai {{dari}}–{{hingga}}: {{bil_sisihan}} sisihan',
            'Rekonsiliasi bayaran cukai bagi {{dari}} hingga {{hingga}} menemui {{bil_sisihan}} sisihan yang memerlukan tindakan Bahagian Kewangan.',
            ['dari', 'hingga', 'bil_sisihan'], true);
    }
}
