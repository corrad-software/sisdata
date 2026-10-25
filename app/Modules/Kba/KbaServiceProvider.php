<?php

namespace App\Modules\Kba;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adm\Contracts\SearchProviders;
use App\Modules\Dms\Contracts\RecordLinks;
use App\Modules\Dms\Events\DocumentDisposed;
use App\Modules\Dms\Events\DocumentStored;
use App\Modules\Kba\Ai\HashEmbeddingProvider;
use App\Modules\Kba\Ai\OllamaEmbeddingProvider;
use App\Modules\Kba\Ai\OllamaLlmGateway;
use App\Modules\Kba\Ai\TiadaLlm;
use App\Modules\Kba\Console\AnalitikCommand;
use App\Modules\Kba\Console\IndeksBaselineCommand;
use App\Modules\Kba\Console\KeluaranCommand;
use App\Modules\Kba\Console\RingkasanJurangCommand;
use App\Modules\Kba\Contracts\ArticleDrafts;
use App\Modules\Kba\Contracts\DocumentTextSource;
use App\Modules\Kba\Contracts\EmbeddingProvider;
use App\Modules\Kba\Contracts\KnowledgeBase;
use App\Modules\Kba\Contracts\LlmGateway;
use App\Modules\Kba\Listeners\PendengarDms;
use App\Modules\Kba\Services\DmsTeksBacaSahaja;
use App\Modules\Kba\Services\Ingest\Chunker;
use App\Modules\Kba\Services\KbaSearchProvider;
use App\Modules\Kba\Services\PengurusArtikel;
use App\Modules\Kba\Services\Penjawab;
use App\Modules\Ntf\Contracts\NotificationTypes;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use App\Modules\Wfe\Contracts\SubjectLinks;
use App\Modules\Wfe\Contracts\WorkflowFlows;
use App\Modules\Wfe\Events\WorkflowCompleted;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Throwable;

/**
 * MOD-KBA · Pangkalan Pengetahuan Berbantu AI (SRS-KBA-01..22). Owns the
 * KnowledgeBase contract (grounded search & answers for TKT / PWS / DSH), the
 * ArticleDrafts contract (solution drafts from TKT) and the shared local-AI
 * interfaces EmbeddingProvider + LlmGateway (SRS-KBA-21). AI runs only on a
 * loopback / private endpoint (SRS-KBA-15); without a model it still works
 * (hashing embedder, extractive answers). Uses RBC, AUD, ADM, NTF, DMS and
 * WFE only through their contracts / events.
 */
class KbaServiceProvider extends ModuleServiceProvider
{
    protected string $code = 'kba';

    public const PERMISSIONS = [
        'kba.soalan.lihat' => 'Tanya Pangkalan Pengetahuan (AI prompt), carian kata kunci dan buka sumber yang dipetik',
        'kba.artikel.cipta' => 'Cipta draf artikel Pangkalan Pengetahuan',
        'kba.artikel.kemas_kini' => 'Sunting artikel, lihat draf & sejarah versi, hantar untuk semakan, tanda lapuk',
        'kba.keluaran.kemas_kini' => 'Rekod keluaran versi sistem baharu (tanda artikel perlu semakan)',
        'kba.sumber.lihat' => 'Lihat senarai sumber, liputan dan status pengindeksan',
        'kba.sumber.kemas_kini' => 'Daftar / muat naik sumber, ubah klasifikasi & peranan dibenarkan, indeks semula',
        'kba.pemetaan.kemas_kini' => 'Urus pemetaan klasifikasi–peranan Pangkalan Pengetahuan',
        'kba.maklum_balas.lihat' => 'Lihat maklum balas pengguna ke atas jawapan AI',
        'kba.maklum_balas.kemas_kini' => 'Kemas kini status tindakan maklum balas',
        'kba.analitik.lihat' => 'Lihat analitik dan laporan jurang kandungan Pangkalan Pengetahuan',
        'kba.log_prompt.audit' => 'Semak audit trail prompt, jawapan dan sumber dipetik semua pengguna (keselamatan ICT / juru audit)',
        'kba.kandungan.eksport' => 'Eksport keseluruhan kandungan Pangkalan Pengetahuan (Markdown / HTML)',
        'kba.sokongan.lihat' => 'Paparan pasukan sokongan: reka bentuk, integrasi, parameter dan sejarah masalah setiap modul',
        'kba.sulit.lihat' => 'Capai segmen Pangkalan Pengetahuan berklasifikasi Sulit',
        'kba.rahsia.lihat' => 'Capai segmen Pangkalan Pengetahuan berklasifikasi Rahsia',
    ];

    public function register(): void
    {
        $this->app->singleton(EmbeddingProvider::class, function ($app) {
            $c = (array) config('kba.embedding');
            if (($c['pemacu'] ?? 'cincangan') === 'ollama') {
                try {
                    return new OllamaEmbeddingProvider((string) $c['url'], (string) $c['model'], (int) ($c['dimensi'] ?? 768), (int) ($c['timeout'] ?? 60));
                } catch (Throwable $e) {
                    report($e); // a non-local endpoint is refused (SRS-KBA-15)
                }
            }

            return new HashEmbeddingProvider((int) ($c['dimensi'] ?? 768));
        });

        $this->app->singleton(LlmGateway::class, function ($app) {
            $c = (array) config('kba.llm');
            if (($c['pemacu'] ?? 'tiada') === 'ollama') {
                try {
                    return new OllamaLlmGateway((string) $c['url'], (string) $c['model'], (int) ($c['timeout'] ?? 90), (int) ($c['konteks'] ?? 4096));
                } catch (Throwable $e) {
                    report($e);
                }
            }

            return new TiadaLlm;
        });

        $this->app->bind(Chunker::class, fn () => new Chunker(1400, 200));
        $this->app->bind(DocumentTextSource::class, DmsTeksBacaSahaja::class);
        $this->app->bind(KnowledgeBase::class, Penjawab::class);
        $this->app->bind(ArticleDrafts::class, PengurusArtikel::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->call(function (PermissionRegistry $permissions, Parameters $parameters, NotificationTypes $types, SearchProviders $search) {
            $permissions->register('kba', 'Pangkalan Pengetahuan Berbantu AI', self::PERMISSIONS);
            $this->declareParameters($parameters);
            $types->register('kba.ringkasan_jurang', 'Ringkasan jurang kandungan Pangkalan Pengetahuan', NotificationTypes::MAKLUMAT,
                'Jurang kandungan Pangkalan Pengetahuan: {{bil_jurang}} soalan ({{tempoh}})',
                "{{bil_soalan}} pertanyaan dalam tempoh {{tempoh}} tidak mempunyai sumber mencukupi atau menerima maklum balas negatif ({{bil_jurang}} soalan berbeza).\nContoh: {{contoh}}\nSemak laporan jurang kandungan dalam SISDATA.",
                ['bil_jurang', 'bil_soalan', 'tempoh', 'contoh']);
            $types->register('kba.maklum_balas_negatif', 'Maklum balas negatif jawapan AI', NotificationTypes::BIASA,
                'Maklum balas jawapan AI: {{penilaian}}',
                'Seorang pengguna menilai jawapan Pangkalan Pengetahuan (log #{{id_log}}) sebagai "{{penilaian}}". Sila semak dalam SISDATA.',
                ['penilaian', 'id_log']);
            $types->register('kba.artikel_perlu_semakan', 'Artikel perlu disemak selepas keluaran baharu', NotificationTypes::BIASA,
                '{{bil}} artikel anda perlu disemak (keluaran {{versi}})',
                'Keluaran sistem versi {{versi}} telah direkodkan. {{bil}} artikel milik anda merujuk versi lama dan perlu disemak.',
                ['bil', 'versi']);
            $search->register(KbaSearchProvider::class);
        });

        $this->callAfterResolving(WorkflowFlows::class, function (WorkflowFlows $flows) {
            $flows->declare(PengurusArtikel::ALIRAN, 'Semakan & penerbitan artikel Pangkalan Pengetahuan', 'kba', PengurusArtikel::SUBJEK, ['modul', 'klasifikasi', 'sumber_asal']);
        });
        $this->callAfterResolving(SubjectLinks::class, fn (SubjectLinks $links) => $links->register(PengurusArtikel::SUBJEK, '/admin/kba/artikel/{id}'));
        $this->callAfterResolving(RecordLinks::class, fn (RecordLinks $links) => $links->register('kba_sumber_dokumen', 'Sumber Pangkalan Pengetahuan', '/admin/kba/sumber/{id}', 'kba.soalan.lihat'));

        Event::listen(DocumentStored::class, [PendengarDms::class, 'stored']);
        Event::listen(DocumentDisposed::class, [PendengarDms::class, 'disposed']);
        Event::listen(WorkflowCompleted::class, fn (WorkflowCompleted $e) => $this->app->make(PengurusArtikel::class)->selesaiAliran($e));

        if ($this->app->runningInConsole()) {
            $this->commands([IndeksBaselineCommand::class, RingkasanJurangCommand::class, AnalitikCommand::class, KeluaranCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('kba:ringkasan-jurang')->weeklyOn(1, '08:00')->withoutOverlapping()->name('kba:ringkasan-jurang');
            $schedule->command('kba:analitik')->dailyAt('01:30')->withoutOverlapping()->name('kba:analitik');
        });
    }

    private function declareParameters(Parameters $p): void
    {
        $p->declare('kba.mod_jawapan', 'string', 'auto', 'Mod jawapan AI: auto (model tempatan jika tersedia, jika tidak ekstraktif) | ekstraktif (petikan sahaja, tiada penjanaan)');
        $p->declare('kba.bilangan_petikan', 'int', 5, 'Bilangan segmen maksimum dimasukkan ke dalam konteks jawapan');
        $p->declare('kba.ambang_liputan_peratus', 'int', 50, 'Peratus minimum kata kunci soalan yang mesti ditemui dalam sumber sebelum menjawab (SRS-KBA-05)');
        $p->declare('kba.ambang_kesamaan_peratus', 'int', 55, 'Kesamaan semantik minimum (%) bersama liputan kata kunci (SRS-KBA-05)');
        $p->declare('kba.ambang_kesamaan_tinggi_peratus', 'int', 72, 'Kesamaan semantik (%) yang cukup tanpa padanan kata kunci (cth. soalan bahasa Inggeris)');
        $p->declare('kba.had_prompt_seminit', 'int', 10, 'Had AI prompt setiap pengguna seminit (SRS-KBA-19)', true);
        $p->declare('kba.had_prompt_sehari', 'int', 300, 'Had AI prompt setiap pengguna sehari (SRS-KBA-19)', true);
        $p->declare('kba.had_peranan', 'json', [], 'Had prompt seminit mengikut peranan, cth. {"pentadbir_sistem": 30} — had tertinggi peranan pengguna digunakan');
        $p->declare('kba.had_prompt_awam_seminit', 'int', 60, 'Had keseluruhan prompt awam (tanpa log masuk, cth. Portal PWS) seminit');
        $p->declare('kba.kategori_dms_automatik', 'json', ['laporan'], 'Kategori DMS yang diindeks secara automatik apabila dokumen / versi baharu disimpan (SRS-KBA-02)');
        $p->declare('kba.kategori_dms', 'string', 'laporan', 'Kategori DMS bagi fail asal (PDF/DOCX) yang dimuat naik ke Pangkalan Pengetahuan');
        $p->declare('kba.hari_semakan_artikel', 'int', 180, 'Tempoh (hari) tarikh semakan seterusnya artikel selepas diterbitkan (SRS-KBA-11)');
    }
}
