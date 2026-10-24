<?php

namespace Tests\Modules\Tsu;

use App\Models\User;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Tsu\Models\KosUrusan;
use App\Modules\Tsu\Models\UrusanPeringkat;
use App\Modules\Tsu\Models\UrusanTanah;
use App\Modules\Tsu\Support\AliranTsu;
use App\Modules\Tsu\TsuServiceProvider;
use App\Modules\Wfe\Contracts\ApproverDirectory;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\StepDecided;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Modules\Aud\Concerns\AssertsAuditTrail;
use Tests\Modules\Tsu\Support\FakeApproverDirectory;
use Tests\Modules\Tsu\Support\FakeDocumentStore;
use Tests\Modules\Tsu\Support\FakeWorkflowEngine;
use Tests\Modules\Tsu\Support\SpyNotifier;
use Tests\TestCase;

/**
 * MOD-TSU test set-up: real RBC (factory permissions / scopes), real AUD,
 * real MOD-PRO LotRegistry, MOD-PTN LotMasterUpdates stand-in, MOD-REF rows,
 * lots + polygons inserted straight into lot_tanah / data_spatial (MOD-PRO
 * owns those models), fake WFE engine (outcomes dispatched as WFE events),
 * spy notifier, in-memory DMS.
 */
abstract class TsuTestCase extends TestCase
{
    use AssertsAuditTrail, RefreshDatabase;

    protected FakeWorkflowEngine $workflow;

    protected SpyNotifier $notifier;

    protected FakeDocumentStore $documents;

    protected FakeApproverDirectory $directory;

    public const PEGAWAI = ['tsu.urusan.lihat', 'tsu.urusan.cipta', 'tsu.urusan.kemas_kini', 'tsu.kos.lihat', 'tsu.ptd.kemas_kini', 'tsu.ukur.lihat', 'tsu.sebutharga.lihat', 'tsu.pemindahan.cipta', 'tsu.laporan.lihat', 'tsu.peta.lihat', 'tsu.panel.lihat', 'tsu.rahsia.kemas_kini', 'tsu.rahsia.lihat'];

    public const KEWANGAN = ['tsu.urusan.lihat', 'tsu.kos.lihat', 'tsu.kewangan.kemas_kini'];

    public const GIS = ['tsu.urusan.lihat', 'tsu.ukur.lihat', 'tsu.hasil_ukur.cipta', 'tsu.hasil_ukur.lulus', 'tsu.peta.lihat'];

    public const PEROLEHAN = ['tsu.urusan.lihat', 'tsu.sebutharga.lihat', 'tsu.sebutharga.cipta', 'tsu.panel.lihat', 'tsu.panel.kemas_kini', 'tsu.ukur.lihat'];

    public const PENILAI = ['tsu.urusan.lihat', 'tsu.sebutharga.lihat', 'tsu.sebutharga.kemas_kini'];

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
        $factory = User::factory()->withPermissions(array_values(array_unique($permissions)));
        if ($districts !== null) {
            $factory = $factory->withScope('daerah', $districts);
        }

        return $factory->create(['role' => 'user', 'is_active' => true]);
    }

    protected function admin(): User
    {
        return $this->user(array_keys(TsuServiceProvider::PERMISSIONS));
    }

    protected function seedRef(): void
    {
        $now = now();
        $base = fn (array $r, int $i) => $r + ['susunan' => $i, 'aktif' => true, 'versi' => 1, 'sah_dari' => '2000-01-01', 'created_at' => $now, 'updated_at' => $now];
        DB::table('ref_daerah')->insert([$base(['kod' => '1008', 'nama' => 'Petaling', 'negeri_kod' => '10', 'kod_pendek' => '08'], 0), $base(['kod' => '1009', 'nama' => 'Sepang', 'negeri_kod' => '10', 'kod_pendek' => '09'], 1)]);
        DB::table('ref_mukim')->insert([$base(['kod' => '100803', 'nama' => 'Damansara', 'daerah_kod' => '1008', 'kod_pendek' => '03'], 0), $base(['kod' => '100901', 'nama' => 'Dengkil', 'daerah_kod' => '1009', 'kod_pendek' => '01'], 1)]);
    }

    /** Lot with a square polygon of ±$d degrees around ($lat, $lng). */
    protected function lot(string $kodMukim = '100803', ?array $pusat = null, float $d = 0.001, string $bahagian = 'hartanah', ?float $keluasan = null): string
    {
        [$lat, $lng] = $pusat ?? ($kodMukim === '100901' ? self::PUSAT_SEPANG : self::PUSAT_PETALING);
        $id = (string) Str::uuid();
        $wkt = sprintf('MULTIPOLYGON(((%1$F %2$F, %3$F %2$F, %3$F %4$F, %1$F %4$F, %1$F %2$F)))', $lng - $d, $lat - $d, $lng + $d, $lat + $d);
        $area = $keluasan ?? (float) DB::selectOne('SELECT ST_Area(ST_GeomFromText(?, 4326)::geography) AS m2', [$wkt])->m2;
        DB::table('lot_tanah')->insert([
            'id' => $id, 'no_lot' => 'Lot '.random_int(1, 999999), 'kod_mukim' => $kodMukim, 'kod_daerah' => substr($kodMukim, 0, 4),
            'keluasan' => round($area, 2), 'unit_keluasan' => 'm2', 'kategori_guna_tanah' => 'PERTANIAN', 'bahagian_pemilik' => $bahagian,
            'status_lot' => 'aktif', 'klasifikasi' => 'dalaman', 'versi_rekod' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::statement("INSERT INTO data_spatial (lot_id, sumber_data, semasa, geometri, created_at, updated_at) VALUES (?, 'sintetik', true, ST_GeomFromText(?, 4326), now(), now())", [$id, $wkt]);

        return $id;
    }

    /** @param list<string> $lotIds @param array<string, mixed> $extra */
    protected function daftar(User $user, string $jenis, array $lotIds, array $extra = []): UrusanTanah
    {
        $res = $this->actingAs($user)->postJson('/api/v1/tsu/urusan', ['jenis_urusan' => $jenis, 'lot_ids' => $lotIds] + $extra)->assertCreated();

        return UrusanTanah::query()->withoutGlobalScope('access')->findOrFail($res->json('data.id'));
    }

    /** @param array<string, mixed> $extra */
    protected function dokumen(User $user, UrusanTanah $u, string $kategori, array $extra = []): TestResponse
    {
        return $this->actingAs($user)->post('/api/v1/tsu/urusan/'.$u->id.'/dokumen', $extra + [
            'fail' => UploadedFile::fake()->create($kategori.'.pdf', 20, 'application/pdf'), 'kategori_dokumen' => $kategori,
        ], ['Accept' => 'application/json']);
    }

    protected function semasa(UrusanTanah $u): UrusanPeringkat
    {
        $u->refresh();

        return UrusanPeringkat::query()->where('urusan_id', $u->id)->where('no_urutan', $u->peringkat_semasa)->firstOrFail();
    }

    protected function step(string $instance, string $flow, ?string $stage, string $action, int $userId, ?string $justification = null): void
    {
        event(new StepDecided($instance, 1, $flow, $stage, $action, $userId, null, $justification));
    }

    protected function complete(string $flow, string $subjectType, string $subjectId, string $outcome = 'lulus', ?int $by = null, ?string $remarks = null, string $instance = 'wf-x'): void
    {
        event(new WorkflowCompleted($instance, $flow, $subjectType, $subjectId, $outcome, $by, $remarks));
    }

    /**
     * Satisfy the current stage's generic requirements (documents, references,
     * costs paid) and complete it; approval stages are approved via WFE events
     * by $approver (with MFA-flagged step for the highest disposal stage).
     */
    protected function selesaikan(UrusanTanah $u, User $officer, User $approver): UrusanPeringkat
    {
        $p = $this->semasa($u);
        foreach ($p->senarai_dokumen_wajib as $kat) {
            $extra = $kat === 'laporan_penilaian' ? ['nilai_penilaian' => '850000.00', 'tarikh_dokumen' => now()->subMonth()->toDateString()] : [];
            $this->dokumen($officer, $u, $kat, $extra)->assertCreated();
        }
        if ($p->kesan === 'perlu_rujukan_etanah') {
            $this->actingAs($officer)->putJson('/api/v1/tsu/urusan/'.$u->id, ['no_rujukan_etanah' => 'ET-'.random_int(1000, 9999)])->assertOk();
        }
        if ($p->kesan === 'perlu_rujukan_ptd' && ! $u->refresh()->no_rujukan_ptd) {
            $this->actingAs($officer)->putJson('/api/v1/tsu/urusan/'.$u->id, ['no_rujukan_ptd' => 'PTD/MANUAL/'.random_int(1000, 9999)])->assertOk();
        }
        if ($p->kesan === 'perlu_kos_dibayar') {
            KosUrusan::query()->where('peringkat_id', $p->id)->update(['status_bayaran' => 'dibayar', 'no_resit' => 'R-1', 'tarikh_bayaran' => today()]);
        }
        $res = $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/peringkat/selesai')->assertOk();
        if ($res->json('data.status') === 'menunggu_kelulusan') {
            $instance = (string) $res->json('data.id_aliran_kerja');
            $flow = $u->jenis_urusan === 'pelupusan' ? AliranTsu::PELUPUSAN : AliranTsu::URUSAN;
            $stage = $u->jenis_urusan === 'pelupusan' ? AliranTsu::PERINGKAT_TERTINGGI : 'kelulusan_ketua_bahagian';
            $this->step($instance, $flow, $stage, 'lulus', $approver->id, 'Diluluskan selepas semakan dokumen.');
            $this->complete($flow, AliranTsu::SUBJEK_PERINGKAT, (string) $p->id, 'lulus', $approver->id, 'Diluluskan selepas semakan dokumen.', $instance);
        }

        return $p->refresh();
    }

    /** Run selesaikan() until the current stage has kod $kod (or the urusan leaves active). */
    protected function sampaiKe(UrusanTanah $u, string $kod, User $officer, User $approver): void
    {
        for ($i = 0; $i < 20 && $this->semasa($u)->kod !== $kod; $i++) {
            $this->selesaikan($u, $officer, $approver);
        }
        $this->assertSame($kod, $this->semasa($u)->kod);
    }

    /** GeoJSON square polygon around a centre (lng/lat). */
    protected function petak(float $lat, float $lng, float $d): string
    {
        return (string) json_encode(['type' => 'FeatureCollection', 'features' => [['type' => 'Feature', 'properties' => [], 'geometry' => ['type' => 'Polygon', 'coordinates' => [[[$lng - $d, $lat - $d], [$lng + $d, $lat - $d], [$lng + $d, $lat + $d], [$lng - $d, $lat + $d], [$lng - $d, $lat - $d]]]]]]]);
    }
}
