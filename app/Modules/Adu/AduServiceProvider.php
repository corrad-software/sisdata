<?php

namespace App\Modules\Adu;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adm\Contracts\SearchProviders;
use App\Modules\Adu\Console\SampleCommand;
use App\Modules\Adu\Console\SegerakPusatCommand;
use App\Modules\Adu\Console\SemakSlaCommand;
use App\Modules\Adu\Contracts\CaptchaVerifier;
use App\Modules\Adu\Contracts\CentralComplaintSystem;
use App\Modules\Adu\Contracts\ComplaintIntake;
use App\Modules\Adu\Contracts\ReporterMessenger;
use App\Modules\Adu\Gis\AduanMapLayer;
use App\Modules\Adu\Integrasi\MailReporterMessenger;
use App\Modules\Adu\Integrasi\MockCaptcha;
use App\Modules\Adu\Integrasi\MockSistemAduanPusat;
use App\Modules\Adu\Kpi\AduanDalamSla;
use App\Modules\Adu\Kpi\AduanTerbuka;
use App\Modules\Adu\Kpi\PurataMasaSelesai;
use App\Modules\Adu\Profile\AduanSection;
use App\Modules\Adu\Reminders\LawatanTapakAduan;
use App\Modules\Adu\Services\AduanSearchProvider;
use App\Modules\Adu\Services\AduanService;
use App\Modules\Adu\Services\AduComplaintIntake;
use App\Modules\Adu\Support\Rujukan;
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
 * MOD-ADU · Aduan dan Isu Tanah (SRS-ADU-01..27). Implements ComplaintIntake
 * (for PTN, PWS, TKT and the central complaints adapter) and its own
 * adapter contracts (CentralComplaintSystem, CaptchaVerifier,
 * ReporterMessenger) with local mocks; uses RBC, AUD, ADM, NTF, DMS, WFE,
 * PRO, GIS and DSH only through their contracts.
 */
class AduServiceProvider extends ModuleServiceProvider
{
    protected string $code = 'adu';

    public const PERMISSIONS = [
        'adu.aduan.lihat' => 'Lihat barisan aduan dan butiran aduan mengikut skop (tanpa identiti pengadu)',
        'adu.aduan.cipta' => 'Rekod aduan baharu (kaunter, e-mel, telefon, lapangan)',
        'adu.aduan.kemas_kini' => 'Tindakan pegawai: siasatan, tindakan susulan, lawatan tapak, rujukan, eskalasi, mohon penutupan, buka semula',
        'adu.aduan.eksport' => 'Eksport laporan aduan',
        'adu.penugasan.kemas_kini' => 'Unit Aduan: triaj, tugaskan / tugaskan semula, tanda pendua, sahkan bukan tanah MAIS',
        'adu.sulit.lihat' => 'Unit Aduan: lihat identiti pengadu (Sulit; setiap capaian diaudit)',
        'adu.laporan.lihat' => 'Lihat dashboard, laporan dan analitik aduan',
        'adu.audit_capaian.lihat' => 'Lihat laporan audit capaian identiti pengadu',
        'adu.konfigurasi.lihat' => 'Lihat konfigurasi kategori, SLA, triaj dan penugasan aduan',
        'adu.konfigurasi.kemas_kini' => 'Ubah konfigurasi kategori, SLA, laluan eskalasi, triaj, penugasan dan integrasi aduan',
        'adu.integrasi.cipta' => 'Akaun integrasi: hantar aduan daripada Sistem Aduan berpusat MAIS',
    ];

    public function register(): void
    {
        $this->app->bind(ComplaintIntake::class, AduComplaintIntake::class);
        $this->app->bindIf(CentralComplaintSystem::class, MockSistemAduanPusat::class);
        $this->app->bindIf(CaptchaVerifier::class, MockCaptcha::class);
        $this->app->bindIf(ReporterMessenger::class, MailReporterMessenger::class);
        $this->app->scoped(Rujukan::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->call(function (PermissionRegistry $permissions, Parameters $parameters, NotificationTypes $types, WorkflowFlows $flows, SubjectLinks $links, SearchProviders $search, ProfileSections $profile) {
            $permissions->register('adu', 'Aduan dan Isu Tanah', self::PERMISSIONS);
            $this->declareParameters($parameters);
            $this->registerNotificationTypes($types);

            // SRS-ADU-19: closure approval by a supervisor through MOD-WFE (AduAliranSeeder seeds the default).
            $flows->declare(AduanService::FLOW, 'Kelulusan penutupan aduan', 'adu', AduanService::SUBJECT, ['kategori', 'kod_daerah', 'tahap_keutamaan', 'saluran', 'kategori_resolusi']);
            $links->register(AduanService::SUBJECT, '/admin/adu/aduan/{id}');

            $search->register(AduanSearchProvider::class);
            $profile->register(AduanSection::class);
        });

        // Lazily, so the registries are not built during boot.
        $this->callAfterResolving(MapLayers::class, fn (MapLayers $layers) => $layers->register(AduanMapLayer::class));
        $this->callAfterResolving(KpiProviders::class, function (KpiProviders $kpi) {
            foreach ([AduanTerbuka::class, AduanDalamSla::class, PurataMasaSelesai::class] as $provider) {
                $kpi->register($provider);
            }
        });
        $this->callAfterResolving(RecordLinks::class, fn (RecordLinks $l) => $l->register(AduanService::SUBJECT, 'Aduan', '/admin/adu/aduan/{id}', 'adu.aduan.lihat'));
        if ($this->app->bound(Reminders::class)) {
            $this->app->make(Reminders::class)->register(LawatanTapakAduan::class);
        }

        Event::listen(WorkflowCompleted::class, [AduanService::class, 'handle']);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('adu:semak-sla')->everyFifteenMinutes()->withoutOverlapping()->name('adu:semak-sla');
            $schedule->command('adu:segerak-pusat')->everyThirtyMinutes()->withoutOverlapping()->name('adu:segerak-pusat');
        });

        if ($this->app->runningInConsole()) {
            $this->commands([SemakSlaCommand::class, SegerakPusatCommand::class, SampleCommand::class]);
        }
    }

    private function declareParameters(Parameters $p): void
    {
        $p->declare('adu.awam_had_ip_sejam', 'int', 5, 'Portal awam: had aduan sejam bagi satu alamat IP (SRS-ADU-24)');
        $p->declare('adu.awam_had_telefon_sehari', 'int', 3, 'Portal awam: had aduan sehari bagi satu nombor telefon (SRS-ADU-24)');
        $p->declare('adu.awam_had_semak_sejam', 'int', 30, 'Portal awam: had semakan status sejam bagi satu alamat IP');
        $p->declare('adu.awam_saiz_maks_kb', 'int', 10240, 'Portal awam: saiz maksimum setiap fail (KB)');
        $p->declare('adu.awam_bil_fail_maks', 'int', 5, 'Portal awam: bilangan fail maksimum setiap aduan');
        $p->declare('adu.captcha_token_ujian', 'string', 'lulus-captcha', 'CAPTCHA tiruan (pembangunan): token yang diterima');
        $p->declare('adu.ambang_hampir_tamat_peratus', 'int', 80, 'Status SLA "hampir tamat" apabila peratus tempoh penyelesaian digunakan mencapai nilai ini');
        $p->declare('adu.peranan_ketua_bahagian', 'string', 'pelulus', 'Kod peranan penerima eskalasi "ketua_bahagian" (SRS-ADU-14)');
        $p->declare('adu.toleransi_padanan_meter', 'int', 0, 'Triaj: padankan ke lot terdekat dalam jarak (meter) jika titik di luar semua poligon; 0 = tepat sahaja');
        $p->declare('adu.sla_respons_lalai', 'int', 3, 'SLA respons lalai (hari bekerja) jika kategori tiada konfigurasi');
        $p->declare('adu.sla_selesai_lalai', 'int', 30, 'SLA penyelesaian lalai (hari bekerja) jika kategori tiada konfigurasi');
        $p->declare('adu.mock_pusat_gagal', 'bool', false, 'Adapter mock Sistem Aduan berpusat: simulasi gagal (ujian SRS-INT-10)');
    }

    private function registerNotificationTypes(NotificationTypes $t): void
    {
        $f = ['no_rujukan', 'kategori', 'daerah', 'keutamaan'];
        $t->register('adu.aduan_baharu', 'Aduan baharu diterima', NotificationTypes::TINGGI,
            'Aduan baharu {{no_rujukan}} — {{kategori}}',
            'Aduan baharu {{no_rujukan}} ({{kategori}}, daerah {{daerah}}, keutamaan {{keutamaan}}) telah diterima dan menunggu triaj/penugasan.', $f);
        $t->register('adu.aduan_ditugaskan', 'Aduan ditugaskan kepada anda', NotificationTypes::TINGGI,
            'Aduan {{no_rujukan}} ditugaskan kepada anda',
            'Aduan {{no_rujukan}} ({{kategori}}, daerah {{daerah}}, keutamaan {{keutamaan}}) telah ditugaskan kepada anda. Sila mulakan siasatan dalam tempoh SLA respons.', $f, true);
        $t->register('adu.sla_hampir_tamat', 'SLA aduan hampir tamat', NotificationTypes::TINGGI,
            'SLA aduan {{no_rujukan}} hampir tamat',
            'Tempoh {{sasaran}} bagi aduan {{no_rujukan}} ({{kategori}}) akan tamat pada {{tarikh_sasaran}}. Peringkat eskalasi {{peringkat}}.', ['no_rujukan', 'kategori', 'peringkat', 'sasaran', 'tarikh_sasaran'], true);
        $t->register('adu.sla_dilepasi', 'SLA aduan dilepasi (eskalasi)', NotificationTypes::KRITIKAL,
            'Eskalasi: SLA aduan {{no_rujukan}} dilepasi',
            'Tempoh {{sasaran}} bagi aduan {{no_rujukan}} ({{kategori}}) telah dilepasi pada {{tarikh_sasaran}}. Aduan dieskalasikan ke peringkat {{peringkat}}.', ['no_rujukan', 'kategori', 'peringkat', 'sasaran', 'tarikh_sasaran'], true);
        $t->register('adu.eskalasi', 'Aduan dieskalasikan (manual)', NotificationTypes::TINGGI,
            'Aduan {{no_rujukan}} dieskalasikan kepada anda',
            'Aduan {{no_rujukan}} ({{kategori}}) telah dieskalasikan: {{sebab}}', [...$f, 'sebab']);
        $t->register('adu.rujukan', 'Aduan dirujuk ke bahagian', NotificationTypes::BIASA,
            'Aduan {{no_rujukan}} dirujuk kepada {{bahagian}}',
            'Aduan {{no_rujukan}} ({{kategori}}, daerah {{daerah}}) dirujuk kepada {{bahagian}} untuk tindakan.', [...$f, 'bahagian']);
        $t->register('adu.aduan_selesai', 'Aduan diselesaikan', NotificationTypes::BIASA,
            'Aduan {{no_rujukan}} telah ditutup',
            'Penutupan aduan {{no_rujukan}} ({{kategori}}) telah diluluskan dan aduan ditutup.', $f);
        $t->register('adu.penutupan_ditolak', 'Penutupan aduan ditolak', NotificationTypes::TINGGI,
            'Penutupan aduan {{no_rujukan}} ditolak',
            'Permohonan penutupan aduan {{no_rujukan}} tidak diluluskan: {{ulasan}}. Sila teruskan siasatan.', [...$f, 'ulasan']);
        $t->register(LawatanTapakAduan::TYPE, 'Peringatan lawatan tapak aduan', NotificationTypes::BIASA,
            'Lawatan tapak aduan {{no_rujukan}} pada {{tarikh_jadual}}',
            'Lawatan tapak bagi aduan {{no_rujukan}} dijadualkan pada {{tarikh_jadual}} ({{baki_hari}} hari lagi).', ['no_rujukan', 'tarikh_jadual', 'baki_hari']);
    }
}
