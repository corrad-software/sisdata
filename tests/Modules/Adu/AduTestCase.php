<?php

namespace Tests\Modules\Adu;

use App\Models\User;
use App\Modules\Adu\AduServiceProvider;
use App\Modules\Adu\Contracts\ReporterMessenger;
use App\Modules\Adu\Models\Aduan;
use App\Modules\Adu\Services\AduanService;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Wfe\Contracts\ApproverDirectory;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Modules\Adu\Support\FakeApproverDirectory;
use Tests\Modules\Adu\Support\FakeDocumentStore;
use Tests\Modules\Adu\Support\FakeWorkflowEngine;
use Tests\Modules\Adu\Support\SpyMessenger;
use Tests\Modules\Adu\Support\SpyNotifier;
use Tests\Modules\Aud\Concerns\AssertsAuditTrail;
use Tests\TestCase;

/**
 * MOD-ADU test set-up: real RBC (factory permissions/scopes), real AUD,
 * MOD-REF rows, lots + polygons inserted straight into lot_tanah /
 * data_spatial (MOD-PRO owns those models), fake WFE engine (the real one in
 * ClosureWorkflowTest), spy notifier / reporter messenger, in-memory DMS.
 */
abstract class AduTestCase extends TestCase
{
    use AssertsAuditTrail, RefreshDatabase;

    protected FakeWorkflowEngine $workflow;

    protected SpyNotifier $notifier;

    protected SpyMessenger $messenger;

    protected FakeDocumentStore $documents;

    protected FakeApproverDirectory $directory;

    /** District officer. */
    public const PEGAWAI = ['adu.aduan.lihat', 'adu.aduan.cipta', 'adu.aduan.kemas_kini'];

    /** Unit Aduan coordinator. */
    public const UNIT_ADUAN = ['adu.aduan.lihat', 'adu.aduan.cipta', 'adu.aduan.kemas_kini', 'adu.penugasan.kemas_kini', 'adu.sulit.lihat', 'adu.laporan.lihat', 'adu.aduan.eksport'];

    /** Lot centres (lat, lng) — each lot is a ~200 m square around it. */
    public const PUSAT_PETALING = [3.1000, 101.6000];

    public const PUSAT_SEPANG = [2.8500, 101.7000];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Referer', 'http://localhost:3000');
        $this->app->instance(WorkflowEngine::class, $this->workflow = new FakeWorkflowEngine);
        $this->app->instance(Notifier::class, $this->notifier = new SpyNotifier);
        $this->app->instance(ReporterMessenger::class, $this->messenger = new SpyMessenger);
        $this->app->instance(DocumentStore::class, $this->documents = new FakeDocumentStore);
        $this->app->instance(ApproverDirectory::class, $this->directory = new FakeApproverDirectory);
        $this->seedRef();
    }

    /**
     * @param  list<string>  $permissions
     * @param  list<string>|null  $districts
     */
    protected function user(array $permissions = self::PEGAWAI, ?array $districts = null, ?string $role = null): User
    {
        $factory = User::factory()->withPermissions($permissions);
        if ($districts !== null) {
            $factory = $factory->withScope('daerah', $districts);
        }

        $user = $factory->create(['role' => 'user', 'is_active' => true]);
        if ($role !== null) {
            $this->directory->roles[$role][] = $user->id;
        }

        return $user;
    }

    protected function supervise(User $supervisor, User $officer): void
    {
        $this->directory->supervisors[$officer->id][] = $supervisor->id;
    }

    protected function superuser(): User
    {
        return $this->user(array_keys(AduServiceProvider::PERMISSIONS));
    }

    protected function seedRef(): void
    {
        $now = now();
        $base = fn (array $r, int $i) => $r + ['susunan' => $i, 'aktif' => true, 'versi' => 1, 'sah_dari' => '2000-01-01', 'created_at' => $now, 'updated_at' => $now];
        $cats = ['PENCEROBOHAN' => 'Pencerobohan tanah', 'BINAAN_HARAM' => 'Binaan tanpa kebenaran', 'PEMBUANGAN_SAMPAH' => 'Pembuangan sampah haram', 'KEROSAKAN_ASET' => 'Kerosakan aset / bangunan', 'PERTIKAIAN_SEMPADAN' => 'Pertikaian sempadan', 'PENCEMARAN' => 'Pencemaran alam sekitar', 'KESELAMATAN' => 'Keselamatan dan kacau ganggu', 'LAIN' => 'Lain-lain'];
        $i = 0;
        DB::table('ref_kategori_aduan')->insert(array_map(function ($kod, $nama) use ($base, &$i) {
            return $base(['kod' => $kod, 'nama' => $nama], $i++);
        }, array_keys($cats), $cats));
        DB::table('ref_daerah')->insert([$base(['kod' => '1008', 'nama' => 'Petaling', 'negeri_kod' => '10', 'kod_pendek' => '08'], 0), $base(['kod' => '1009', 'nama' => 'Sepang', 'negeri_kod' => '10', 'kod_pendek' => '09'], 1)]);
        DB::table('ref_mukim')->insert([$base(['kod' => '100803', 'nama' => 'Damansara', 'daerah_kod' => '1008', 'kod_pendek' => '03'], 0), $base(['kod' => '100901', 'nama' => 'Dengkil', 'daerah_kod' => '1009', 'kod_pendek' => '01'], 1)]);
        DB::table('ref_pbt')->insert([$base(['kod' => 'MBPJ', 'nama' => 'Majlis Bandaraya Petaling Jaya', 'singkatan' => 'MBPJ', 'jenis' => 'majlis_bandaraya', 'negeri_kod' => '10', 'daerah_kod' => json_encode(['1008'])], 0)]);
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

    /**
     * Intake through the service (no HTTP).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $pengadu
     */
    protected function aduan(array $data = [], string $saluran = 'dalaman', ?array $pengadu = ['nama' => 'Ali bin Abu', 'no_telefon' => '012-3456789', 'emel' => 'ali@contoh.test', 'alamat' => 'No. 1, Jalan Ujian'], ?User $by = null): Aduan
    {
        $data += ['kategori' => 'PENCEROBOHAN', 'keterangan' => 'Terdapat pencerobohan di atas tanah ini oleh pihak tidak dikenali.'];

        return app(AduanService::class)->terima($data, $saluran, $by, null, $pengadu, $pengadu === null);
    }

    protected function completeWorkflow(Aduan $aduan, string $outcome = 'lulus', ?int $decidedBy = null): void
    {
        event(new WorkflowCompleted((string) $aduan->id_instans_aliran, AduanService::FLOW, AduanService::SUBJECT, $aduan->id, $outcome, $decidedBy, 'Ulasan penyelia'));
    }
}
