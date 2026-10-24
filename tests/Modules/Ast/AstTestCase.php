<?php

namespace Tests\Modules\Ast;

use App\Models\User;
use App\Modules\Ast\AstServiceProvider;
use App\Modules\Ast\Models\AsetFizikal;
use App\Modules\Ast\Models\AsetKategori;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Wfe\Contracts\ApproverDirectory;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Modules\Ast\Support\FakeApproverDirectory;
use Tests\Modules\Ast\Support\FakeDocumentStore;
use Tests\Modules\Ast\Support\FakeWorkflowEngine;
use Tests\Modules\Ast\Support\SpyNotifier;
use Tests\Modules\Aud\Concerns\AssertsAuditTrail;
use Tests\TestCase;

/**
 * MOD-AST test set-up: real RBC (factory permissions / scopes), real AUD,
 * real MOD-PRO LotRegistry, real MOD-PTN MaintenanceNeeds, real MOD-GIS /
 * MOD-DSH / MOD-NTF registries; lots + polygons inserted straight into
 * lot_tanah / data_spatial (MOD-PRO owns those models); fake WFE engine,
 * spy notifier, in-memory DMS, explicit supervisor / role maps.
 */
abstract class AstTestCase extends TestCase
{
    use AssertsAuditTrail, RefreshDatabase;

    protected FakeWorkflowEngine $workflow;

    protected SpyNotifier $notifier;

    protected FakeDocumentStore $documents;

    protected FakeApproverDirectory $directory;

    public const UNIT_ASET = ['ast.aset.lihat', 'ast.aset.cipta', 'ast.aset.kemas_kini', 'ast.aset.padam', 'ast.qr.eksport', 'ast.qr.kemas_kini', 'ast.imbasan.lihat',
        'ast.pemeriksaan.lihat', 'ast.pemeriksaan.cipta', 'ast.penyelenggaraan.lihat', 'ast.penyelenggaraan.cipta', 'ast.penyelenggaraan.kemas_kini',
        'ast.kontraktor.lihat', 'ast.kontraktor.kemas_kini', 'ast.peta.lihat', 'ast.laporan.lihat', 'ast.laporan.eksport', 'ast.konfigurasi.lihat'];

    public const PUSAT_PETALING = [3.1000, 101.6000];

    public const PUSAT_SEPANG = [2.8500, 101.7000];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Referer', 'http://localhost:3000');
        $this->app->instance(WorkflowEngine::class, $this->workflow = new FakeWorkflowEngine);
        $this->app->instance(Notifier::class, $this->notifier = new SpyNotifier);
        $this->app->instance(DocumentStore::class, $this->documents = new FakeDocumentStore);
        $this->app->instance(ApproverDirectory::class, $this->directory = new FakeApproverDirectory);
        $this->seedRef();
    }

    /**
     * @param  list<string>  $permissions
     * @param  list<string>|null  $districts
     */
    protected function user(array $permissions, ?array $districts = null): User
    {
        $factory = User::factory()->withPermissions($permissions);
        if ($districts !== null) {
            $factory = $factory->withScope('daerah', $districts);
        }

        return $factory->create(['role' => 'user', 'is_active' => true]);
    }

    protected function unitAset(?array $districts = null): User
    {
        return $this->user(self::UNIT_ASET, $districts);
    }

    protected function admin(): User
    {
        return $this->user(array_keys(AstServiceProvider::PERMISSIONS));
    }

    protected function seedRef(): void
    {
        $now = now();
        $base = fn (array $r, int $i) => $r + ['susunan' => $i, 'aktif' => true, 'versi' => 1, 'sah_dari' => '2000-01-01', 'created_at' => $now, 'updated_at' => $now];
        DB::table('ref_daerah')->insert([$base(['kod' => '1008', 'nama' => 'Petaling', 'negeri_kod' => '10', 'kod_pendek' => '08'], 0), $base(['kod' => '1009', 'nama' => 'Sepang', 'negeri_kod' => '10', 'kod_pendek' => '09'], 1)]);
        DB::table('ref_mukim')->insert([$base(['kod' => '100803', 'nama' => 'Damansara', 'daerah_kod' => '1008', 'kod_pendek' => '03'], 0), $base(['kod' => '100901', 'nama' => 'Dengkil', 'daerah_kod' => '1009', 'kod_pendek' => '01'], 1)]);
    }

    /** Lot with a square polygon of ±$d degrees around ($lat, $lng). */
    protected function lot(string $kodMukim = '100803', ?array $pusat = null, float $d = 0.001): string
    {
        [$lat, $lng] = $pusat ?? ($kodMukim === '100901' ? self::PUSAT_SEPANG : self::PUSAT_PETALING);
        $id = (string) Str::uuid();
        DB::table('lot_tanah')->insert([
            'id' => $id, 'no_lot' => 'Lot '.random_int(1, 999999), 'kod_mukim' => $kodMukim, 'kod_daerah' => substr($kodMukim, 0, 4),
            'keluasan' => 1000, 'unit_keluasan' => 'm2', 'kategori_guna_tanah' => 'BANGUNAN', 'bahagian_pemilik' => 'wakaf',
            'status_lot' => 'aktif', 'klasifikasi' => 'dalaman', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $wkt = sprintf('MULTIPOLYGON(((%1$F %2$F, %3$F %2$F, %3$F %4$F, %1$F %4$F, %1$F %2$F)))', $lng - $d, $lat - $d, $lng + $d, $lat + $d);
        DB::statement("INSERT INTO data_spatial (lot_id, sumber_data, semasa, geometri, created_at, updated_at) VALUES (?, 'sintetik', true, ST_GeomFromText(?, 4326), now(), now())", [$id, $wkt]);

        return $id;
    }

    protected function kategori(string $kod = 'MASJID_SURAU'): AsetKategori
    {
        return AsetKategori::query()->where('kod', $kod)->firstOrFail();
    }

    /** @param array<string, mixed> $extra */
    protected function daftar(User $by, string $lotId, array $extra = []): AsetFizikal
    {
        $id = $this->actingAs($by)->postJson('/api/v1/ast/aset', $extra + [
            'lot_id' => $lotId, 'id_kategori' => $this->kategori()->id, 'nama_aset' => 'Surau Ujian', 'tahun_pembinaan' => 2005, 'tahun_perolehan' => 2006,
            'saiz_keluasan' => 450, 'kos_perolehan' => 250000,
        ])->assertCreated()->json('data.id');

        return AsetFizikal::query()->withoutGlobalScope('access')->findOrFail($id);
    }

    /** Drop the acting user (next request is anonymous, like a phone camera without an account). */
    protected function tanpaAkaun(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    protected function complete(string $flow, string $subjectType, string $subjectId, string $outcome = 'lulus', ?int $by = null, ?string $remarks = null): void
    {
        event(new WorkflowCompleted('wf-x', $flow, $subjectType, $subjectId, $outcome, $by, $remarks));
    }

    /** MOD-PTN maintenance need (MOD-PTN's own table — inserted directly, as PTN's field app would). */
    protected function keperluanPtn(string $lotId, array $extra = []): int
    {
        $lot = DB::table('lot_tanah')->where('id', $lotId)->first();

        return (int) DB::table('keperluan_penyelenggaraan')->insertGetId($extra + [
            'lot_id' => $lotId, 'kod_daerah' => $lot->kod_daerah, 'kod_mukim' => $lot->kod_mukim, 'klasifikasi' => 'dalaman',
            'jenis' => 'pagar', 'keterangan' => 'Pagar sempadan roboh sebahagian', 'keutamaan' => 'tinggi', 'status' => 'dikenal_pasti',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
