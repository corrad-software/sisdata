<?php

namespace Tests\Modules\Cki;

use App\Models\User;
use App\Modules\Cki\CkiServiceProvider;
use App\Modules\Cki\Contracts\GrpPembayaran;
use App\Modules\Cki\Contracts\PenyediaCukaiBerpusat;
use App\Modules\Cki\Integration\MockGrpPembayaran;
use App\Modules\Cki\Integration\MockPenyediaMbi;
use App\Modules\Cki\Models\Cukai;
use App\Modules\Cki\Models\Liputan;
use App\Modules\Cki\Models\Obligasi;
use App\Modules\Cki\Services\BilService;
use App\Modules\Cki\Services\LiputanService;
use App\Modules\Cki\Services\ObligasiService;
use App\Modules\Cki\Support\Aliran;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Tests\Modules\Aud\Concerns\AssertsAuditTrail;
use Tests\Modules\Pjk\Support\FakeDocumentStore;
use Tests\Modules\Pjk\Support\FakeWorkflowEngine;
use Tests\Modules\Pjk\Support\SpyNotifier;
use Tests\TestCase;

/**
 * MOD-CKI tests: real RBC (factory permissions / scopes), real AUD, real PRO
 * LotRegistry over lots inserted into lot_tanah, real mock MBI / GRP
 * adapters; WFE, DMS and NTF faked at their contracts (the spies from the
 * MOD-PJK test support are reused).
 */
abstract class CkiTestCase extends TestCase
{
    use AssertsAuditTrail, RefreshDatabase;

    /** Pegawai daerah / Bahagian Pengurusan Hartanah. */
    public const PEGAWAI = [
        'cki.obligasi.lihat', 'cki.obligasi.cipta', 'cki.obligasi.kemas_kini', 'cki.bil.lihat', 'cki.bil.cipta', 'cki.bil.kemas_kini', 'cki.bil.eksport',
        'cki.bayaran.cipta', 'cki.konflik.kemas_kini', 'cki.laporan.lihat', 'cki.laporan.eksport',
    ];

    protected FakeWorkflowEngine $workflow;

    protected SpyNotifier $notifier;

    protected FakeDocumentStore $dms;

    protected MockPenyediaMbi $mbi;

    protected MockGrpPembayaran $grp;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-15 09:00:00');
        Sleep::fake();
        Cache::flush();
        $this->withHeader('Referer', 'http://localhost:3000');
        $this->app->instance(WorkflowEngine::class, $this->workflow = new FakeWorkflowEngine);
        $this->app->instance(Notifier::class, $this->notifier = new SpyNotifier);
        $this->app->instance(DocumentStore::class, $this->dms = new FakeDocumentStore);
        $this->app->instance(PenyediaCukaiBerpusat::class, $this->mbi = new MockPenyediaMbi);
        $this->app->instance(GrpPembayaran::class, $this->grp = new MockGrpPembayaran);
        $this->seedRef();
        app(LiputanService::class)->lalai();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @param  list<string>  $permissions
     * @param  list<string>|null  $districts
     */
    protected function user(array $permissions = self::PEGAWAI, ?array $districts = null): User
    {
        $factory = User::factory()->withPermissions($permissions);
        if ($districts !== null) {
            $factory = $factory->withScope('daerah', $districts);
        }

        return $factory->create(['role' => 'user', 'is_active' => true]);
    }

    protected function superuser(): User
    {
        return $this->user(array_keys(CkiServiceProvider::PERMISSIONS));
    }

    protected function seedRef(): void
    {
        $now = now();
        $base = fn (array $r, int $i) => $r + ['susunan' => $i, 'aktif' => true, 'versi' => 1, 'sah_dari' => '2000-01-01', 'created_at' => $now, 'updated_at' => $now];
        DB::table('ref_daerah')->insert([$base(['kod' => '1008', 'nama' => 'Petaling', 'negeri_kod' => '10', 'kod_pendek' => '08'], 0), $base(['kod' => '1009', 'nama' => 'Sepang', 'negeri_kod' => '10', 'kod_pendek' => '09'], 1)]);
        DB::table('ref_mukim')->insert([$base(['kod' => '100803', 'nama' => 'Damansara', 'daerah_kod' => '1008', 'kod_pendek' => '03'], 0), $base(['kod' => '100901', 'nama' => 'Dengkil', 'daerah_kod' => '1009', 'kod_pendek' => '01'], 1)]);
        DB::table('ref_pbt')->insert([
            $base(['kod' => 'MBPJ', 'nama' => 'Majlis Bandaraya Petaling Jaya', 'singkatan' => 'MBPJ', 'jenis' => 'majlis_bandaraya', 'negeri_kod' => '10', 'daerah_kod' => json_encode(['1008'])], 0),
            $base(['kod' => 'MPSEPANG', 'nama' => 'Majlis Perbandaran Sepang', 'singkatan' => 'MPSepang', 'jenis' => 'majlis_perbandaran', 'negeri_kod' => '10', 'daerah_kod' => json_encode(['1009'])], 1),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    protected function lot(array $attributes = [], bool $geometry = false): string
    {
        $id = (string) Str::uuid();
        $mukim = $attributes['kod_mukim'] ?? '100803';
        DB::table('lot_tanah')->insert($attributes + [
            'id' => $id, 'no_lot' => 'PT '.random_int(1, 999999), 'kod_mukim' => $mukim, 'kod_daerah' => substr($mukim, 0, 4),
            'keluasan' => 1500, 'unit_keluasan' => 'm2', 'bahagian_pemilik' => 'wakaf', 'status_lot' => 'aktif', 'klasifikasi' => 'dalaman',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($geometry) {
            DB::statement("INSERT INTO data_spatial (lot_id, sumber_data, semasa, created_at, updated_at, geometri) VALUES (?, 'sintetik', true, now(), now(), ST_GeomFromText('MULTIPOLYGON(((101.5 3.0, 101.501 3.0, 101.501 3.001, 101.5 3.001, 101.5 3.0)))', 4326))", [$id]);
        }

        return $id;
    }

    protected function obligasi(string $jenis = 'tanah', ?string $lotId = null, ?string $akaun = null, string $mukim = '100803'): Obligasi
    {
        return app(ObligasiService::class)->cipta([
            'lot_id' => $lotId ?? $this->lot(['kod_mukim' => $mukim]), 'jenis_cukai' => $jenis, 'no_akaun_cukai' => $akaun ?? 'AK'.random_int(100000, 999999),
        ], null);
    }

    /** @param array<string, mixed> $data */
    protected function bil(?Obligasi $o = null, array $data = [], string $sumber = 'manual'): Cukai
    {
        $o ??= $this->obligasi();

        return app(BilService::class)->cipta($o, $data + [
            'tahun_taksiran' => 2026, 'no_bil' => 'B-'.random_int(100000, 999999), 'amaun_pokok' => '1200.00', 'amaun_denda' => '0.00',
            'tarikh_bil' => '2026-01-02', 'tarikh_akhir' => '2026-12-31',
        ], null, $sumber);
    }

    protected function agensi(string $kod): Liputan
    {
        return Liputan::query()->where('kod_agensi', $kod)->firstOrFail();
    }

    protected function complete(string $subjectId, string $outcome = 'lulus', ?int $decidedBy = null): void
    {
        event(new WorkflowCompleted('wf-x', Aliran::BAYARAN, 'transaksi_bayaran_cukai', $subjectId, $outcome, $decidedBy ?? $this->user(['cki.bil.lihat'])->id, 'Diluluskan'));
    }
}
