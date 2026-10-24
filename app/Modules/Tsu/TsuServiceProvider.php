<?php

namespace App\Modules\Tsu;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adm\Contracts\SearchProviders;
use App\Modules\Dms\Contracts\RecordLinks;
use App\Modules\Dsh\Contracts\KpiProviders;
use App\Modules\Gis\Contracts\MapLayers;
use App\Modules\Ntf\Contracts\NotificationTypes;
use App\Modules\Ntf\Contracts\Reminders;
use App\Modules\Pro\Contracts\ProfileSections;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use App\Modules\Tsu\Console\SampleCommand;
use App\Modules\Tsu\Console\SemakTempohCommand;
use App\Modules\Tsu\Contracts\LotChanges;
use App\Modules\Tsu\Contracts\LotRecordTransfers;
use App\Modules\Tsu\Contracts\PejabatTanahGateway;
use App\Modules\Tsu\Gis\HasilUkurLayer;
use App\Modules\Tsu\Gis\UrusanTanahLayer;
use App\Modules\Tsu\Integrasi\PejabatTanahMock;
use App\Modules\Tsu\Kpi\LewatSasaran;
use App\Modules\Tsu\Kpi\PurataTempoh;
use App\Modules\Tsu\Kpi\UrusanAktif;
use App\Modules\Tsu\Profile\UrusanTanahSection;
use App\Modules\Tsu\Reminders\BayaranHampirTempoh;
use App\Modules\Tsu\Reminders\DokumenHampirLuput;
use App\Modules\Tsu\Reminders\PeringkatHampirSasaran;
use App\Modules\Tsu\Reminders\SusulanPtd;
use App\Modules\Tsu\Services\AliranTsuListener;
use App\Modules\Tsu\Services\DaftarPemindahan;
use App\Modules\Tsu\Services\UrusanSearchProvider;
use App\Modules\Tsu\Support\AliranTsu;
use App\Modules\Tsu\Support\LotChangesFallback;
use App\Modules\Wfe\Contracts\WorkflowFlows;
use App\Modules\Wfe\Events\StepDecided;
use App\Modules\Wfe\Events\WorkflowCompleted;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

/**
 * MOD-TSU · Tukar Syarat, Pelupusan dan Ukur (SRS-TSU-01..27). Implements its
 * own contracts (LotRecordTransfers registry, PejabatTanahGateway adapter +
 * mock) and binds a stand-in for the one MOD-PRO should own (LotChanges);
 * uses RBC, AUD, ADM, NTF, DMS, WFE, PRO, HKM, PTN, GIS and DSH only through
 * their contracts / events.
 */
class TsuServiceProvider extends ModuleServiceProvider
{
    protected string $code = 'tsu';

    public const PERMISSIONS = [
        'tsu.urusan.lihat' => 'Lihat urusan tanah (tukar syarat, pelupusan, pecah sempadan, ukur…) dalam skop',
        'tsu.urusan.cipta' => 'Daftar urusan tanah baharu',
        'tsu.urusan.kemas_kini' => 'Kemas kini urusan: dokumen, selesai peringkat, tutup / batal urusan',
        'tsu.kos.lihat' => 'Lihat kos urusan dan graf kos lot',
        'tsu.kewangan.kemas_kini' => 'Bahagian Kewangan: rekod dan ubah kos, invois, bayaran dan resit urusan',
        'tsu.rahsia.lihat' => 'Lihat nilai laporan penilaian JPPH (Rahsia)',
        'tsu.rahsia.kemas_kini' => 'Muat naik laporan penilaian JPPH dan nilainya (Rahsia)',
        'tsu.ptd.kemas_kini' => 'Hantar ke Pejabat Tanah dan kemas kini status (API / manual)',
        'tsu.panel.lihat' => 'Lihat panel jurukur berlesen',
        'tsu.panel.kemas_kini' => 'Urus panel jurukur berlesen',
        'tsu.sebutharga.lihat' => 'Lihat sebut harga kerja ukur dan jadual perbandingan (selepas tarikh tutup)',
        'tsu.sebutharga.cipta' => 'Pegawai perolehan: jemput sebut harga, rekod tawaran, rekod lantikan jurukur',
        'tsu.sebutharga.kemas_kini' => 'Penilai sebut harga: pengesyoran dan justifikasi pemilihan',
        'tsu.ukur.lihat' => 'Lihat lantikan, kemajuan dan hasil ukur',
        'tsu.ukur.kemas_kini' => 'Kemas kini kemajuan kerja ukur (pegawai MAIS)',
        'tsu.jurukur.kemas_kini' => 'Jurukur berlesen luar: kemas kini kemajuan lantikan sendiri sahaja',
        'tsu.hasil_ukur.cipta' => 'GIS Specialist: import hasil ukur (DXF / Shapefile / GeoJSON)',
        'tsu.hasil_ukur.lulus' => 'GIS Specialist: terima / tolak hasil ukur',
        'tsu.pemindahan.cipta' => 'Petakan dan hantar pemindahan rekod ke lot baharu',
        'tsu.templat.lihat' => 'Lihat templat proses urusan',
        'tsu.templat.kemas_kini' => 'Pentadbir: ubah templat proses (peringkat, tempoh, dokumen wajib, kategori kos)',
        'tsu.laporan.lihat' => 'Lihat laporan urusan tanah',
        'tsu.laporan.eksport' => 'Eksport laporan urusan tanah',
        'tsu.peta.lihat' => 'Lihat lapisan GIS urusan tanah dan hasil ukur',
    ];

    public function register(): void
    {
        $this->app->singleton(DaftarPemindahan::class);
        $this->app->alias(DaftarPemindahan::class, LotRecordTransfers::class);
        $this->app->bindIf(LotChanges::class, LotChangesFallback::class);
        $this->app->bindIf(PejabatTanahGateway::class, PejabatTanahMock::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->call(function (PermissionRegistry $permissions, Parameters $parameters, NotificationTypes $types, WorkflowFlows $flows, SearchProviders $search, ProfileSections $profile) {
            $permissions->register('tsu', 'Tukar Syarat, Pelupusan dan Ukur', self::PERMISSIONS);
            $this->declareParameters($parameters);
            $this->registerNotificationTypes($types);

            $flows->declare(AliranTsu::URUSAN, 'Kelulusan dalaman urusan tanah', 'tsu', AliranTsu::SUBJEK_PERINGKAT, ['kod_daerah', 'jenis_urusan', 'bahagian_pemilik']);
            $flows->declare(AliranTsu::PELUPUSAN, 'Kelulusan pelupusan tanah (aliran tertinggi, MFA)', 'tsu', AliranTsu::SUBJEK_PERINGKAT, ['kod_daerah', 'bahagian_pemilik', 'kaedah_pelupusan']);
            $flows->declare(AliranTsu::LANTIKAN, 'Kelulusan pemilihan jurukur berlesen', 'tsu', AliranTsu::SUBJEK_SEBUTHARGA, ['kod_daerah', 'nilai']);
            $flows->declare(AliranTsu::PEMINDAHAN, 'Pengesahan dua peringkat pemindahan rekod lot', 'tsu', AliranTsu::SUBJEK_PEMINDAHAN, ['kod_daerah', 'bilangan']);

            $search->register(UrusanSearchProvider::class);
            $profile->register(UrusanTanahSection::class);
        });

        $this->callAfterResolving(MapLayers::class, function (MapLayers $layers) {
            $layers->register(UrusanTanahLayer::class);
            $layers->register(HasilUkurLayer::class);
        });
        $this->callAfterResolving(KpiProviders::class, function (KpiProviders $kpi) {
            foreach ([UrusanAktif::class, PurataTempoh::class, LewatSasaran::class] as $p) {
                $kpi->register($p);
            }
        });
        $this->callAfterResolving(RecordLinks::class, fn (RecordLinks $l) => $l->register('urusan_tanah', 'Urusan Tanah (MOD-TSU)', '/admin/tsu/urusan/{id}', 'tsu.urusan.lihat'));
        if ($this->app->bound(Reminders::class)) {
            $r = $this->app->make(Reminders::class);
            foreach ([BayaranHampirTempoh::class, DokumenHampirLuput::class, PeringkatHampirSasaran::class, SusulanPtd::class] as $source) {
                $r->register($source);
            }
        }

        Event::listen(WorkflowCompleted::class, [AliranTsuListener::class, 'completed']);
        Event::listen(StepDecided::class, [AliranTsuListener::class, 'step']);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('tsu:semak-tempoh')->dailyAt('07:15')->withoutOverlapping()->name('tsu:semak-tempoh');
        });

        if ($this->app->runningInConsole()) {
            $this->commands([SemakTempohCommand::class, SampleCommand::class]);
        }
    }

    private function declareParameters(Parameters $p): void
    {
        $p->declare('tsu.toleransi_keluasan_peratus', 'int', 5, 'Toleransi sisihan keluasan hasil ukur berbanding keluasan dokumen rasmi (%) — SRS-TSU-17');
        $p->declare('tsu.tempoh_sah_penilaian_bulan', 'int', 12, 'Tempoh sah laporan penilaian JPPH (bulan) jika tarikh luput tidak dinyatakan — SRS-TSU-05');
        $p->declare('tsu.ambang_lewat_ukur_peratus', 'int', 10, 'Kerja ukur dikira lewat jika peratus siap terkebelakang melebihi ambang ini (%) — SRS-TSU-11');
        $p->declare('tsu.hari_eskalasi_ketua', 'int', 7, 'Hari selepas tarikh sasaran peringkat sebelum dieskalasi kepada ketua bahagian');
        $p->declare('tsu.hari_susulan_manual', 'int', 14, 'Hari sebelum peringatan susulan bagi kemas kini status Pejabat Tanah secara manual — SRS-TSU-22');
        $p->declare('tsu.daerah_liputan_api', 'json', ['1001', '1002', '1008'], 'Kod daerah yang diliputi API berpusat Pejabat Tanah — SRS-TSU-22');
        $p->declare('tsu.mock_ptd_hari_per_status', 'int', 15, 'Mock Pejabat Tanah: hari bagi setiap perubahan status');
        $p->declare('tsu.peringkat_wfe_mfa', 'json', ['kelulusan_ketua_bahagian', AliranTsu::PERINGKAT_TERTINGGI], 'Kod peringkat MOD-WFE yang dikonfigurasi perlu_mfa (penunjuk MFA dalam sejarah keputusan)', true);
        $p->declare('tsu.pemberat_sebutharga', 'json', ['harga' => 60, 'tempoh' => 25, 'pengalaman' => 15], 'Pemberat markah jadual perbandingan sebut harga (harga / tempoh siap / pengalaman) — SRS-TSU-09');
        $p->declare('tsu.minimum_jurukur_dijemput', 'int', 3, 'Bilangan minimum jurukur panel dijemput bagi setiap sebut harga');
    }

    private function registerNotificationTypes(NotificationTypes $t): void
    {
        $t->register('tsu.urusan_baharu', 'Urusan tanah baharu diumpukkan', NotificationTypes::BIASA,
            'Urusan {{jenis}} {{no_rujukan}}', 'Urusan {{jenis}} {{no_rujukan}} (daerah {{daerah}}) telah didaftarkan dan diumpukkan kepada anda.', ['no_rujukan', 'jenis', 'daerah']);
        $t->register(PeringkatHampirSasaran::TYPE, 'Peringkat urusan tanah hampir tarikh sasaran', NotificationTypes::BIASA,
            'Peringkat "{{peringkat}}" ({{no_rujukan}}) — {{baki_hari}} hari lagi', 'Peringkat "{{peringkat}}" bagi urusan {{no_rujukan}} (pihak: {{pihak}}) perlu disiapkan sebelum {{tarikh_sasaran}}.', ['no_rujukan', 'peringkat', 'pihak', 'tarikh_sasaran', 'baki_hari']);
        $t->register('tsu.peringkat_lewat', 'Peringkat urusan tanah melepasi sasaran', NotificationTypes::TINGGI,
            'LEWAT: {{peringkat}} ({{no_rujukan}})', 'Peringkat "{{peringkat}}" bagi urusan {{no_rujukan}} (pihak: {{pihak}}) telah melepasi tarikh sasaran {{tarikh_sasaran}} ({{hari_lewat}} hari).', ['no_rujukan', 'peringkat', 'pihak', 'tarikh_sasaran', 'hari_lewat']);
        $t->register('tsu.peringkat_eskalasi', 'Eskalasi urusan tanah kepada ketua bahagian', NotificationTypes::TINGGI,
            'ESKALASI: {{no_rujukan}} lewat {{hari_lewat}} hari', 'Urusan {{no_rujukan}} tertangguh di peringkat "{{peringkat}}" (pihak: {{pihak}}) sejak {{tarikh_sasaran}} ({{hari_lewat}} hari). Dimaklumkan kepada pegawai bertanggungjawab dan ketua bahagian.', ['no_rujukan', 'peringkat', 'pihak', 'tarikh_sasaran', 'hari_lewat'], true);
        $t->register(BayaranHampirTempoh::TYPE, 'Bayaran premium / fi urusan tanah hampir tempoh', NotificationTypes::TINGGI,
            'Bayaran {{kategori}} ({{no_rujukan}}) — {{baki_hari}} hari lagi', 'Bayaran {{kategori}} bagi urusan {{no_rujukan}} perlu dijelaskan sebelum {{tarikh_akhir}}.', ['no_rujukan', 'kategori', 'tarikh_akhir', 'baki_hari']);
        $t->register(DokumenHampirLuput::TYPE, 'Dokumen urusan tanah hampir luput', NotificationTypes::BIASA,
            '{{dokumen}} ({{no_rujukan}}) luput {{tarikh_luput}}', 'Dokumen "{{dokumen}}" bagi urusan {{no_rujukan}} akan luput pada {{tarikh_luput}} ({{baki_hari}} hari lagi). Dapatkan dokumen terkini.', ['no_rujukan', 'dokumen', 'tarikh_luput', 'baki_hari']);
        $t->register(SusulanPtd::TYPE, 'Susulan status Pejabat Tanah (kemas kini manual)', NotificationTypes::BIASA,
            'Susulan Pejabat Tanah: {{no_rujukan}}', 'Sila buat susulan dengan {{pejabat_tanah}} bagi urusan {{no_rujukan}} (status terakhir: {{status}}) dan kemas kini status menjelang {{tarikh_susulan}}.', ['no_rujukan', 'status', 'pejabat_tanah', 'tarikh_susulan']);
        $t->register('tsu.status_ptd_berubah', 'Status permohonan Pejabat Tanah berubah', NotificationTypes::MAKLUMAT,
            'Status PTD {{no_rujukan}}: {{status}}', 'Status urusan {{no_rujukan}} di Pejabat Tanah kini "{{status}}" ({{sumber}}).', ['no_rujukan', 'status', 'sumber']);
        $t->register('tsu.ukur_lewat', 'Kerja ukur terkebelakang daripada jadual', NotificationTypes::TINGGI,
            'Kerja ukur lewat: {{no_rujukan}}', 'Kemajuan kerja ukur {{jurukur}} bagi urusan {{no_rujukan}} ialah {{peratus_siap}}% berbanding {{peratus_dijangka}}% dijangka (siap dijangka {{tarikh_jangka_siap}}).', ['no_rujukan', 'jurukur', 'peratus_siap', 'peratus_dijangka', 'tarikh_jangka_siap']);
        $t->register('tsu.hasil_ukur_untuk_pengesahan', 'Hasil ukur menunggu pengesahan pelan', NotificationTypes::TINGGI,
            'Hasil ukur {{no_rujukan}} menunggu pengesahan', '{{bilangan}} hasil ukur bagi urusan {{no_rujukan}} telah diimport dan menunggu pengesahan GIS Specialist.', ['no_rujukan', 'bilangan']);
        $t->register('tsu.keputusan_kelulusan', 'Keputusan kelulusan urusan tanah', NotificationTypes::TINGGI,
            'Keputusan: {{peringkat}} ({{no_rujukan}})', 'Keputusan bagi "{{peringkat}}" urusan {{no_rujukan}}: {{keputusan}}.', ['no_rujukan', 'peringkat', 'keputusan']);
    }
}
