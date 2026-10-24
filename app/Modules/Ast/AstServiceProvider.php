<?php

namespace App\Modules\Ast;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adm\Contracts\SearchProviders;
use App\Modules\Ast\Console\ImportGisCommand;
use App\Modules\Ast\Console\JanaJadualCommand;
use App\Modules\Ast\Console\SampleCommand;
use App\Modules\Ast\Console\SemakHarianCommand;
use App\Modules\Ast\Console\TarikKeperluanPtnCommand;
use App\Modules\Ast\Integrasi\AsetFizikalLayer;
use App\Modules\Ast\Integrasi\AsetFizikalSection;
use App\Modules\Ast\Integrasi\AsetSearchProvider;
use App\Modules\Ast\Integrasi\Kpi\BilanganAset;
use App\Modules\Ast\Integrasi\Kpi\BilanganAsetKategori;
use App\Modules\Ast\Integrasi\Kpi\KerjaTertunggak;
use App\Modules\Ast\Integrasi\Kpi\KosPenyelenggaraan;
use App\Modules\Ast\Integrasi\Kpi\NilaiBuku;
use App\Modules\Ast\Integrasi\Reminders\InsuransTamat;
use App\Modules\Ast\Integrasi\Reminders\JaminanTamat;
use App\Modules\Ast\Integrasi\Reminders\PenilaianSemula;
use App\Modules\Ast\Integrasi\Reminders\PenyelenggaraanBerjadual;
use App\Modules\Ast\Services\AliranAstListener;
use App\Modules\Ast\Support\AliranAst;
use App\Modules\Ast\Support\Rujukan;
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
 * MOD-AST · Inventori Aset Fizikal (SRS-AST-01..26). Uses RBC, AUD, ADM,
 * NTF, DMS, WFE, PRO, GIS, DSH and PTN only through their contracts:
 * registers the real GIS asset layer (`ast.aset_fizikal`, mandatory slot 10),
 * the PRO 360° section `aset_fizikal` (with maintenance costs as
 * FinancialSeries `kos_penyelenggaraan`), DSH KPIs, NTF reminders, WFE flows,
 * and consumes MOD-PTN's MaintenanceNeeds into work orders. MOD-PJK's
 * premis_aset is linked read-only (PJK exposes no premises contract).
 */
class AstServiceProvider extends ModuleServiceProvider
{
    protected string $code = 'ast';

    public const PERMISSIONS = [
        'ast.aset.lihat' => 'Lihat inventori dan profil penuh aset fizikal',
        'ast.aset.cipta' => 'Daftar aset fizikal baharu',
        'ast.aset.kemas_kini' => 'Kemas kini rekod aset, status kondisi, tag, gambar dan dokumen',
        'ast.aset.padam' => 'Mohon pelupusan / hapus kira / perobohan aset (melalui kelulusan)',
        'ast.sulit.kemas_kini' => 'Kemas kini nilai semasa aset (Bahagian Kewangan / Unit Aset diberi kuasa)',
        'ast.qr.eksport' => 'Cetak label QR (individu dan kelompok)',
        'ast.qr.kemas_kini' => 'Nyahaktif / jana semula QR aset',
        'ast.imbasan.lihat' => 'Lihat log imbasan QR',
        'ast.pemeriksaan.lihat' => 'Lihat rekod pemeriksaan aset',
        'ast.pemeriksaan.cipta' => 'Rekod pemeriksaan aset (manual / selepas imbasan QR)',
        'ast.lapangan.lihat' => 'Aplikasi lapangan aset: muat turun senarai aset untuk pemeriksaan',
        'ast.lapangan.kemas_kini' => 'Aplikasi lapangan aset: rekod dan segerak pemeriksaan',
        'ast.penyelenggaraan.lihat' => 'Lihat jadual, kalendar dan rekod penyelenggaraan',
        'ast.penyelenggaraan.cipta' => 'Wujudkan jadual dan kerja penyelenggaraan',
        'ast.penyelenggaraan.kemas_kini' => 'Kemas kini jadual dan kerja penyelenggaraan, kos dan kontraktor',
        'ast.kerja.kemas_kini' => 'Kontraktor: kemas kini status kerja penyelenggaraan yang diumpukkan sahaja',
        'ast.kontraktor.lihat' => 'Lihat senarai kontraktor penyelenggaraan',
        'ast.kontraktor.kemas_kini' => 'Daftar dan kemas kini kontraktor penyelenggaraan',
        'ast.peta.lihat' => 'Lihat lapisan GIS aset fizikal',
        'ast.laporan.lihat' => 'Lihat laporan aset fizikal',
        'ast.laporan.eksport' => 'Eksport laporan aset fizikal',
        'ast.konfigurasi.lihat' => 'Lihat konfigurasi kategori aset',
        'ast.konfigurasi.kemas_kini' => 'Ubah kategori aset, kitaran penyelenggaraan dan ambang penilaian',
    ];

    public function register(): void
    {
        $this->app->scoped(Rujukan::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->call(function (PermissionRegistry $permissions, Parameters $parameters, NotificationTypes $types, WorkflowFlows $flows, SearchProviders $search, ProfileSections $profile) {
            $permissions->register('ast', 'Inventori Aset Fizikal', self::PERMISSIONS);
            $this->declareParameters($parameters);
            $this->registerNotificationTypes($types);

            $flows->declare(AliranAst::PEROLEHAN, 'Kelulusan perolehan / pendaftaran aset bernilai tinggi', 'ast', AliranAst::SUBJEK_ASET, ['kod_daerah', 'kategori', 'kos_perolehan']);
            $flows->declare(AliranAst::PELUPUSAN, 'Kelulusan pelupusan / hapus kira / perobohan aset', 'ast', AliranAst::SUBJEK_PELUPUSAN, ['kod_daerah', 'jenis', 'nilai_semasa']);
            $flows->declare(AliranAst::PENYELENGGARAAN, 'Kelulusan kerja penyelenggaraan melebihi ambang kos', 'ast', AliranAst::SUBJEK_KERJA, ['kod_daerah', 'kos_anggaran', 'jenis_kerja']);

            $search->register(AsetSearchProvider::class);
            $profile->register(AsetFizikalSection::class);
        });

        $this->callAfterResolving(MapLayers::class, fn (MapLayers $layers) => $layers->register(AsetFizikalLayer::class));
        $this->callAfterResolving(KpiProviders::class, function (KpiProviders $kpi) {
            foreach ([BilanganAset::class, BilanganAsetKategori::class, NilaiBuku::class, KosPenyelenggaraan::class, KerjaTertunggak::class] as $p) {
                $kpi->register($p);
            }
        });
        $this->callAfterResolving(RecordLinks::class, function (RecordLinks $l) {
            $l->register(AliranAst::SUBJEK_ASET, 'Aset Fizikal', '/admin/ast/aset/{id}', 'ast.aset.lihat');
            $l->register(AliranAst::SUBJEK_KERJA, 'Kerja Penyelenggaraan Aset', '/admin/ast/kerja/{id}', 'ast.penyelenggaraan.lihat');
        });
        $this->callAfterResolving(SubjectLinks::class, function (SubjectLinks $l) {
            $l->register(AliranAst::SUBJEK_ASET, '/admin/ast/aset/{id}');
            $l->register(AliranAst::SUBJEK_KERJA, '/admin/ast/kerja/{id}');
        });
        if ($this->app->bound(Reminders::class)) {
            $r = $this->app->make(Reminders::class);
            foreach ([PenyelenggaraanBerjadual::class, PenilaianSemula::class, JaminanTamat::class, InsuransTamat::class] as $source) {
                $r->register($source);
            }
        }

        Event::listen(WorkflowCompleted::class, [AliranAstListener::class, 'handle']);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('ast:tarik-keperluan-ptn')->dailyAt('05:30')->withoutOverlapping()->name('ast:tarik-keperluan-ptn');
            $schedule->command('ast:semak-harian')->dailyAt('06:30')->withoutOverlapping()->name('ast:semak-harian');
        });

        if ($this->app->runningInConsole()) {
            $this->commands([SampleCommand::class, ImportGisCommand::class, JanaJadualCommand::class, SemakHarianCommand::class, TarikKeperluanPtnCommand::class]);
        }
    }

    private function declareParameters(Parameters $p): void
    {
        $p->declare('ast.ambang_kelulusan_perolehan_rm', 'int', 500000, 'Kos perolehan (RM) yang memerlukan kelulusan WFE semasa pendaftaran aset', true);
        $p->declare('ast.ambang_kelulusan_penyelenggaraan_rm', 'int', 20000, 'Anggaran kos kerja penyelenggaraan (RM) yang memerlukan kelulusan WFE', true);
        $p->declare('ast.tempoh_amaran_hari', 'int', 14, 'Tempoh amaran lalai (hari) sebelum penyelenggaraan berjadual — kerja dijana dan notifikasi dihantar (SRS-AST-16)');
        $p->declare('ast.peranan_ketua_unit', 'string', 'ketua_unit_aset', 'Kod peranan Ketua Unit Aset (penerima eskalasi penyelenggaraan tertunggak, dipisah koma)');
        $p->declare('ast.toleransi_lokasi_m', 'int', 150, 'Jarak (meter) dari titik aset yang dianggap "di lokasi" semasa pemeriksaan lapangan');
        $p->declare('ast.tag_berstruktur', 'json', ['warisan', 'tarikan_pelancong', 'wakaf_khas', 'sewaan', 'oku_mesra', 'risiko_banjir'], 'Senarai tag berstruktur aset (SRS-AST-11)');
        $p->declare('ast.pautan_qr_asas', 'string', '', 'URL asas pautan awam QR (kosong = FRONTEND_URL). Contoh: https://sisdata.mais.gov.my');
    }

    private function registerNotificationTypes(NotificationTypes $t): void
    {
        $t->register(PenyelenggaraanBerjadual::TYPE, 'Peringatan penyelenggaraan aset berjadual', NotificationTypes::BIASA,
            'Penyelenggaraan {{jenis}} — {{nama_aset}} pada {{tarikh}}',
            'Penyelenggaraan berjadual {{jenis}} bagi aset {{nama_aset}} ({{no_rujukan}}) jatuh tempoh pada {{tarikh}} ({{baki_hari}} hari lagi).', ['jenis', 'nama_aset', 'no_rujukan', 'tarikh', 'baki_hari']);
        $t->register('ast.penyelenggaraan_tertunggak', 'Penyelenggaraan aset tertunggak (eskalasi)', NotificationTypes::TINGGI,
            'TERTUNGGAK: penyelenggaraan {{jenis}} — {{nama_aset}}',
            'Penyelenggaraan {{jenis}} bagi aset {{nama_aset}} ({{no_rujukan}}) telah tertunggak sejak {{tarikh}} ({{hari_lewat}} hari). Dimaklumkan kepada pegawai bertanggungjawab dan Ketua Unit Aset.', ['jenis', 'nama_aset', 'no_rujukan', 'tarikh', 'hari_lewat'], true);
        $t->register(PenilaianSemula::TYPE, 'Penilaian semula nilai aset diperlukan', NotificationTypes::BIASA,
            'Penilaian semula aset {{nama_aset}}',
            'Nilai semasa aset {{nama_aset}} ({{no_rujukan}}) kali terakhir dinilai pada {{tarikh_nilai}}. Penilaian semula diperlukan menjelang {{tarikh}} ({{baki_hari}} hari lagi).', ['nama_aset', 'no_rujukan', 'tarikh_nilai', 'tarikh', 'baki_hari']);
        $t->register(JaminanTamat::TYPE, 'Jaminan / waranti aset hampir tamat', NotificationTypes::BIASA,
            'Jaminan {{perkara}} tamat {{tarikh}}',
            'Jaminan "{{perkara}}" bagi aset {{nama_aset}} ({{no_rujukan}}) tamat pada {{tarikh}} ({{baki_hari}} hari lagi).', ['perkara', 'nama_aset', 'no_rujukan', 'tarikh', 'baki_hari']);
        $t->register(InsuransTamat::TYPE, 'Polisi insurans aset hampir tamat', NotificationTypes::TINGGI,
            'Insurans aset {{nama_aset}} tamat {{tarikh}}',
            'Polisi insurans {{no_polisi}} bagi aset {{nama_aset}} ({{no_rujukan}}) tamat pada {{tarikh}} ({{baki_hari}} hari lagi). Sila perbaharui.', ['no_polisi', 'nama_aset', 'no_rujukan', 'tarikh', 'baki_hari']);
        $t->register('ast.kerja_ditugaskan', 'Kerja penyelenggaraan aset diumpukkan', NotificationTypes::BIASA,
            'Kerja penyelenggaraan {{no_rujukan}}',
            'Kerja penyelenggaraan {{no_rujukan}} ({{jenis}}) bagi aset {{nama_aset}} telah diumpukkan. Tarikh dijadual {{tarikh}}.', ['no_rujukan', 'jenis', 'nama_aset', 'tarikh']);
        $t->register('ast.kondisi_kritikal', 'Aset berstatus kondisi kritikal', NotificationTypes::TINGGI,
            'KRITIKAL: aset {{nama_aset}}',
            'Status kondisi aset {{nama_aset}} ({{no_rujukan}}) ditukar kepada KRITIKAL pada {{tarikh}}. {{catatan}}', ['nama_aset', 'no_rujukan', 'tarikh', 'catatan']);
        $t->register('ast.keputusan_kelulusan', 'Keputusan kelulusan aset', NotificationTypes::BIASA,
            '{{perkara}}: {{keputusan}}',
            'Permohonan {{perkara}} bagi aset {{nama_aset}} ({{no_rujukan}}) telah {{keputusan}}. {{ulasan}}', ['perkara', 'keputusan', 'nama_aset', 'no_rujukan', 'ulasan']);
    }
}
