<?php

namespace App\Modules\Pjk;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adm\Contracts\SearchProviders;
use App\Modules\Dms\Contracts\RecordLinks;
use App\Modules\Dsh\Contracts\KpiProviders;
use App\Modules\Gis\Contracts\MapLayers;
use App\Modules\Ntf\Contracts\NotificationTypes;
use App\Modules\Ntf\Contracts\Reminders;
use App\Modules\Pjk\Console\HarianCommand;
use App\Modules\Pjk\Console\RekonsiliasiCommand;
use App\Modules\Pjk\Console\SampleCommand;
use App\Modules\Pjk\Console\SemakGatewayCommand;
use App\Modules\Pjk\Contracts\ESewa;
use App\Modules\Pjk\Contracts\GrpMais;
use App\Modules\Pjk\Contracts\PaymentGateway;
use App\Modules\Pjk\Integration\KontrakSearchProvider;
use App\Modules\Pjk\Integration\Kpi\KadarKutipan;
use App\Modules\Pjk\Integration\Kpi\KadarPenghunian;
use App\Modules\Pjk\Integration\Kpi\KontrakHampirTamat;
use App\Modules\Pjk\Integration\Kpi\Tunggakan;
use App\Modules\Pjk\Integration\MockESewa;
use App\Modules\Pjk\Integration\MockGrpMais;
use App\Modules\Pjk\Integration\MockPaymentGateway;
use App\Modules\Pjk\Integration\PajakanMapLayer;
use App\Modules\Pjk\Profile\PajakanSection;
use App\Modules\Pjk\Reminders\BayaranHampirTamatTempoh;
use App\Modules\Pjk\Reminders\KontrakTamatTempoh;
use App\Modules\Pjk\Services\AgihanService;
use App\Modules\Pjk\Services\BayaranService;
use App\Modules\Pjk\Services\DepositService;
use App\Modules\Pjk\Services\KontrakService;
use App\Modules\Pjk\Services\PelarasanService;
use App\Modules\Pjk\Services\PindaanService;
use App\Modules\Pjk\Services\SaluranPemerolehan;
use App\Modules\Pjk\Support\Aliran;
use App\Modules\Pro\Contracts\ProfileSections;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use App\Modules\Wfe\Contracts\SubjectLinks;
use App\Modules\Wfe\Contracts\WorkflowFlows;
use App\Modules\Wfe\Events\WorkflowCompleted;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

/**
 * MOD-PJK · Pajakan dan Kontrak (SRS Modul 5, SRS-PJK-01..28).
 *
 * Provides: Contracts\PaymentGateway (FPX/card; mock bound — MOD-PWS and
 * MOD-CKI may reuse it), Contracts\GrpMais and Contracts\ESewa (mock).
 * Uses: RBC (permissions, scope), AUD, ADM (parameters, search, integration
 * log, secrets), NTF (types, reminders), DMS (agreements, receipts, letters,
 * proofs), WFE (5 approval flows), PRO (LotRegistry, ProfileSections +
 * FinancialSeries), GIS (MapLayers), DSH (KpiProviders).
 */
class PjkServiceProvider extends ModuleServiceProvider
{
    protected string $code = 'pjk';

    public const PERMISSIONS = [
        'pjk.kontrak.lihat' => 'Lihat perjanjian sewaan / pajakan (Sulit) mengikut skop',
        'pjk.kontrak.cipta' => 'Daftar perjanjian baharu (draf)',
        'pjk.kontrak.kemas_kini' => 'Kemas kini draf, hantar untuk kelulusan, mohon pembaharuan / semakan kadar / pindaan / penamatan',
        'pjk.kontrak.eksport' => 'Eksport senarai perjanjian',
        'pjk.penyewa.lihat' => 'Lihat daftar penyewa (nombor pengenalan ditutup)',
        'pjk.penyewa.cipta' => 'Daftar penyewa',
        'pjk.penyewa.kemas_kini' => 'Kemas kini maklumat penyewa',
        'pjk.sulit.lihat' => 'Lihat nombor pengenalan / pendaftaran dan hubungan penuh penyewa (Sulit)',
        'pjk.rahsia.lihat' => 'Lihat nilai penilaian (Rahsia) dalam semakan kadar',
        'pjk.premis.lihat' => 'Lihat daftar premis dan katalog tawaran',
        'pjk.premis.cipta' => 'Daftar premis / tanah untuk disewa',
        'pjk.premis.kemas_kini' => 'Kemas kini premis dan status premis',
        'pjk.permohonan.lihat' => 'Lihat permohonan sewaan',
        'pjk.permohonan.cipta' => 'Daftar permohonan sewaan di kaunter',
        'pjk.permohonan.kemas_kini' => 'Semak permohonan, sediakan kontrak atau tolak',
        'pjk.lejar.lihat' => 'Lihat lejar, jadual caj, bayaran dan resit',
        'pjk.lejar.kemas_kini' => 'Mohon pelarasan, pembatalan atau pengecualian lejar',
        'pjk.lejar.eksport' => 'Jana penyata akaun penyewa',
        'pjk.bayaran.cipta' => 'Rekod bayaran luar talian dan mulakan bayaran dalam talian',
        'pjk.bayaran.lulus' => 'Sahkan bayaran luar talian (Bahagian Kewangan)',
        'pjk.deposit.kemas_kini' => 'Mohon penyelarasan dan pulangan deposit',
        'pjk.rekonsiliasi.lihat' => 'Lihat rekonsiliasi dan senarai sisihan',
        'pjk.rekonsiliasi.cipta' => 'Jalankan rekonsiliasi dan muat naik fail penyelesaian',
        'pjk.rekonsiliasi.kemas_kini' => 'Kemas kini tindakan ke atas sisihan',
        'pjk.agihan.lihat' => 'Lihat Dasar Agihan Manfaat dan kiraan',
        'pjk.agihan.cipta' => 'Kira dan simulasi agihan manfaat',
        'pjk.agihan.lulus' => 'Sahkan kiraan agihan manfaat',
        'pjk.dasar.kemas_kini' => 'Selenggara kadar Dasar Agihan Manfaat (versi baharu)',
        'pjk.templat.kemas_kini' => 'Selenggara templat surat',
        'pjk.surat.cipta' => 'Jana surat daripada templat',
        'pjk.laporan.lihat' => 'Lihat laporan dan dashboard pajakan',
        'pjk.laporan.eksport' => 'Eksport laporan pajakan (PDF / Excel)',
        'pjk.portal.cipta' => 'Hantar permohonan dan bayaran daripada Portal Hartanah Wakaf (akaun integrasi)',
    ];

    public function register(): void
    {
        $this->app->singletonIf(PaymentGateway::class, MockPaymentGateway::class);
        $this->app->singletonIf(GrpMais::class, MockGrpMais::class);
        $this->app->singletonIf(ESewa::class, MockESewa::class);
        $this->app->scoped(SaluranPemerolehan::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->call(function (PermissionRegistry $permissions, Parameters $parameters, NotificationTypes $types, WorkflowFlows $flows, SearchProviders $search, ProfileSections $profile) {
            $permissions->register('pjk', 'Pajakan dan Kontrak', self::PERMISSIONS);
            $this->declareParameters($parameters);
            $this->declareNotificationTypes($types);
            foreach (Aliran::FLOWS as $code => [$name, $subject, $fields]) {
                $flows->declare($code, $name, 'pjk', $subject, $fields);
            }
            $search->register(KontrakSearchProvider::class);
            $profile->register(PajakanSection::class);
        });

        $this->callAfterResolving(MapLayers::class, fn (MapLayers $layers) => $layers->register(PajakanMapLayer::class));
        $this->callAfterResolving(KpiProviders::class, function (KpiProviders $kpi) {
            foreach ([KadarKutipan::class, Tunggakan::class, KadarPenghunian::class, KontrakHampirTamat::class] as $provider) {
                $kpi->register($provider);
            }
        });
        $this->callAfterResolving(Reminders::class, function (Reminders $reminders) {
            $reminders->register(KontrakTamatTempoh::class);
            $reminders->register(BayaranHampirTamatTempoh::class);
        });
        $this->callAfterResolving(SubjectLinks::class, function (SubjectLinks $links) {
            // Amendment / adjustment / deposit flows pass `pautan` (the contract page) in their start() context.
            $links->register('kontrak_pajakan', '/admin/pjk/kontrak/{id}');
            $links->register('dasar_agihan_manfaat', '/admin/pjk/agihan?dasar={id}');
        });
        $this->callAfterResolving(RecordLinks::class, fn (RecordLinks $links) => $links->register('kontrak_pajakan', 'Perjanjian Sewaan / Pajakan', '/admin/pjk/kontrak/{id}', 'pjk.kontrak.lihat'));

        Event::listen(WorkflowCompleted::class, [KontrakService::class, 'onWorkflowCompleted']);
        Event::listen(WorkflowCompleted::class, [PindaanService::class, 'onWorkflowCompleted']);
        Event::listen(WorkflowCompleted::class, [PelarasanService::class, 'onWorkflowCompleted']);
        Event::listen(WorkflowCompleted::class, [DepositService::class, 'onWorkflowCompleted']);
        Event::listen(WorkflowCompleted::class, [AgihanService::class, 'onWorkflowCompleted']);

        if ($this->app->runningInConsole()) {
            $this->commands([HarianCommand::class, SemakGatewayCommand::class, RekonsiliasiCommand::class, SampleCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('pjk:harian')->dailyAt('00:30')->withoutOverlapping()->onOneServer();
            $schedule->command('pjk:semak-gateway')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
            $schedule->command('pjk:rekonsiliasi --sumber=semua')->dailyAt('06:00')->withoutOverlapping()->onOneServer();
        });
    }

    private function declareParameters(Parameters $p): void
    {
        $p->declare('pjk.hari_tempoh_bayar', 'int', 14, 'Tempoh bayar lalai (hari) selepas tarikh caj');
        $p->declare('pjk.hari_hampir_tamat', 'int', 90, 'Kontrak bertukar status "hampir tamat" dalam tempoh (hari) sebelum tarikh tamat');
        $p->declare('pjk.keutamaan_agihan', 'json', BayaranService::KEUTAMAAN_LALAI, 'Susunan keutamaan agihan bayaran mengikut kod caj (SRS-PJK-20)');
        $p->declare('pjk.penalti_peratus_bulanan', 'string', '1.00', 'Kadar penalti lewat bayar sebulan (%) atas sewa tertunggak', critical: true);
        $p->declare('pjk.penalti_hari_tangguh', 'int', 30, 'Tempoh tangguh (hari) selepas tarikh akhir sebelum penalti dikenakan');
        $p->declare('pjk.ambang_tunggakan_hari', 'json', [30, 60, 90], 'Ambang umur tunggakan (hari) untuk notifikasi tunggakan');
        $p->declare('pjk.ambang_hari_kosong', 'int', 90, 'Premis kosong melebihi ambang (hari) disenaraikan dalam dashboard penghunian');
        $p->declare('pjk.gateway_minit_semak', 'int', 15, 'Transaksi gateway tanpa panggilan balik disemak status selepas (minit)');
        $p->declare('pjk.gateway_minit_tamat_masa', 'int', 60, 'Transaksi gateway yang tidak selesai ditanda tamat masa selepas (minit)');
        $p->declare('pjk.sasaran_hasil_peratus', 'string', '5.00', 'Sasaran hasil sewa tahunan (% nilai pasaran) bagi cadangan kadar semakan');
        $p->declare('pjk.peta_saluran_perolehan', 'json', SaluranPemerolehan::PETA_LALAI, 'Pemetaan kod saluran MOD-PER kepada status perolehan wakaf / belian (SRS-PJK-23)');
    }

    private function declareNotificationTypes(NotificationTypes $types): void
    {
        $types->register(KontrakTamatTempoh::TYPE, 'Kontrak pajakan hampir tamat tempoh', NotificationTypes::TINGGI,
            'Kontrak {{no_kontrak}} tamat dalam {{baki_hari}} hari',
            'Perjanjian {{no_kontrak}} bagi {{nama_premis}} (daerah {{kod_daerah}}) akan tamat pada {{tarikh_tamat}} ({{baki_hari}} hari lagi). Sila mulakan proses pembaharuan atau penamatan.',
            ['no_kontrak', 'nama_premis', 'kod_daerah', 'tarikh_tamat', 'baki_hari']);
        $types->register(BayaranHampirTamatTempoh::TYPE, 'Bayaran sewa perlu dijelaskan', NotificationTypes::BIASA,
            'Bayaran kontrak {{no_kontrak}} perlu dijelaskan dalam {{baki_hari}} hari',
            'Caj bagi kontrak {{no_kontrak}} berjumlah {{amaun}} perlu dijelaskan sebelum {{tarikh_akhir}}.',
            ['no_kontrak', 'tarikh_akhir', 'amaun', 'baki_hari']);
        $types->register('pjk.tunggakan', 'Tunggakan sewa', NotificationTypes::TINGGI,
            'Tunggakan kontrak {{no_kontrak}} melebihi {{hari}} hari',
            'Kontrak {{no_kontrak}} (daerah {{kod_daerah}}) mempunyai tunggakan {{amaun}} yang melebihi {{hari}} hari. Sila ambil tindakan susulan.',
            ['no_kontrak', 'kod_daerah', 'amaun', 'hari']);
        $types->register('pjk.keputusan_kelulusan', 'Keputusan kelulusan pajakan', NotificationTypes::BIASA,
            '{{perkara}} {{no_kontrak}}: {{keputusan}}',
            'Permohonan "{{perkara}}" bagi kontrak {{no_kontrak}} telah {{keputusan}}.',
            ['perkara', 'no_kontrak', 'keputusan']);
        $types->register('pjk.sisihan_rekonsiliasi', 'Sisihan rekonsiliasi bayaran', NotificationTypes::TINGGI,
            'Rekonsiliasi {{sumber}} {{tarikh}}: {{bil_sisihan}} sisihan',
            'Rekonsiliasi {{sumber}} bagi {{tarikh}} menemui {{bil_sisihan}} sisihan yang memerlukan tindakan Bahagian Kewangan.',
            ['sumber', 'tarikh', 'bil_sisihan'], true);
    }
}
