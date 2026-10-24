<?php

namespace Tests\Modules\Pjk;

use App\Models\User;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\Penyewa;
use App\Modules\Pjk\Models\PremisAset;
use App\Modules\Pjk\PjkServiceProvider;
use App\Modules\Pjk\Support\Aliran;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Modules\Aud\Concerns\AssertsAuditTrail;
use Tests\Modules\Pjk\Support\FakeDocumentStore;
use Tests\Modules\Pjk\Support\FakeWorkflowEngine;
use Tests\Modules\Pjk\Support\SpyNotifier;
use Tests\TestCase;

/**
 * MOD-PJK tests: real RBC (factory permissions / scopes), real AUD, real
 * PRO LotRegistry over lots inserted into lot_tanah; WFE, DMS and NTF faked at
 * their contracts; the mock payment gateway / GRP / e-Sewa adapters are real.
 */
abstract class PjkTestCase extends TestCase
{
    use AssertsAuditTrail, RefreshDatabase;

    /** Unit Sewaan registrar. */
    public const PENDAFTAR = [
        'pjk.kontrak.lihat', 'pjk.kontrak.cipta', 'pjk.kontrak.kemas_kini', 'pjk.penyewa.lihat', 'pjk.penyewa.cipta', 'pjk.penyewa.kemas_kini',
        'pjk.premis.lihat', 'pjk.premis.cipta', 'pjk.premis.kemas_kini', 'pjk.lejar.lihat', 'pjk.lejar.kemas_kini', 'pjk.lejar.eksport',
        'pjk.bayaran.cipta', 'pjk.permohonan.lihat', 'pjk.permohonan.cipta', 'pjk.permohonan.kemas_kini', 'pjk.surat.cipta', 'pjk.deposit.kemas_kini',
    ];

    /** Bahagian Kewangan. */
    public const KEWANGAN = ['pjk.kontrak.lihat', 'pjk.lejar.lihat', 'pjk.bayaran.lulus', 'pjk.rekonsiliasi.lihat', 'pjk.rekonsiliasi.cipta', 'pjk.rekonsiliasi.kemas_kini', 'pjk.agihan.lihat', 'pjk.agihan.lulus'];

    protected FakeWorkflowEngine $workflow;

    protected SpyNotifier $notifier;

    protected FakeDocumentStore $dms;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-15 09:00:00');
        $this->withHeader('Referer', 'http://localhost:3000');
        $this->app->instance(WorkflowEngine::class, $this->workflow = new FakeWorkflowEngine);
        $this->app->instance(Notifier::class, $this->notifier = new SpyNotifier);
        $this->app->instance(DocumentStore::class, $this->dms = new FakeDocumentStore);
        Mail::fake();
        $this->seedRef();
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
    protected function user(array $permissions = self::PENDAFTAR, ?array $districts = null): User
    {
        $factory = User::factory()->withPermissions($permissions);
        if ($districts !== null) {
            $factory = $factory->withScope('daerah', $districts);
        }

        return $factory->create(['role' => 'user', 'is_active' => true]);
    }

    protected function superuser(): User
    {
        return $this->user(array_keys(PjkServiceProvider::PERMISSIONS));
    }

    protected function seedRef(): void
    {
        $now = now();
        $base = fn (array $r, int $i) => $r + ['susunan' => $i, 'aktif' => true, 'versi' => 1, 'sah_dari' => '2000-01-01', 'created_at' => $now, 'updated_at' => $now];
        DB::table('ref_daerah')->insert([$base(['kod' => '1008', 'nama' => 'Petaling', 'negeri_kod' => '10', 'kod_pendek' => '08'], 0), $base(['kod' => '1009', 'nama' => 'Sepang', 'negeri_kod' => '10', 'kod_pendek' => '09'], 1)]);
        DB::table('ref_mukim')->insert([$base(['kod' => '100803', 'nama' => 'Damansara', 'daerah_kod' => '1008', 'kod_pendek' => '03'], 0), $base(['kod' => '100901', 'nama' => 'Dengkil', 'daerah_kod' => '1009', 'kod_pendek' => '01'], 1)]);
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

    /** @param array<string, mixed> $attributes */
    protected function premis(array $attributes = [], ?string $lotId = null): PremisAset
    {
        $lotId ??= $this->lot(isset($attributes['kod_mukim']) ? ['kod_mukim' => $attributes['kod_mukim']] : []);
        $lot = DB::table('lot_tanah')->where('id', $lotId)->first();
        unset($attributes['kod_mukim']);

        return PremisAset::query()->withoutGlobalScope('access')->create($attributes + [
            'lot_id' => $lotId, 'kod_premis' => 'PRM-'.Str::upper(Str::random(6)), 'nama_premis' => 'Kedai Wakaf Ujian', 'jenis_aset' => 'unit_kedai',
            'kategori_hartanah' => 'premis_perniagaan', 'keluasan' => '120.00', 'status_premis' => 'kosong', 'tarikh_status' => '2026-01-01',
            'kadar_tawaran' => '1500.00', 'kod_daerah' => $lot->kod_daerah, 'kod_mukim' => $lot->kod_mukim, 'bahagian' => $lot->bahagian_pemilik, 'papar_portal' => true,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    protected function penyewa(array $attributes = []): Penyewa
    {
        static $n = 0;
        $n++;

        return Penyewa::query()->create($attributes + [
            'jenis_entiti' => 'individu', 'nama' => 'Penyewa Ujian '.$n, 'no_pengenalan' => '990000-99-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'alamat' => 'Jalan Ujian', 'telefon' => '0123456789', 'emel' => 'penyewa'.$n.'@contoh.invalid', 'klasifikasi' => 'sulit',
        ]);
    }

    /**
     * Create a draft through the API.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function draft(User $user, array $overrides = []): Kontrak
    {
        $premis = isset($overrides['premis_id']) ? null : $this->premis();
        $payload = $overrides + [
            'premis_id' => $premis?->id, 'penyewa_id' => $this->penyewa()->id, 'tarikh_mula' => '2026-01-01', 'tarikh_tamat' => '2027-12-31',
            'kadar' => '1500.00', 'deposit' => '3000.00', 'kitaran_caj' => 'bulanan', 'terma' => 'Terma ujian',
        ];
        $res = $this->actingAs($user)->postJson('/api/v1/pjk/kontrak', $payload)->assertCreated();

        return Kontrak::query()->withoutGlobalScope('access')->findOrFail($res->json('data.id'));
    }

    /** Draft → submitted → WFE lulus (decided by $approver) → active. */
    protected function active(User $user, ?User $approver = null, array $overrides = []): Kontrak
    {
        $k = $this->draft($user, $overrides);
        $this->actingAs($user)->postJson("/api/v1/pjk/kontrak/{$k->id}/hantar")->assertOk();
        $this->complete(Aliran::KONTRAK, 'kontrak_pajakan', $k->id, 'lulus', ($approver ?? $this->user(['pjk.kontrak.lihat']))->id);

        return $k->refresh();
    }

    protected function complete(string $flow, string $subject, string|int $id, string $outcome = 'lulus', ?int $decidedBy = null): void
    {
        event(new WorkflowCompleted('wf-x', $flow, $subject, (string) $id, $outcome, $decidedBy, 'Ulasan pelulus'));
    }
}
