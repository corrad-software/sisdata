<?php

namespace Tests\Modules\Kes;

use App\Models\User;
use App\Modules\Cki\Contracts\GrpPembayaran;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Hkm\Contracts\TitleChangeProposals;
use App\Modules\Kes\Contracts\MfaAssurance;
use App\Modules\Kes\Models\KesMahkamah;
use App\Modules\Kes\Services\KesService;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Modules\Aud\Concerns\AssertsAuditTrail;
use Tests\Modules\Kes\Support\FakeDocumentStore;
use Tests\Modules\Kes\Support\FakeGrp;
use Tests\Modules\Kes\Support\FakeMfa;
use Tests\Modules\Kes\Support\FakeWorkflowEngine;
use Tests\Modules\Kes\Support\SpyNotifier;
use Tests\Modules\Kes\Support\SpyTitleProposals;
use Tests\TestCase;

/**
 * MOD-KES test set-up: real RBC (factory permissions/scopes), real AUD and
 * PRO LotRegistry (lots inserted straight into lot_tanah / data_spatial),
 * fake WFE engine, spy notifier, in-memory DMS, fake GRP and HKM proposal
 * spy; MFA faked as satisfied (the real SSO check has its own tests).
 */
abstract class KesTestCase extends TestCase
{
    use AssertsAuditTrail, RefreshDatabase;

    /** Pegawai Bahagian Perundangan. */
    public const PEGAWAI = ['kes.kes.lihat', 'kes.kes.cipta', 'kes.kes.kemas_kini', 'kes.rahsia.lihat', 'kes.laporan.lihat', 'kes.laporan.eksport'];

    /** Ketua Bahagian Perundangan. */
    public const KETUA = [...self::PEGAWAI, 'kes.kes.lulus', 'kes.pihak.lihat', 'kes.log_capaian.lihat', 'kes.konfigurasi.lihat', 'kes.konfigurasi.kemas_kini'];

    protected FakeWorkflowEngine $workflow;

    protected SpyNotifier $notifier;

    protected FakeDocumentStore $documents;

    protected FakeGrp $grp;

    protected FakeMfa $mfa;

    protected SpyTitleProposals $titles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Referer', 'http://localhost:3000');
        $this->app->instance(WorkflowEngine::class, $this->workflow = new FakeWorkflowEngine);
        $this->app->instance(Notifier::class, $this->notifier = new SpyNotifier);
        $this->app->instance(DocumentStore::class, $this->documents = new FakeDocumentStore);
        $this->app->instance(GrpPembayaran::class, $this->grp = new FakeGrp);
        $this->app->instance(MfaAssurance::class, $this->mfa = new FakeMfa);
        $this->app->instance(TitleChangeProposals::class, $this->titles = new SpyTitleProposals);
        $this->seedRef();
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

    protected function seedRef(): void
    {
        $now = now();
        $base = fn (array $r, int $i) => $r + ['susunan' => $i, 'aktif' => true, 'versi' => 1, 'sah_dari' => '2000-01-01', 'created_at' => $now, 'updated_at' => $now];
        DB::table('ref_daerah')->insert([$base(['kod' => '1008', 'nama' => 'Petaling', 'negeri_kod' => '10', 'kod_pendek' => '08'], 0), $base(['kod' => '1009', 'nama' => 'Sepang', 'negeri_kod' => '10', 'kod_pendek' => '09'], 1)]);
        DB::table('ref_mukim')->insert([$base(['kod' => '100803', 'nama' => 'Damansara', 'daerah_kod' => '1008', 'kod_pendek' => '03'], 0), $base(['kod' => '100901', 'nama' => 'Dengkil', 'daerah_kod' => '1009', 'kod_pendek' => '01'], 1)]);
    }

    protected function lot(string $kodMukim = '100803', string $kategori = 'BANGUNAN'): string
    {
        [$lat, $lng] = $kodMukim === '100901' ? [2.85, 101.7] : [3.1, 101.6];
        $lat += random_int(0, 1000) / 100000;
        $id = (string) Str::uuid();
        DB::table('lot_tanah')->insert([
            'id' => $id, 'no_lot' => 'Lot '.random_int(1, 999999), 'kod_mukim' => $kodMukim, 'kod_daerah' => substr($kodMukim, 0, 4),
            'keluasan' => 1000, 'unit_keluasan' => 'm2', 'kategori_guna_tanah' => $kategori, 'bahagian_pemilik' => 'wakaf',
            'status_lot' => 'aktif', 'klasifikasi' => 'dalaman', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $d = 0.001;
        $wkt = sprintf('MULTIPOLYGON(((%1$F %2$F, %3$F %2$F, %3$F %4$F, %1$F %4$F, %1$F %2$F)))', $lng - $d, $lat - $d, $lng + $d, $lat + $d);
        DB::statement("INSERT INTO data_spatial (lot_id, sumber_data, semasa, geometri, created_at, updated_at) VALUES (?, 'sintetik', true, ST_GeomFromText(?, 4326), now(), now())", [$id, $wkt]);

        return $id;
    }

    /** @return array<string, mixed> every A-12.02 field */
    protected function dataKes(array $lotIds = [], array $extra = []): array
    {
        return [
            'no_kes' => 'BA-22NCvC-'.random_int(100, 999).'-03/2026',
            'tajuk' => 'MAIS lwn Penceroboh (ujian)',
            'jenis' => 'pencerobohan',
            'mahkamah' => 'Mahkamah Tinggi Shah Alam',
            'peringkat_mahkamah' => 'tinggi',
            'tarikh_pemfailan' => now()->subMonths(2)->toDateString(),
            'ringkasan_tuntutan' => 'Tuntutan milikan kosong dan ganti rugi.',
            'nilai_tuntutan' => '250000.50',
            'peranan_mais' => 'plaintif',
            'lot_ids' => $lotIds,
            'pihak' => [
                ['nama_pihak' => 'Majlis Agama Islam Selangor', 'jenis_pihak' => 'mais', 'peranan' => 'plaintif', 'adalah_mais' => true],
                ['nama_pihak' => 'Ali bin Abu', 'jenis_pihak' => 'individu', 'peranan' => 'defendan', 'no_pengenalan' => '800101-10-1234', 'maklumat_perhubungan' => '012-3456789', 'status_wakilan' => 'diwakili'],
            ],
            'peguam' => [['nama_peguam' => 'Encik Peguam Lawan', 'nama_firma' => 'Tetuan Lawan & Co', 'no_telefon' => '03-12345678', 'emel' => 'lawan@contoh.test', 'pihak_indeks' => 1]],
            ...$extra,
        ];
    }

    /** Register through the service (no HTTP). */
    protected function kes(array $lotIds = [], ?User $by = null, array $extra = []): KesMahkamah
    {
        $by ??= $this->user(self::KETUA);

        return app(KesService::class)->daftar($this->dataKes($lotIds, $extra), $by);
    }

    protected function selesaiAliran(string $flow, string $subjectType, string|int $subjectId, string $instanceId, string $outcome = 'lulus', ?int $by = null): void
    {
        event(new WorkflowCompleted($instanceId, $flow, $subjectType, (string) $subjectId, $outcome, $by, 'Ulasan pelulus'));
    }
}
