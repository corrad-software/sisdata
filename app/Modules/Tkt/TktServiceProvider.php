<?php

namespace App\Modules\Tkt;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adm\Contracts\SearchProviders;
use App\Modules\Dms\Contracts\RecordLinks;
use App\Modules\Dsh\Contracts\KpiProviders;
use App\Modules\Ntf\Contracts\NotificationTypes;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use App\Modules\Tkt\Console\LaporanBulananCommand;
use App\Modules\Tkt\Console\SampleCommand;
use App\Modules\Tkt\Console\SegerakPusatCommand;
use App\Modules\Tkt\Console\SemakSlaCommand;
use App\Modules\Tkt\Contracts\CentralTicketSystem;
use App\Modules\Tkt\Contracts\TicketIntake;
use App\Modules\Tkt\Integrasi\MockSistemAduanPusat;
use App\Modules\Tkt\Kpi\KadarPenyelesaianKendiri;
use App\Modules\Tkt\Kpi\Mttr;
use App\Modules\Tkt\Kpi\PematuhanSla;
use App\Modules\Tkt\Kpi\TiketTerbuka;
use App\Modules\Tkt\Services\TiketSearchProvider;
use App\Modules\Tkt\Services\TiketService;
use App\Modules\Tkt\Services\TktTicketIntake;
use App\Modules\Tkt\Support\Akses;
use App\Modules\Tkt\Support\Lalai;
use App\Modules\Wfe\Contracts\SubjectLinks;
use App\Modules\Wfe\Contracts\WorkflowFlows;
use App\Modules\Wfe\Events\WorkflowCompleted;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

/**
 * MOD-TKT · Tiket Sokongan Sistem (SRS-TKT-01..21). Implements
 * TicketIntake (for MOD-PWS, e-mail, monitoring) and its own adapter
 * contract CentralTicketSystem (mock until MAIS gives access); uses RBC,
 * AUD, ADM, NTF, DMS, WFE, KBA, ADU and DSH only through their contracts.
 */
class TktServiceProvider extends ModuleServiceProvider
{
    protected string $code = 'tkt';

    public const PERMISSIONS = [
        'tkt.tiket.cipta' => 'Buka tiket sokongan melalui butang bantuan (untuk semua pengguna)',
        'tkt.tiket.lihat' => 'Lihat tiket sokongan sendiri',
        'tkt.tiket.kemas_kini' => 'Pelapor: balas, sahkan penyelesaian, buka semula, tinjauan kepuasan, lampiran tambahan',
        'tkt.sokongan.lihat' => 'Pasukan sokongan: lihat tiket yang ditugaskan dan tiket modul pasukan (termasuk catatan dalaman)',
        'tkt.sokongan.kemas_kini' => 'Pasukan sokongan: siasat, tukar status, catatan, tugaskan semula, eskalasi, serah ke vendor, pendua, permintaan perubahan',
        'tkt.semua.pantau' => 'Pengurusan sokongan: lihat dan urus semua tiket (penyelia / pengurus projek / BTM)',
        'tkt.sulit.lihat' => 'Lihat tiket berklasifikasi atau isu keselamatan (setiap capaian diaudit)',
        'tkt.laporan.lihat' => 'Papan pemuka sokongan, laporan SLA, eskalasi dan penyelesaian kendiri',
        'tkt.laporan.eksport' => 'Jana dan eksport laporan sokongan bulanan (PDF / Excel)',
        'tkt.aktiviti.cipta' => 'Rekod aktiviti sokongan (tampalan, penyelenggaraan pencegahan, masa henti, pemindahan teknologi)',
        'tkt.konfigurasi.lihat' => 'Lihat konfigurasi SLA, pasukan, penugasan dan pemetaan Sistem Aduan',
        'tkt.konfigurasi.kemas_kini' => 'Ubah ambang SLA, hierarki eskalasi, pasukan, peraturan penugasan, pemetaan dan integrasi',
        'tkt.serahan.eksport' => 'Muat turun pakej serahan sokongan (sejarah tiket, konfigurasi SLA, hierarki eskalasi, pautan KB)',
        'tkt.integrasi.sistem' => 'Akaun integrasi: Sistem Aduan berpusat MAIS (buka / kemas kini tiket melalui API Gateway)',
    ];

    public function register(): void
    {
        $this->app->bind(TicketIntake::class, TktTicketIntake::class);
        $this->app->bindIf(CentralTicketSystem::class, MockSistemAduanPusat::class);
        $this->app->scoped(Akses::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->call(function (PermissionRegistry $permissions, Parameters $parameters, NotificationTypes $types, WorkflowFlows $flows, SubjectLinks $links, SearchProviders $search) {
            $permissions->register('tkt', 'Tiket Sokongan Sistem', self::PERMISSIONS);
            $this->declareParameters($parameters);
            $this->registerNotificationTypes($types);
            // Change requests (permintaan perubahan, §18.4) are approved through MOD-WFE — database/seeders/TktAliranSeeder seeds the default.
            $flows->declare(TiketService::FLOW, 'Kelulusan permintaan perubahan (tiket sokongan)', 'tkt', TiketService::SUBJECT, ['modul', 'keterukan', 'kategori']);
            $links->register(TiketService::SUBJECT, '/admin/tkt/tiket/{id}');
            $search->register(TiketSearchProvider::class);
        });

        $this->callAfterResolving(KpiProviders::class, function (KpiProviders $kpi) {
            foreach ([TiketTerbuka::class, PematuhanSla::class, Mttr::class, KadarPenyelesaianKendiri::class] as $provider) {
                $kpi->register($provider);
            }
        });
        $this->callAfterResolving(RecordLinks::class, function (RecordLinks $l) {
            $l->register(TiketService::SUBJECT, 'Tiket Sokongan', '/admin/tkt/tiket/{id}', 'tkt.tiket.lihat');
            $l->register('tkt_laporan_bulanan', 'Laporan Sokongan Bulanan', '/admin/tkt/laporan', 'tkt.laporan.lihat');
        });

        Event::listen(WorkflowCompleted::class, [TiketService::class, 'handle']);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('tkt:semak-sla')->everyFiveMinutes()->withoutOverlapping()->name('tkt:semak-sla');
            $schedule->command('tkt:segerak-pusat')->everyTenMinutes()->withoutOverlapping()->name('tkt:segerak-pusat');
            $schedule->command('tkt:laporan-bulanan')->monthlyOn(1, '07:00')->timezone('Asia/Kuala_Lumpur')->name('tkt:laporan-bulanan');
        });

        if ($this->app->runningInConsole()) {
            $this->commands([SemakSlaCommand::class, SegerakPusatCommand::class, LaporanBulananCommand::class, SampleCommand::class]);
        }
    }

    private function declareParameters(Parameters $p): void
    {
        $p->declare('tkt.versi_sistem', 'string', '1.0.0', 'Versi sistem SISDATA semasa yang direkod pada setiap tiket (SRS-TKT-02)');
        $p->declare('tkt.matriks_keterukan', 'json', Lalai::MATRIKS, 'Matriks kesan × kesegeraan → severity cadangan (SRS-TKT-08)');
        $p->declare('tkt.hari_buka_semula', 'int', 10, 'Tempoh (hari) tiket boleh dibuka semula selepas selesai (SRS-TKT-12)');
        $p->declare('tkt.hari_peringatan_pengesahan', 'int', 3, 'Hari selepas selesai sebelum peringatan pengesahan dihantar kepada pelapor (SRS-TKT-12)');
        $p->declare('tkt.hari_tutup_automatik', 'int', 5, 'Hari selepas selesai tanpa maklum balas pelapor sebelum tiket ditutup automatik (SRS-TKT-12)');
        $p->declare('tkt.hari_tutup_selepas_disahkan', 'int', 2, 'Hari selepas pelapor mengesahkan sebelum tiket ditutup automatik');
        $p->declare('tkt.ambang_pendua', 'string', '0.45', 'Ambang keserupaan tajuk (0–1, pg_trgm) untuk cadangan tiket pendua');
        $p->declare('tkt.tempoh_pendua_hari', 'int', 30, 'Tempoh (hari) carian tiket pendua');
        $p->declare('tkt.peranan_pengurus_projek', 'string', 'pengurus_projek', 'Kod peranan penerima eskalasi "pengurus projek" jika pasukan tiada pengurus (§18.5)');
        $p->declare('tkt.integrasi_ip_dibenarkan', 'string', '', 'Senarai IP / CIDR (dipisah koma) yang dibenarkan memanggil API tiket berpusat; kosong = semua (SRS-TKT-14)', true);
        $p->declare('tkt.mock_pusat_gagal', 'bool', false, 'Adapter mock Sistem Aduan berpusat: simulasi gagal (ujian SRS-TKT-16)');
    }

    private function registerNotificationTypes(NotificationTypes $t): void
    {
        $f = ['nombor_rujukan', 'kategori', 'keterukan', 'modul'];
        $sla = [...$f, 'peringkat', 'sasaran', 'tarikh_sasaran', 'sebab'];
        $t->register('tkt.tiket_diterima', 'Tiket sokongan diterima (akuan)', NotificationTypes::BIASA,
            'Tiket {{nombor_rujukan}} telah diterima',
            'Tiket sokongan anda {{nombor_rujukan}} ({{kategori}}, {{keterukan}}) telah diterima dan akan diambil tindakan mengikut SLA.', [...$f, 'tajuk'], true);
        $t->register('tkt.tiket_baharu', 'Tiket sokongan baharu belum ditugaskan', NotificationTypes::TINGGI,
            'Tiket baharu {{nombor_rujukan}} — {{keterukan}}',
            'Tiket {{nombor_rujukan}} ({{kategori}}, modul {{modul}}, {{keterukan}}) belum ditugaskan kepada juruteknik.', $f);
        $t->register('tkt.tiket_ditugaskan', 'Tiket sokongan ditugaskan kepada anda', NotificationTypes::TINGGI,
            'Tiket {{nombor_rujukan}} ditugaskan kepada anda',
            'Tiket {{nombor_rujukan}} ({{kategori}}, modul {{modul}}, {{keterukan}}) telah ditugaskan kepada anda.', $f, true);
        $t->register('tkt.status_berubah', 'Status tiket sokongan berubah', NotificationTypes::MAKLUMAT,
            'Tiket {{nombor_rujukan}}: {{status}}',
            'Status tiket {{nombor_rujukan}} kini {{status}}.', [...$f, 'status']);
        $t->register('tkt.balasan_sokongan', 'Balasan pasukan sokongan', NotificationTypes::BIASA,
            'Balasan baharu bagi tiket {{nombor_rujukan}}',
            'Pasukan sokongan telah membalas tiket {{nombor_rujukan}}. Sila semak dalam SISDATA.', $f);
        $t->register('tkt.balasan_pelapor', 'Pelapor membalas tiket', NotificationTypes::BIASA,
            'Pelapor membalas tiket {{nombor_rujukan}}',
            'Pelapor telah membalas tiket {{nombor_rujukan}} ({{keterukan}}).', $f);
        $t->register('tkt.sla_amaran', 'Tiket menghampiri ambang SLA', NotificationTypes::TINGGI,
            'SLA {{sasaran}} tiket {{nombor_rujukan}} hampir tamat',
            'Tempoh {{sasaran}} bagi tiket {{nombor_rujukan}} ({{keterukan}}) akan tamat pada {{tarikh_sasaran}}.', $sla, true);
        $t->register('tkt.sla_eskalasi', 'Eskalasi: SLA tiket dilepasi', NotificationTypes::KRITIKAL,
            'Eskalasi peringkat {{peringkat}}: SLA tiket {{nombor_rujukan}} dilepasi',
            'Tempoh {{sasaran}} bagi tiket {{nombor_rujukan}} ({{keterukan}}, modul {{modul}}) telah dilepasi pada {{tarikh_sasaran}}. Tiket dieskalasikan kepada anda (peringkat {{peringkat}}).', $sla, true);
        $t->register('tkt.eskalasi_manual', 'Tiket dieskalasikan kepada anda', NotificationTypes::TINGGI,
            'Tiket {{nombor_rujukan}} dieskalasikan kepada anda',
            'Tiket {{nombor_rujukan}} ({{keterukan}}) dieskalasikan ke peringkat {{peringkat}}: {{sebab}}', $sla);
        $t->register('tkt.tiket_selesai', 'Tiket selesai — mohon pengesahan', NotificationTypes::TINGGI,
            'Tiket {{nombor_rujukan}} telah diselesaikan',
            'Tiket {{nombor_rujukan}} telah diselesaikan: {{penyelesaian}}. Sila sahkan penyelesaian atau buka semula tiket dalam SISDATA.', [...$f, 'penyelesaian'], true);
        $t->register('tkt.peringatan_pengesahan', 'Peringatan pengesahan penyelesaian', NotificationTypes::BIASA,
            'Peringatan: sahkan penyelesaian tiket {{nombor_rujukan}}',
            'Tiket {{nombor_rujukan}} menunggu pengesahan anda. Tiket akan ditutup automatik selepas {{hari}} hari dari tarikh selesai.', [...$f, 'hari']);
        $t->register('tkt.pengesahan_pelapor', 'Pelapor mengesahkan penyelesaian', NotificationTypes::MAKLUMAT,
            'Tiket {{nombor_rujukan}} disahkan pelapor',
            'Pelapor telah mengesahkan penyelesaian tiket {{nombor_rujukan}}.', $f);
        $t->register('tkt.tiket_ditutup', 'Tiket sokongan ditutup', NotificationTypes::MAKLUMAT,
            'Tiket {{nombor_rujukan}} ditutup',
            'Tiket {{nombor_rujukan}} telah ditutup. Terima kasih.', $f);
        $t->register('tkt.tiket_dibuka_semula', 'Tiket dibuka semula', NotificationTypes::TINGGI,
            'Tiket {{nombor_rujukan}} dibuka semula',
            'Tiket {{nombor_rujukan}} ({{keterukan}}) telah dibuka semula: {{sebab}}', [...$f, 'sebab']);
        $t->register('tkt.tinjauan_kepuasan', 'Tinjauan kepuasan sokongan', NotificationTypes::MAKLUMAT,
            'Bagaimana pengalaman sokongan tiket {{nombor_rujukan}}?',
            'Sila beri penilaian (1–5) bagi sokongan tiket {{nombor_rujukan}} dalam SISDATA.', $f);
        $t->register('tkt.keputusan_kelulusan', 'Keputusan permintaan perubahan', NotificationTypes::BIASA,
            'Permintaan perubahan {{nombor_rujukan}}: {{keputusan}}',
            'Permintaan perubahan dalam tiket {{nombor_rujukan}} telah {{keputusan}}.', [...$f, 'keputusan']);
        $t->register('tkt.penyegerakan_gagal', 'Penyegerakan Sistem Aduan berpusat gagal', NotificationTypes::TINGGI,
            'Penyegerakan tiket {{nombor_rujukan}} gagal',
            'Status tiket {{nombor_rujukan}} gagal disegerakkan ke Sistem Aduan berpusat selepas {{cubaan}} cubaan. Sila semak laporan rekonsiliasi.', [...$f, 'cubaan']);
        $t->register('tkt.laporan_bulanan', 'Laporan sokongan bulanan dijana', NotificationTypes::BIASA,
            'Laporan sokongan bulanan {{bulan}}',
            'Laporan sokongan bulan {{bulan}} telah dijana: {{dibuka}} tiket dibuka, pematuhan SLA penyelesaian {{peratus_selesai}}%.', ['bulan', 'dibuka', 'peratus_selesai']);
    }
}
