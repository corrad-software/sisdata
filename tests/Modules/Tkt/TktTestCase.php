<?php

namespace Tests\Modules\Tkt;

use App\Models\User;
use App\Modules\Adu\Contracts\ComplaintIntake;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Kba\Contracts\ArticleDrafts;
use App\Modules\Kba\Contracts\KnowledgeBase;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Tkt\Contracts\CentralTicketSystem;
use App\Modules\Tkt\Models\AhliPasukan;
use App\Modules\Tkt\Models\Pasukan;
use App\Modules\Tkt\Models\Tiket;
use App\Modules\Tkt\Services\TiketService;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Modules\Aud\Concerns\AssertsAuditTrail;
use Tests\Modules\Tkt\Support\FakeArticleDrafts;
use Tests\Modules\Tkt\Support\FakeComplaintIntake;
use Tests\Modules\Tkt\Support\FakeDocumentStore;
use Tests\Modules\Tkt\Support\FakeKnowledgeBase;
use Tests\Modules\Tkt\Support\FakeWorkflowEngine;
use Tests\Modules\Tkt\Support\SpyCentral;
use Tests\Modules\Tkt\Support\SpyNotifier;
use Tests\TestCase;

require_once __DIR__.'/Support/Fakes.php';

/**
 * MOD-TKT test set-up: real RBC (factory permissions), real AUD, seeded TKT
 * configuration (SLA, L1/L2 teams, mapping); fakes for WFE, NTF, DMS, KBA,
 * ADU intake and the central complaints system.
 */
abstract class TktTestCase extends TestCase
{
    use AssertsAuditTrail, RefreshDatabase;

    public const PELAPOR = ['tkt.tiket.cipta', 'tkt.tiket.lihat', 'tkt.tiket.kemas_kini'];

    public const JURUTEKNIK = ['tkt.tiket.cipta', 'tkt.tiket.lihat', 'tkt.sokongan.lihat', 'tkt.sokongan.kemas_kini'];

    protected SpyNotifier $notifier;

    protected FakeDocumentStore $documents;

    protected FakeWorkflowEngine $workflow;

    protected FakeKnowledgeBase $kb;

    protected FakeArticleDrafts $drafts;

    protected FakeComplaintIntake $complaints;

    protected SpyCentral $central;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Referer', 'http://localhost:3000');
        $this->app->instance(Notifier::class, $this->notifier = new SpyNotifier);
        $this->app->instance(DocumentStore::class, $this->documents = new FakeDocumentStore);
        $this->app->instance(WorkflowEngine::class, $this->workflow = new FakeWorkflowEngine);
        $this->app->instance(KnowledgeBase::class, $this->kb = new FakeKnowledgeBase);
        $this->app->instance(ArticleDrafts::class, $this->drafts = new FakeArticleDrafts);
        $this->app->instance(ComplaintIntake::class, $this->complaints = new FakeComplaintIntake);
        $this->app->instance(CentralTicketSystem::class, $this->central = new SpyCentral);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param list<string> $permissions */
    protected function user(array $permissions = self::PELAPOR): User
    {
        return User::factory()->withPermissions($permissions)->create(['role' => 'user', 'is_active' => true]);
    }

    protected function pasukan(string $kod = 'L1_MAIS'): Pasukan
    {
        return Pasukan::query()->where('kod', $kod)->firstOrFail();
    }

    /** Active member of a team. */
    protected function ahli(string $peranan = 'juruteknik', string $kod = 'L1_MAIS', array $permissions = self::JURUTEKNIK): User
    {
        $user = $this->user($permissions);
        AhliPasukan::query()->create(['id_pasukan' => $this->pasukan($kod)->id, 'id_pengguna' => $user->id, 'peranan' => $peranan, 'aktif' => true]);

        return $user;
    }

    /** @param array<string, mixed> $data */
    protected function tiket(array $data = [], ?User $pelapor = null, string $saluran = 'aplikasi'): Tiket
    {
        $data += ['tajuk' => 'Gagal menjana invois sewaan', 'penerangan' => 'Butang Jana Invois tidak bertindak balas bagi kontrak bulan ini.', 'kategori' => 'ralat', 'keterukan' => 3, 'modul' => 'pjk'];

        return app(TiketService::class)->buka($data, $saluran, $pelapor ?? $this->user(), null, ['skrin' => 'Senarai Kontrak', 'modul' => $data['modul'], 'url' => 'http://localhost:3000/admin/pjk/kontrak']);
    }

    /** Monday 2026-11-02 09:00 Malaysia time. */
    protected function isnin(string $time = '09:00'): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 '.$time, 'Asia/Kuala_Lumpur'));
    }

    protected function selesai(Tiket $t, User $tech): Tiket
    {
        $svc = app(TiketService::class);
        if ($t->status === 'baharu') {
            $svc->tukarStatus($t, 'disiasat', $tech);
        }

        return $svc->tukarStatus($t, 'selesai', $tech, null, ['punca_akar' => 'Cache konfigurasi lapuk.', 'penyelesaian' => 'Cache dikosongkan dan konfigurasi dimuat semula.']);
    }
}
