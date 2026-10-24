<?php

namespace Tests\Modules\Per;

use App\Models\User;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Hkm\Contracts\TitleRegistry;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Per\Models\Pemerolehan;
use App\Modules\Per\PerServiceProvider;
use App\Modules\Per\Services\ApprovalService;
use App\Modules\Per\Support\ChannelCatalog;
use App\Modules\Per\Support\RefNames;
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
use Tests\Modules\Per\Support\FakeApproverDirectory;
use Tests\Modules\Per\Support\FakeDocumentStore;
use Tests\Modules\Per\Support\FakeTitleRegistry;
use Tests\Modules\Per\Support\FakeWorkflowEngine;
use Tests\Modules\Per\Support\SpyNotifier;
use Tests\TestCase;

/**
 * MOD-PER tests run on the real RBC (permissions via the factory) and the real
 * AUD trail; DMS, WFE, HKM and NTF are faked at their contracts. Lots are
 * inserted directly into lot_tanah.
 */
abstract class PerTestCase extends TestCase
{
    use AssertsAuditTrail, RefreshDatabase;

    /** Everyday registrar / case officer. */
    public const PEGAWAI = ['per.kes.lihat', 'per.kes.cipta', 'per.kes.kemas_kini', 'per.dokumen.cipta', 'per.bayaran.cipta'];

    protected FakeDocumentStore $dms;

    protected FakeWorkflowEngine $workflow;

    protected FakeTitleRegistry $titles;

    protected SpyNotifier $notifier;

    protected FakeApproverDirectory $directory;

    protected function setUp(): void
    {
        parent::setUp();
        RefNames::flush();

        $this->app->instance(DocumentStore::class, $this->dms = new FakeDocumentStore);
        $this->app->instance(WorkflowEngine::class, $this->workflow = new FakeWorkflowEngine);
        $this->app->instance(TitleRegistry::class, $this->titles = new FakeTitleRegistry);
        $this->app->instance(Notifier::class, $this->notifier = new SpyNotifier);
        $this->app->instance(ApproverDirectory::class, $this->directory = new FakeApproverDirectory);
    }

    /**
     * @param  list<string>  $permissions
     * @param  array<string, list<string>>  $scopes
     */
    protected function officer(array $permissions = self::PEGAWAI, array $scopes = [], array $attributes = []): User
    {
        $factory = User::factory()->withPermissions($permissions);
        foreach ($scopes as $type => $values) {
            $factory = $factory->withScope($type, $values);
        }

        return $factory->create($attributes + ['role' => 'user', 'is_active' => true]);
    }

    protected function everything(): User
    {
        return $this->officer(array_keys(PerServiceProvider::PERMISSIONS));
    }

    protected function lot(string $noLot = 'PT 1001', string $kodMukim = '100803', ?string $kodDaerah = null): string
    {
        $id = (string) Str::uuid();
        DB::table('lot_tanah')->insert([
            'id' => $id, 'no_lot' => $noLot, 'kod_mukim' => $kodMukim, 'kod_daerah' => $kodDaerah ?? substr($kodMukim, 0, 4),
            'keluasan' => 1200, 'status_lot' => 'aktif', 'klasifikasi' => 'dalaman', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    /** @param array<string, mixed> $overrides */
    protected function createCase(User $user, string $channel, array $overrides = []): TestResponse
    {
        $payload = $overrides + [
            'kod_saluran' => $channel,
            'tajuk' => 'Kes ujian '.$channel,
            'lot_id' => $overrides['lot_id'] ?? (array_key_exists('no_lot', $overrides) ? null : $this->lot('PT '.random_int(10000, 99999))),
            'butiran' => [],
            'pihak' => [],
        ];

        return $this->actingAs($user)->postJson('/api/v1/per/kes', array_filter($payload, fn ($v) => $v !== null));
    }

    protected function newCase(User $user, string $channel, array $overrides = []): Pemerolehan
    {
        $response = $this->createCase($user, $channel, $overrides);
        $response->assertCreated();

        return Pemerolehan::query()->withoutGlobalScope('access')->findOrFail($response->json('data.id'));
    }

    /** Values for every field a stage requires. @return array<string, mixed> */
    protected function fieldsFor(string $channel, string $stage): array
    {
        $out = [];
        foreach (ChannelCatalog::fields($channel) as $key => $f) {
            if (($f['peringkat'] ?? null) !== $stage) {
                continue;
            }
            $out[$key] = match ($f['jenis']) {
                'tarikh' => now()->subDays(10)->toDateString(),
                'wang' => '1000.00',
                'nombor' => '500',
                'pilihan' => $f['mesti'] ?? array_key_first($f['pilihan']),
                default => ! empty($f['peribadi']) ? 'Nama Sintetik bin Ujian' : 'UJI-'.strtoupper(substr($key, 0, 4)),
            };
        }

        return $out;
    }

    protected function upload(User $user, Pemerolehan $case, int $semakId): TestResponse
    {
        return $this->actingAs($user)->post("/api/v1/per/kes/{$case->id}/dokumen", [
            'fail' => UploadedFile::fake()->create('dokumen.pdf', 20, 'application/pdf'),
            'id_semak' => $semakId,
            'kod_peti_besi' => 'PB-01', 'no_fail' => 'F-100', 'lokasi_rak' => 'R2-3', 'status_dokumen_asal' => 'asal_dalam_simpanan',
        ], ['Accept' => 'application/json']);
    }

    /** Upload every mandatory document required up to (and including) $stage. */
    protected function uploadRequired(User $user, Pemerolehan $case, string $stage): void
    {
        $case->refresh()->load('peringkat', 'saluran.senaraiSemak');
        $order = $case->peringkat->pluck('susunan', 'kod_peringkat')->all() + [ChannelCatalog::TUTUP => PHP_INT_MAX];
        foreach ($case->saluran->senaraiSemak as $item) {
            if ($item->dijana_sistem || ($order[$item->peringkat_diperlukan] ?? PHP_INT_MAX) > ($order[$stage] ?? PHP_INT_MAX)) {
                continue;
            }
            if (! $case->dokumen()->where('id_semak', $item->id)->exists()) {
                $this->upload($user, $case, $item->id)->assertCreated();
            }
        }
    }

    protected function advance(User $user, Pemerolehan $case): TestResponse
    {
        return $this->actingAs($user)->postJson("/api/v1/per/kes/{$case->id}/maju", ['catatan' => 'Dimajukan dalam ujian']);
    }

    /** WFE decided: the flow ended with $outcome by $approver. */
    protected function decide(Pemerolehan $case, User $approver, string $outcome = 'lulus'): void
    {
        $case->refresh();
        event(new StepDecided((string) $case->id_aliran_kerja, 1, ApprovalService::FLOW, 'kelulusan_ketua_bahagian', $outcome === 'lulus' ? 'lulus' : 'tolak', $approver->id));
        event(new WorkflowCompleted((string) $case->id_aliran_kerja, ApprovalService::FLOW, ApprovalService::SUBJECT, $case->id, $outcome, $approver->id, 'Ulasan ujian'));
    }

    /**
     * Drive a case from its current stage to the final stage, filling fields,
     * uploading documents, getting WFE approval from $approver and (5A)
     * paying the notice in full.
     */
    protected function driveToFinalStage(User $user, User $approver, User $finance, Pemerolehan $case): Pemerolehan
    {
        for ($guard = 0; $guard < 20; $guard++) {
            $case = Pemerolehan::query()->withoutGlobalScope('access')->with(['peringkat', 'saluran'])->findOrFail($case->id);
            $stage = $case->peringkatSemasa();
            $this->assertNotNull($stage);
            if ($stage->jenis === 'simpanan') {
                return $case;
            }
            if ($stage->jenis === 'kelulusan' && $case->status_pemerolehan === Pemerolehan::MENUNGGU_KELULUSAN) {
                $this->decide($case, $approver);

                continue;
            }

            $channel = $case->kodSaluran();
            $fields = $this->fieldsFor($channel, $stage->kod_peringkat);
            if ($fields !== []) {
                $this->actingAs($user)->putJson("/api/v1/per/kes/{$case->id}", ['butiran' => $fields])->assertOk();
            }
            $next = $case->peringkat->first(fn ($s) => $s->susunan > $stage->susunan);
            if ($next !== null) {
                $this->uploadRequired($user, $case, $next->kod_peringkat);
            }
            if ($channel === 'pemberimilikan_5a' && $next?->kod_peringkat === 'penerimaan_hak_milik') {
                $this->payInFull($user, $finance, $case);
            }

            $this->advance($user, $case)->assertOk();
        }

        $this->fail('Kes tidak sampai ke peringkat akhir.');
    }

    protected function payInFull(User $user, User $finance, Pemerolehan $case): void
    {
        $due = $this->actingAs($user)->getJson("/api/v1/per/kes/{$case->id}")->json('data.bayaran_5a.baki_tertunggak');
        $this->actingAs($user)->postJson("/api/v1/per/kes/{$case->id}/bayaran", [
            'jumlah_dibayar' => $due, 'tarikh_bayaran' => now()->toDateString(), 'mod_bayaran' => 'eft', 'no_resit' => 'R-'.random_int(1000, 9999),
        ])->assertCreated();
        $paymentId = (int) DB::table('bayaran_notis_5a')->where('id_pemerolehan', $case->id)->where('status_pengesahan', 'belum_disahkan')->value('id');
        $this->actingAs($finance)->postJson("/api/v1/per/kes/{$case->id}/bayaran/{$paymentId}/pengesahan", ['terima' => true])->assertOk();
    }

    /** @return array<string, mixed> */
    protected function closePayload(): array
    {
        return [
            'hak_milik' => ['no_hak_milik' => 'GRN 55123', 'jenis_hak_milik' => 'geran', 'tarikh_pendaftaran' => now()->toDateString(), 'pejabat_pendaftaran' => 'PTG Selangor'],
            'peti_besi' => ['kod_peti_besi' => 'PB-02', 'no_fail' => 'F-2201', 'lokasi_rak' => 'R4-1', 'jenis_dokumen_asal' => 'geran_asal'],
        ];
    }
}
