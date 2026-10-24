<?php

namespace App\Modules\Kes;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adm\Contracts\SearchProviders;
use App\Modules\Dms\Contracts\RecordLinks;
use App\Modules\Dms\Events\DocumentStored;
use App\Modules\Dsh\Contracts\KpiProviders;
use App\Modules\Gis\Contracts\MapLayers;
use App\Modules\Kes\Console\SampleCommand;
use App\Modules\Kes\Console\SemakEskalasiCommand;
use App\Modules\Kes\Contracts\AttorneyGeneralChambers;
use App\Modules\Kes\Contracts\CourtFilingSystem;
use App\Modules\Kes\Contracts\LegalCases;
use App\Modules\Kes\Contracts\MfaAssurance;
use App\Modules\Kes\Gis\KesMahkamahLayer;
use App\Modules\Kes\Http\Middleware\PastikanAksesKes;
use App\Modules\Kes\Integrasi\MockPeguamNegara;
use App\Modules\Kes\Integrasi\MockSistemMahkamah;
use App\Modules\Kes\Kpi\KesAktif;
use App\Modules\Kes\Kpi\KesMengikutJenis;
use App\Modules\Kes\Kpi\KosGuamanTahunIni;
use App\Modules\Kes\Kpi\ProsidingHampir;
use App\Modules\Kes\Profile\KesMahkamahSection;
use App\Modules\Kes\Reminders\PeringatanProsiding;
use App\Modules\Kes\Reminders\PeringatanTarikhPenting;
use App\Modules\Kes\Risiko\IsyaratPerundangan;
use App\Modules\Kes\Services\AliranKes;
use App\Modules\Kes\Services\DokumenService;
use App\Modules\Kes\Services\KesLegalCaseIntake;
use App\Modules\Kes\Services\KesLegalCases;
use App\Modules\Kes\Services\KesSearchProvider;
use App\Modules\Kes\Services\Rujukan;
use App\Modules\Kes\Support\Akses;
use App\Modules\Kes\Support\Aliran;
use App\Modules\Kes\Support\Penerima;
use App\Modules\Kes\Support\SsoMfaAssurance;
use App\Modules\Ntf\Contracts\NotificationTypes;
use App\Modules\Ntf\Contracts\Reminders;
use App\Modules\Pro\Contracts\ProfileSections;
use App\Modules\Ptn\Contracts\LegalCaseIntake;
use App\Modules\Ptn\Contracts\RiskSignals;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use App\Modules\Wfe\Contracts\SubjectLinks;
use App\Modules\Wfe\Contracts\WorkflowFlows;
use App\Modules\Wfe\Events\WorkflowCompleted;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;

/**
 * MOD-KES · Kes Mahkamah dan Perundangan (SRS-KES-01..24). Rahsia module:
 * every case record / document needs kes.kes.lihat + the explicit grant
 * kes.rahsia.lihat + MFA, and every view is logged. Implements MOD-PTN's
 * LegalCaseIntake (replacing its stub) and its own contracts LegalCases
 * (for ADU / HKM / others), MfaAssurance, CourtFilingSystem and
 * AttorneyGeneralChambers (local mocks). Uses RBC, AUD, ADM, NTF, DMS, WFE,
 * PRO, HKM, GIS, DSH, PTN and CKI (GRP) only through their contracts.
 */
class KesServiceProvider extends ModuleServiceProvider
{
    protected string $code = 'kes';

    public const PERMISSIONS = [
        'kes.kes.lihat' => 'Lihat daftar dan rekod kes mahkamah (juga memerlukan kes.rahsia.lihat dan MFA)',
        'kes.kes.cipta' => 'Daftar kes mahkamah baharu',
        'kes.kes.kemas_kini' => 'Kemas kini kes: pihak, peguam, lot, prosiding, dokumen, kos, keputusan, rayuan, penyelesaian',
        'kes.kes.lulus' => 'Ketua Bahagian Perundangan: tugaskan / tukar pegawai kes, arkib kes, terima eskalasi dan peringatan',
        'kes.rahsia.lihat' => 'Capai rekod dan dokumen kes berklasifikasi Rahsia (diberi secara eksplisit sahaja)',
        'kes.pihak.lihat' => 'Dedah butiran peribadi penuh pihak kes (setiap paparan dilog)',
        'kes.laporan.lihat' => 'Lihat dashboard, kalendar, laporan dan pendedahan perundangan',
        'kes.laporan.eksport' => 'Eksport laporan kes (PDF / Excel / CSV)',
        'kes.log_capaian.lihat' => 'Lihat log capaian rekod dan dokumen kes',
        'kes.konfigurasi.lihat' => 'Lihat konfigurasi jenis kes, peringkat status dan panel peguam',
        'kes.konfigurasi.kemas_kini' => 'Ubah konfigurasi jenis kes, peringkat status dan daftar panel peguam',
    ];

    public function register(): void
    {
        $this->app->scoped(Rujukan::class);
        $this->app->scoped(Akses::class);
        $this->app->scoped(Penerima::class);
        $this->app->bind(LegalCases::class, KesLegalCases::class);
        // Overrides MOD-PTN's "not available" stub (PTN binds with bindIf).
        $this->app->bind(LegalCaseIntake::class, KesLegalCaseIntake::class);
        $this->app->bindIf(MfaAssurance::class, SsoMfaAssurance::class);
        $this->app->bindIf(CourtFilingSystem::class, MockSistemMahkamah::class);
        $this->app->bindIf(AttorneyGeneralChambers::class, MockPeguamNegara::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->make(Router::class)->aliasMiddleware('kes.akses', PastikanAksesKes::class);

        $this->app->call(function (PermissionRegistry $permissions, Parameters $parameters, NotificationTypes $types, WorkflowFlows $flows, SubjectLinks $links, SearchProviders $search, ProfileSections $profile) {
            $permissions->register('kes', 'Kes Mahkamah dan Perundangan', self::PERMISSIONS);
            $this->declareParameters($parameters);
            $this->registerNotificationTypes($types);
            foreach (Aliran::FLOWS as $code => [$name, $subject]) {
                $flows->declare($code, $name, 'kes', $subject, Aliran::KONTEKS);
            }
            $links->register('kes_mahkamah', '/admin/kes/kes/{id}');
            $search->register(KesSearchProvider::class);
            $profile->register(KesMahkamahSection::class);
        });

        $this->callAfterResolving(MapLayers::class, fn (MapLayers $layers) => $layers->register(KesMahkamahLayer::class));
        $this->callAfterResolving(KpiProviders::class, function (KpiProviders $kpi) {
            foreach ([KesAktif::class, ProsidingHampir::class, KosGuamanTahunIni::class, KesMengikutJenis::class] as $provider) {
                $kpi->register($provider);
            }
        });
        $this->callAfterResolving(RiskSignals::class, fn (RiskSignals $s) => $s->register(IsyaratPerundangan::class));
        $this->callAfterResolving(RecordLinks::class, fn (RecordLinks $l) => $l->register('kes_mahkamah', 'Kes Mahkamah', '/admin/kes/kes/{id}', 'kes.rahsia.lihat'));
        if ($this->app->bound(Reminders::class)) {
            $reminders = $this->app->make(Reminders::class);
            $reminders->register(PeringatanProsiding::class);
            $reminders->register(PeringatanTarikhPenting::class);
        }

        Event::listen(WorkflowCompleted::class, [AliranKes::class, 'handle']);
        Event::listen(DocumentStored::class, [DokumenService::class, 'handle']);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('kes:semak-eskalasi')->dailyAt('07:30')->withoutOverlapping()->name('kes:semak-eskalasi');
        });

        if ($this->app->runningInConsole()) {
            $this->commands([SemakEskalasiCommand::class, SampleCommand::class]);
        }
    }

    private function declareParameters(Parameters $p): void
    {
        $p->declare('kes.mfa_wajib', 'bool', true, 'Wajibkan MFA (sesi SSO MAIS) bagi setiap capaian rekod dan dokumen kes (SRS-KES-14)', true);
        $p->declare('kes.hari_tanpa_tarikh_berikutnya', 'int', 7, 'Eskalasi kepada Ketua Bahagian jika tiada tarikh prosiding berikutnya selepas N hari (SRS-KES-11)');
        $p->declare('kes.kategori_dms', 'string', 'lain', 'Kod kategori MOD-DMS bagi dokumen perundangan (klasifikasi sentiasa Rahsia)');
        $p->declare('kes.kod_vot_grp', 'string', 'B-PERUNDANGAN', 'Kod vot GRP MAIS bagi bayaran fi peguam dan kos mahkamah');
        $p->declare('kes.mock_mahkamah_gagal', 'bool', false, 'Adapter mock sistem mahkamah / Peguam Negara: simulasi gagal (sandaran manual)');
    }

    private function registerNotificationTypes(NotificationTypes $t): void
    {
        $t->register(PeringatanProsiding::TYPE, 'Peringatan tarikh prosiding kes mahkamah', NotificationTypes::TINGGI,
            'Prosiding {{jenis}} kes {{no_rujukan}} dalam {{baki_hari}} hari',
            'Peringatan: {{jenis}} bagi kes {{no_rujukan}} dijadualkan pada {{tarikh}} ({{baki_hari}} hari lagi). Sila pastikan persediaan dan dokumen lengkap.',
            ['no_rujukan', 'jenis', 'tarikh', 'baki_hari'], true);
        $t->register(PeringatanTarikhPenting::TYPE, 'Peringatan tarikh penting kes (pengemukaan dokumen / had)', NotificationTypes::TINGGI,
            '{{jenis}} kes {{no_rujukan}} dalam {{baki_hari}} hari',
            'Peringatan: {{jenis}} bagi kes {{no_rujukan}} perlu diselesaikan pada atau sebelum {{tarikh}} ({{baki_hari}} hari lagi).',
            ['no_rujukan', 'jenis', 'tarikh', 'baki_hari'], true);
        $t->register('kes.eskalasi_tiada_tarikh', 'Eskalasi: kes tanpa tarikh prosiding berikutnya', NotificationTypes::KRITIKAL,
            'Eskalasi: kes {{no_rujukan}} tiada tarikh prosiding berikutnya',
            'Kes {{no_rujukan}} tidak mempunyai tarikh prosiding berikutnya sejak prosiding pada {{tarikh_prosiding}} ({{hari}} hari). Sila semak dengan pegawai kes.',
            ['no_rujukan', 'tarikh_prosiding', 'hari'], true);
        $t->register('kes.kes_ditugaskan', 'Kes mahkamah ditugaskan kepada anda', NotificationTypes::TINGGI,
            'Kes {{no_rujukan}} ditugaskan kepada anda',
            'Kes {{no_rujukan}} ({{jenis}}) telah ditugaskan kepada anda sebagai pegawai bertanggungjawab.', ['no_rujukan', 'jenis'], true);
        $t->register('kes.tindakan_susulan', 'Tindakan susulan keputusan mahkamah', NotificationTypes::TINGGI,
            'Tindakan susulan kes {{no_rujukan}}: {{tindakan}}',
            'Tindakan susulan "{{tindakan}}" bagi kes {{no_rujukan}} telah dicetuskan. Tarikh sasaran: {{tarikh_sasaran}}.', ['no_rujukan', 'tindakan', 'tarikh_sasaran']);
    }
}
