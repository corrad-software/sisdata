<?php

namespace App\Modules\Ptn;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adm\Contracts\SearchProviders;
use App\Modules\Dms\Contracts\RecordLinks;
use App\Modules\Dsh\Contracts\KpiProviders;
use App\Modules\Gis\Contracts\MapLayers;
use App\Modules\Ntf\Contracts\NotificationTypes;
use App\Modules\Ntf\Contracts\Reminders;
use App\Modules\Pro\Contracts\ProfileSections;
use App\Modules\Ptn\Console\JanaJadualCommand;
use App\Modules\Ptn\Console\KiraRisikoCommand;
use App\Modules\Ptn\Console\SampleCommand;
use App\Modules\Ptn\Console\SemakTertunggakCommand;
use App\Modules\Ptn\Contracts\LegalCaseIntake;
use App\Modules\Ptn\Contracts\LotMasterUpdates;
use App\Modules\Ptn\Contracts\LotRiskStatus;
use App\Modules\Ptn\Contracts\MaintenanceNeeds;
use App\Modules\Ptn\Contracts\RiskSignals;
use App\Modules\Ptn\Gis\PemantauanLayer;
use App\Modules\Ptn\Gis\RisikoLotLayer;
use App\Modules\Ptn\Kpi\LawatanIkutJadual;
use App\Modules\Ptn\Kpi\LotBelumDiperiksa;
use App\Modules\Ptn\Kpi\LotRisikoMerah;
use App\Modules\Ptn\Kpi\PenemuanTerbuka;
use App\Modules\Ptn\Profile\PemantauanSection;
use App\Modules\Ptn\Profile\RisikoSection;
use App\Modules\Ptn\Reminders\LawatanDijadualkan;
use App\Modules\Ptn\Reminders\TindakanHampirTamat;
use App\Modules\Ptn\Services\AliranPtnListener;
use App\Modules\Ptn\Services\DaftarIsyaratRisiko;
use App\Modules\Ptn\Services\LawatanSearchProvider;
use App\Modules\Ptn\Services\PtnLotRiskStatus;
use App\Modules\Ptn\Services\PtnMaintenanceNeeds;
use App\Modules\Ptn\Support\AliranPtn;
use App\Modules\Ptn\Support\KodWarna;
use App\Modules\Ptn\Support\LotMasterFallback;
use App\Modules\Ptn\Support\Rujukan;
use App\Modules\Ptn\Support\TiadaModulKes;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use App\Modules\Wfe\Contracts\WorkflowFlows;
use App\Modules\Wfe\Events\WorkflowCompleted;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

/**
 * MOD-PTN · Pemantauan Tanah (SRS-PTN-01..24). Implements its own contracts
 * (RiskSignals, LotRiskStatus, MaintenanceNeeds) and binds fallbacks for the
 * ones other modules will own (LotMasterUpdates → MOD-PRO, LegalCaseIntake →
 * MOD-KES); uses RBC, AUD, ADM, NTF, DMS, WFE, PRO, GIS, DSH and ADU only
 * through their contracts.
 */
class PtnServiceProvider extends ModuleServiceProvider
{
    protected string $code = 'ptn';

    public const PERMISSIONS = [
        'ptn.jadual.lihat' => 'Lihat perancangan dan jadual pemantauan',
        'ptn.jadual.cipta' => 'Wujudkan jadual / jana jadual tahunan automatik',
        'ptn.jadual.kemas_kini' => 'Ubah, batal dan agih tugasan lawatan kepada pegawai lapangan',
        'ptn.lawatan.lihat' => 'Lihat semua rekod lawatan dalam skop (penyelia / pegawai daerah)',
        'ptn.lawatan.cipta' => 'Rekod lawatan tanpa jadual (lawatan khas)',
        'ptn.lawatan.kemas_kini' => 'Penyelia: semak gambar di luar sempadan, selesaikan konflik penyegerakan, laporan tambahan',
        'ptn.lapangan.lihat' => 'Aplikasi lapangan: muat turun tugasan sendiri',
        'ptn.lapangan.kemas_kini' => 'Aplikasi lapangan: rekod dan segerak lawatan yang diumpukkan',
        'ptn.isu.lihat' => 'Lihat isu pemantauan dan tindakan pembetulan',
        'ptn.isu.cipta' => 'Rekod isu pemantauan secara manual',
        'ptn.isu.kemas_kini' => 'Wujudkan tindakan pembetulan, eskalasi ke Aduan / Kes Mahkamah',
        'ptn.tindakan.kemas_kini' => 'Kemas kini kemajuan tindakan pembetulan yang diumpukkan',
        'ptn.risiko.lihat' => 'Lihat dashboard risiko dan butiran skor risiko lot',
        'ptn.peta.lihat' => 'Lihat lapisan GIS pemantauan dan risiko',
        'ptn.laporan.lihat' => 'Lihat laporan pemantauan',
        'ptn.laporan.eksport' => 'Eksport laporan pemantauan',
        'ptn.konfigurasi.lihat' => 'Lihat konfigurasi senarai semak, kekerapan dan ambang warna',
        'ptn.konfigurasi.kemas_kini' => 'Ubah senarai semak, kekerapan lawatan, ambang warna dan pemberat risiko',
    ];

    public function register(): void
    {
        $this->app->singleton(DaftarIsyaratRisiko::class);
        $this->app->alias(DaftarIsyaratRisiko::class, RiskSignals::class);
        $this->app->bind(LotRiskStatus::class, PtnLotRiskStatus::class);
        $this->app->bind(MaintenanceNeeds::class, PtnMaintenanceNeeds::class);
        $this->app->bindIf(LotMasterUpdates::class, LotMasterFallback::class);
        $this->app->bindIf(LegalCaseIntake::class, TiadaModulKes::class);
        $this->app->scoped(KodWarna::class);
        $this->app->scoped(Rujukan::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->call(function (PermissionRegistry $permissions, Parameters $parameters, NotificationTypes $types, WorkflowFlows $flows, SearchProviders $search, ProfileSections $profile) {
            $permissions->register('ptn', 'Pemantauan Tanah', self::PERMISSIONS);
            $this->declareParameters($parameters);
            $this->registerNotificationTypes($types);

            $flows->declare(AliranPtn::LAPORAN, 'Pengesahan laporan lawatan pemantauan', 'ptn', AliranPtn::SUBJEK_LAPORAN, ['kod_daerah', 'jenis', 'ada_kritikal']);
            $flows->declare(AliranPtn::KEMASKINI_LOT, 'Kelulusan cadangan kemas kini rekod tanah', 'ptn', AliranPtn::SUBJEK_CADANGAN, ['kod_daerah', 'medan']);
            $flows->declare(AliranPtn::TINDAKAN, 'Pengesahan penyelesaian tindakan pembetulan', 'ptn', AliranPtn::SUBJEK_TINDAKAN, ['kod_daerah', 'tahap_keterukan']);

            $search->register(LawatanSearchProvider::class);
            $profile->register(PemantauanSection::class);
            $profile->register(RisikoSection::class);
        });

        $this->callAfterResolving(MapLayers::class, function (MapLayers $layers) {
            $layers->register(PemantauanLayer::class);
            $layers->register(RisikoLotLayer::class);
        });
        $this->callAfterResolving(KpiProviders::class, function (KpiProviders $kpi) {
            foreach ([LawatanIkutJadual::class, LotBelumDiperiksa::class, PenemuanTerbuka::class, LotRisikoMerah::class] as $p) {
                $kpi->register($p);
            }
        });
        $this->callAfterResolving(RecordLinks::class, function (RecordLinks $l) {
            $l->register('rekod_lawatan', 'Lawatan Pemantauan', '/admin/ptn/lawatan/{id}', 'ptn.lawatan.lihat');
            $l->register('tindakan_pembetulan', 'Tindakan Pembetulan', '/admin/ptn/isu?tindakan={id}', 'ptn.isu.lihat');
        });
        if ($this->app->bound(Reminders::class)) {
            $r = $this->app->make(Reminders::class);
            $r->register(LawatanDijadualkan::class);
            $r->register(TindakanHampirTamat::class);
        }

        Event::listen(WorkflowCompleted::class, [AliranPtnListener::class, 'handle']);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('ptn:kira-risiko')->dailyAt('02:30')->withoutOverlapping()->name('ptn:kira-risiko');
            $schedule->command('ptn:semak-tertunggak')->dailyAt('07:00')->withoutOverlapping()->name('ptn:semak-tertunggak');
            $schedule->command('ptn:jana-jadual')->yearlyOn(12, 1, '03:00')->withoutOverlapping()->name('ptn:jana-jadual');
        });

        if ($this->app->runningInConsole()) {
            $this->commands([JanaJadualCommand::class, KiraRisikoCommand::class, SemakTertunggakCommand::class, SampleCommand::class]);
        }
    }

    private function declareParameters(Parameters $p): void
    {
        $p->declare('ptn.toleransi_lingkungan_m', 'int', 10, 'Toleransi GPS (meter) semasa menyemak koordinat gambar/lawatan berada dalam sempadan lot (SRS-PTN-07)');
        $p->declare('ptn.gambar_minimum', 'int', 1, 'Bilangan gambar minimum sebelum lawatan boleh dihantar');
        $p->declare('ptn.risiko_cukai_rm_penuh', 'int', 5000, 'Risiko tunggakan cukai: amaun tertunggak (RM) yang memberi skor penuh 100');
        $p->declare('ptn.risiko_dokumen_wajib', 'json', ['geran', 'pelan'], 'Risiko dokumen: kategori DMS wajib bagi setiap lot');
        $p->declare('ptn.nilai_aset_tinggi_rm', 'int', 1000000, 'Penjadualan: nilai pasaran (RM) yang dianggap aset bernilai tinggi (+10 keutamaan)');
        $p->declare('ptn.hari_belum_diperiksa', 'int', 365, 'KPI: lot dikira belum diperiksa jika tiada lawatan dalam tempoh (hari) ini');
        $p->declare('ptn.peranan_penguatkuasaan', 'string', 'penguatkuasaan', 'Kod peranan Bahagian Penguatkuasaan (penerima penemuan kritikal, dipisah koma)');
        $p->declare('ptn.peranan_pengurusan_hartanah', 'string', 'pengurusan_hartanah', 'Kod peranan Bahagian Pengurusan Hartanah (penerima penemuan kritikal, dipisah koma)');
    }

    private function registerNotificationTypes(NotificationTypes $t): void
    {
        $t->register('ptn.tugasan_baharu', 'Tugasan lawatan pemantauan baharu', NotificationTypes::BIASA,
            'Tugasan lawatan pemantauan baharu ({{bilangan}})',
            '{{bilangan}} tugasan lawatan pemantauan (lot {{no_lot}}, daerah {{daerah}}) telah diumpukkan kepada anda. Tarikh sasaran terawal {{tarikh_sasaran}}. Muat turun di aplikasi lapangan.', ['bilangan', 'no_lot', 'daerah', 'tarikh_sasaran']);
        $t->register(LawatanDijadualkan::TYPE, 'Peringatan lawatan pemantauan berjadual', NotificationTypes::BIASA,
            'Lawatan lot {{no_lot}} pada {{tarikh_sasaran}}',
            'Lawatan pemantauan lot {{no_lot}} dijadualkan pada {{tarikh_sasaran}} ({{baki_hari}} hari lagi). Pastikan tugasan telah dimuat turun ke aplikasi lapangan.', ['no_lot', 'tarikh_sasaran', 'baki_hari']);
        $t->register('ptn.penemuan_kritikal', 'Penemuan kritikal pemantauan tanah', NotificationTypes::KRITIKAL,
            'KRITIKAL: {{kategori}} di lot {{no_lot}}',
            'Penemuan kritikal {{no_rujukan}} ({{kategori}}) direkod pada {{tarikh}} di lot {{no_lot}}, daerah {{daerah}}. Lot ditanda merah pada peta risiko. Sila ambil tindakan segera.', ['no_rujukan', 'kategori', 'no_lot', 'daerah', 'tarikh'], true);
        $t->register('ptn.tindakan_ditugaskan', 'Tindakan pembetulan diumpukkan', NotificationTypes::TINGGI,
            'Tindakan pembetulan bagi isu {{no_rujukan}}',
            'Anda bertanggungjawab bagi tindakan "{{langkah}}" untuk isu {{no_rujukan}}. Tarikh sasaran {{tarikh_sasaran}}.', ['no_rujukan', 'langkah', 'tarikh_sasaran']);
        $t->register(TindakanHampirTamat::TYPE, 'Tindakan pembetulan hampir tarikh akhir', NotificationTypes::TINGGI,
            'Tindakan isu {{no_rujukan}} tamat dalam {{baki_hari}} hari',
            'Tindakan "{{langkah}}" bagi isu {{no_rujukan}} perlu disiapkan sebelum {{tarikh_sasaran}} ({{baki_hari}} hari lagi).', ['no_rujukan', 'langkah', 'tarikh_sasaran', 'baki_hari']);
        $t->register('ptn.tindakan_lewat', 'Tindakan pembetulan melepasi tarikh akhir', NotificationTypes::TINGGI,
            'LEWAT: tindakan isu {{no_rujukan}}',
            'Tindakan "{{langkah}}" bagi isu {{no_rujukan}} telah melepasi tarikh akhir {{tarikh_sasaran}} ({{hari_lewat}} hari). Dimaklumkan kepada pegawai bertanggungjawab dan penyelia.', ['no_rujukan', 'langkah', 'tarikh_sasaran', 'hari_lewat'], true);
        $t->register('ptn.laporan_dikembalikan', 'Laporan lawatan dikembalikan', NotificationTypes::TINGGI,
            'Laporan lawatan {{no_rujukan}} dikembalikan',
            'Laporan lawatan {{no_rujukan}} tidak disahkan: {{ulasan}}. Sila betulkan dan hantar semula melalui aplikasi lapangan.', ['no_rujukan', 'ulasan']);
    }
}
