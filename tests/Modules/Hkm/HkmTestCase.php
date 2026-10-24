<?php

namespace Tests\Modules\Hkm;

use App\Models\User;
use App\Modules\Hkm\HkmServiceProvider;
use App\Modules\Hkm\Models\HakMilik;
use App\Modules\Hkm\Models\PetiBesi;
use App\Modules\Hkm\Services\TitleService;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Modules\Aud\Concerns\AssertsAuditTrail;
use Tests\Modules\Hkm\Support\FakeWorkflowEngine;
use Tests\Modules\Hkm\Support\SpyNotifier;
use Tests\TestCase;

/**
 * Shared set-up for MOD-HKM tests: real RBC (factory permissions/scopes), real
 * AUD (AssertsAuditTrail), MOD-REF code rows, lots inserted straight into
 * lot_tanah (the Pro model isn't ours), a fake WFE engine (real one in
 * WorkflowIntegrationTest) and a spy notifier.
 */
abstract class HkmTestCase extends TestCase
{
    use AssertsAuditTrail, RefreshDatabase;

    protected FakeWorkflowEngine $workflow;

    protected SpyNotifier $notifier;

    /** Registrar (Pegawai Hartanah). */
    public const PENDAFTAR = ['hkm.hak_milik.lihat', 'hkm.hak_milik.cipta', 'hkm.hak_milik.kemas_kini', 'hkm.hak_milik.eksport'];

    /** Vault custodian. */
    public const PENJAGA = ['hkm.peti_besi.lihat', 'hkm.peti_besi.cipta', 'hkm.peti_besi.kemas_kini', 'hkm.peti_besi.eksport', 'hkm.pengeluaran.cipta', 'hkm.pengeluaran.kemas_kini'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Referer', 'http://localhost:3000');
        $this->app->instance(WorkflowEngine::class, $this->workflow = new FakeWorkflowEngine);
        $this->app->instance(Notifier::class, $this->notifier = new SpyNotifier);
        $this->seedRef();
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
        return $this->user(array_keys(HkmServiceProvider::PERMISSIONS));
    }

    /** Minimal MOD-REF rows (the real ones come from `php artisan ref:seed`). */
    protected function seedRef(): void
    {
        $now = now();
        $base = fn (array $r, int $i) => $r + ['susunan' => $i, 'aktif' => true, 'versi' => 1, 'sah_dari' => '2000-01-01', 'created_at' => $now, 'updated_at' => $now];

        $types = [
            ['GRN', 'Geran', 'GRN', 'negeri', false, true], ['PN', 'Pajakan Negeri', 'PN', 'negeri', false, true],
            ['GM', 'Geran Mukim', 'GM', 'daerah', false, true], ['PM', 'Pajakan Mukim', 'PM', 'daerah', false, true],
            ['HSD', 'Hakmilik Sementara (Pejabat Pendaftaran)', 'HS(D)', 'negeri', true, true], ['HSM', 'Hakmilik Sementara (Mukim)', 'HS(M)', 'daerah', true, true],
            ['TOL', 'Lesen Pendudukan Sementara (TOL)', 'LPS', 'daerah', true, false], ['RIZAB', 'Tanah Rizab', 'RZB', 'tiada', false, false],
        ];
        DB::table('ref_jenis_hak_milik')->insert(array_map(fn ($t, $i) => $base(['kod' => $t[0], 'nama' => $t[1], 'singkatan' => $t[2], 'peringkat' => $t[3], 'sementara' => $t[4], 'hak_milik' => $t[5]], $i), $types, array_keys($types)));
        DB::table('ref_kategori_guna_tanah')->insert(array_map(fn ($k, $i) => $base(['kod' => $k[0], 'nama' => $k[1]], $i), [['PERTANIAN', 'Pertanian'], ['BANGUNAN', 'Bangunan'], ['PERUSAHAAN', 'Perusahaan']], [0, 1, 2]));
        DB::table('ref_daerah')->insert([$base(['kod' => '1008', 'nama' => 'Petaling', 'negeri_kod' => '10', 'kod_pendek' => '08'], 0), $base(['kod' => '1009', 'nama' => 'Sepang', 'negeri_kod' => '10', 'kod_pendek' => '09'], 1)]);
        DB::table('ref_mukim')->insert([$base(['kod' => '100803', 'nama' => 'Damansara', 'daerah_kod' => '1008', 'kod_pendek' => '03'], 0), $base(['kod' => '100901', 'nama' => 'Dengkil', 'daerah_kod' => '1009', 'kod_pendek' => '01'], 1)]);
    }

    /** Tiny lot_tanah insert (MOD-PRO owns the model). */
    protected function lot(array $attributes = []): string
    {
        $id = (string) Str::uuid();
        $mukim = $attributes['kod_mukim'] ?? '100803';
        DB::table('lot_tanah')->insert($attributes + [
            'id' => $id,
            'no_lot' => 'Lot '.random_int(1, 999999),
            'kod_mukim' => $mukim,
            'kod_daerah' => substr($mukim, 0, 4),
            'keluasan' => 1234.5,
            'unit_keluasan' => 'm2',
            'kategori_guna_tanah' => 'BANGUNAN',
            'bahagian_pemilik' => 'wakaf',
            'status_lot' => 'aktif',
            'klasifikasi' => 'dalaman',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /** Register a title directly through the service (no HTTP). */
    protected function title(array $data = [], ?string $lotId = null, ?User $by = null): HakMilik
    {
        $lotId ??= $this->lot(isset($data['kod_mukim']) ? ['kod_mukim' => $data['kod_mukim']] : []);
        unset($data['kod_mukim']);

        return app(TitleService::class)->register($lotId, $data + [
            'no_hak_milik' => 'GRN '.random_int(1, 999999),
            'jenis_hak_milik' => 'GRN',
            'pemilik_berdaftar' => 'Majlis Agama Islam Selangor',
            'tarikh_pendaftaran' => '2001-05-10',
            'syarat_nyata' => 'Bangunan kediaman',
            'keluasan' => '1234.5',
        ], $by);
    }

    protected function vault(?User $custodian = null, string $code = 'PB-01'): PetiBesi
    {
        return PetiBesi::query()->create([
            'kod_peti_besi' => $code, 'nama' => 'Peti Besi Utama', 'lokasi_bangunan' => 'Menara MAIS', 'tingkat' => '3', 'bilik' => 'Bilik Kebal A',
            'id_pegawai_penjaga' => $custodian?->id, 'status_peti_besi' => 'aktif',
        ]);
    }

    /** Simulate MOD-WFE finishing the flow for a change request. */
    protected function completeWorkflow(string $changeId, string $outcome = 'lulus', ?int $decidedBy = null): void
    {
        event(new WorkflowCompleted('wf-x', 'hkm.perubahan_hak_milik', 'perubahan_hak_milik', $changeId, $outcome, $decidedBy, 'Ulasan pelulus'));
    }
}
