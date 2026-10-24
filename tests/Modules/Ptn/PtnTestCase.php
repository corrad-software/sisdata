<?php

namespace Tests\Modules\Ptn;

use App\Models\User;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Ntf\Contracts\RecipientDirectory;
use App\Modules\Ptn\Models\JadualPemantauan;
use App\Modules\Ptn\Models\RekodLawatan;
use App\Modules\Ptn\Models\TemplatSenaraiSemak;
use App\Modules\Ptn\PtnServiceProvider;
use App\Modules\Wfe\Contracts\ApproverDirectory;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Modules\Aud\Concerns\AssertsAuditTrail;
use Tests\Modules\Ptn\Support\FakeApproverDirectory;
use Tests\Modules\Ptn\Support\FakeDocumentStore;
use Tests\Modules\Ptn\Support\FakeRecipientDirectory;
use Tests\Modules\Ptn\Support\FakeWorkflowEngine;
use Tests\Modules\Ptn\Support\SpyNotifier;
use Tests\TestCase;

/**
 * MOD-PTN test set-up: real RBC (factory permissions/scopes), real AUD,
 * real MOD-ADU ComplaintIntake, MOD-REF rows, lots + polygons inserted
 * straight into lot_tanah / data_spatial (MOD-PRO owns those models), fake
 * WFE engine, spy notifier, in-memory DMS, explicit role/supervisor maps.
 */
abstract class PtnTestCase extends TestCase
{
    use AssertsAuditTrail, RefreshDatabase;

    protected FakeWorkflowEngine $workflow;

    protected SpyNotifier $notifier;

    protected FakeDocumentStore $documents;

    protected FakeApproverDirectory $directory;

    protected FakeRecipientDirectory $recipients;

    public const LAPANGAN = ['ptn.lapangan.lihat', 'ptn.lapangan.kemas_kini'];

    public const PENYELIA = ['ptn.jadual.lihat', 'ptn.jadual.cipta', 'ptn.jadual.kemas_kini', 'ptn.lawatan.lihat', 'ptn.lawatan.kemas_kini', 'ptn.isu.lihat', 'ptn.isu.cipta', 'ptn.isu.kemas_kini', 'ptn.risiko.lihat', 'ptn.peta.lihat', 'ptn.laporan.lihat', 'ptn.laporan.eksport'];

    /** Lot centres (lat, lng) — each lot is a ~220 m square around it. */
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
        $this->app->instance(RecipientDirectory::class, $this->recipients = new FakeRecipientDirectory);
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

    protected function admin(): User
    {
        return $this->user(array_keys(PtnServiceProvider::PERMISSIONS));
    }

    protected function seedRef(): void
    {
        $now = now();
        $base = fn (array $r, int $i) => $r + ['susunan' => $i, 'aktif' => true, 'versi' => 1, 'sah_dari' => '2000-01-01', 'created_at' => $now, 'updated_at' => $now];
        $cats = ['PENCEROBOHAN' => 'Pencerobohan tanah', 'BINAAN_HARAM' => 'Binaan tanpa kebenaran', 'PEMBUANGAN_SAMPAH' => 'Pembuangan sampah haram', 'KEROSAKAN_ASET' => 'Kerosakan aset / bangunan', 'PERTIKAIAN_SEMPADAN' => 'Pertikaian sempadan', 'LAIN' => 'Lain-lain'];
        $i = 0;
        DB::table('ref_kategori_aduan')->insert(array_map(function ($kod, $nama) use ($base, &$i) {
            return $base(['kod' => $kod, 'nama' => $nama], $i++);
        }, array_keys($cats), $cats));
        DB::table('ref_daerah')->insert([$base(['kod' => '1008', 'nama' => 'Petaling', 'negeri_kod' => '10', 'kod_pendek' => '08'], 0), $base(['kod' => '1009', 'nama' => 'Sepang', 'negeri_kod' => '10', 'kod_pendek' => '09'], 1)]);
        DB::table('ref_mukim')->insert([$base(['kod' => '100803', 'nama' => 'Damansara', 'daerah_kod' => '1008', 'kod_pendek' => '03'], 0), $base(['kod' => '100901', 'nama' => 'Dengkil', 'daerah_kod' => '1009', 'kod_pendek' => '01'], 1)]);
    }

    /** Lot with a square polygon of ±$d degrees around ($lat, $lng). */
    protected function lot(string $kodMukim = '100803', ?array $pusat = null, float $d = 0.001, string $kategori = 'PERTANIAN'): string
    {
        [$lat, $lng] = $pusat ?? ($kodMukim === '100901' ? self::PUSAT_SEPANG : self::PUSAT_PETALING);
        $id = (string) Str::uuid();
        DB::table('lot_tanah')->insert([
            'id' => $id, 'no_lot' => 'Lot '.random_int(1, 999999), 'kod_mukim' => $kodMukim, 'kod_daerah' => substr($kodMukim, 0, 4),
            'keluasan' => 1000, 'unit_keluasan' => 'm2', 'kategori_guna_tanah' => $kategori, 'bahagian_pemilik' => 'wakaf',
            'status_lot' => 'aktif', 'klasifikasi' => 'dalaman', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $wkt = sprintf('MULTIPOLYGON(((%1$F %2$F, %3$F %2$F, %3$F %4$F, %1$F %4$F, %1$F %2$F)))', $lng - $d, $lat - $d, $lng + $d, $lat + $d);
        DB::statement("INSERT INTO data_spatial (lot_id, sumber_data, semasa, geometri, created_at, updated_at) VALUES (?, 'sintetik', true, ST_GeomFromText(?, 4326), now(), now())", [$id, $wkt]);

        return $id;
    }

    protected function jadual(string $lotId, ?User $pegawai = null, ?string $tarikh = null): JadualPemantauan
    {
        $lot = DB::table('lot_tanah')->where('id', $lotId)->first();
        $j = new JadualPemantauan;
        $j->forceFill([
            'lot_id' => $lotId, 'kod_daerah' => $lot->kod_daerah, 'kod_mukim' => $lot->kod_mukim, 'klasifikasi' => 'dalaman',
            'tahun' => now()->year, 'kekerapan' => 1, 'tarikh_sasaran' => $tarikh ?? now()->addDays(3)->toDateString(), 'objektif' => 'Pemantauan berkala',
            'id_templat' => TemplatSenaraiSemak::query()->where('kod', 'PTN-RUTIN')->value('id'),
            'id_pegawai_bertugas' => $pegawai?->id, 'status_pelaksanaan' => $pegawai ? 'ditugaskan' : 'dirancang',
        ])->save();

        return $j->refresh();
    }

    /**
     * Checklist answers for the default template; overrides by item code.
     *
     * @param  array<string, array<string, mixed>|string>  $override
     * @return list<array<string, mixed>>
     */
    protected function item(array $override = []): array
    {
        $out = [];
        foreach (TemplatSenaraiSemak::query()->where('kod', 'PTN-RUTIN')->firstOrFail()->senarai_item as $i) {
            $o = $override[$i['kod']] ?? 'baik';
            $out[] = ['kod_item' => $i['kod'], ...(is_string($o) ? ['status_item' => $o] : $o)];
        }

        return $out;
    }

    /** @param array<string, mixed> $extra */
    protected function syncVisit(User $officer, JadualPemantauan $j, array $extra = []): TestResponse
    {
        return $this->actingAs($officer)->withHeader('X-Sumber', 'pwa')->postJson('/api/v1/ptn/lapangan/lawatan', $extra + [
            'id_luar_talian' => (string) Str::uuid(), 'id_jadual' => $j->id, 'versi_jadual' => $j->versi,
            'tarikh_lawatan' => now()->toDateString(), 'masa_mula' => now()->subHour()->toIso8601String(),
            'lat' => self::PUSAT_PETALING[0], 'lng' => self::PUSAT_PETALING[1], 'ketepatan_gps_m' => 5,
            'keadaan_tapak' => 'baik', 'ringkasan_penemuan' => 'Tapak baik.', 'cadangan_tindakan' => 'Teruskan pemantauan.',
            'item' => $this->item(),
        ]);
    }

    /** @param array<string, mixed> $meta */
    protected function photo(User $officer, string $lawatanId, ?array $at = null, array $meta = []): TestResponse
    {
        [$lat, $lng] = $at ?? self::PUSAT_PETALING;

        return $this->actingAs($officer)->post('/api/v1/ptn/lapangan/lawatan/'.$lawatanId.'/gambar', $meta + [
            'fail' => UploadedFile::fake()->image('tapak.jpg', 640, 480), 'id_luar_talian' => (string) Str::uuid(),
            'lat' => $lat, 'lng' => $lng, 'ketepatan_m' => 4, 'arah_kamera' => 90, 'tarikh_masa' => now()->toIso8601String(), 'sumber_gambar' => 'kamera',
        ], ['Accept' => 'application/json']);
    }

    /** Full visit: sync, one photo inside the lot, submit. */
    protected function lawatanLengkap(User $officer, JadualPemantauan $j, array $extra = []): RekodLawatan
    {
        $id = $this->syncVisit($officer, $j, $extra)->assertCreated()->json('data.id');
        $this->photo($officer, $id)->assertCreated();
        $this->actingAs($officer)->postJson('/api/v1/ptn/lapangan/lawatan/'.$id.'/hantar')->assertOk();

        return RekodLawatan::query()->withoutGlobalScope('access')->findOrFail($id);
    }

    protected function complete(string $flow, string $subjectId, string $outcome = 'lulus', ?int $by = null, ?string $remarks = null): void
    {
        $subject = ['ptn.pengesahan_laporan' => 'laporan_lawatan', 'ptn.kemaskini_lot' => 'cadangan_kemaskini_lot', 'ptn.pengesahan_tindakan' => 'tindakan_pembetulan'][$flow];
        event(new WorkflowCompleted('wf-x', $flow, $subject, $subjectId, $outcome, $by, $remarks));
    }
}
