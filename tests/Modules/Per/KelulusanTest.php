<?php

namespace Tests\Modules\Per;

use App\Models\User;
use App\Modules\Per\Models\Pemerolehan;
use App\Modules\Per\Services\ApprovalService;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\WorkflowCompleted;
use App\Modules\Wfe\Models\Definisi;
use App\Modules\Wfe\Models\Kumpulan;
use App\Modules\Wfe\Models\Tugasan;
use App\Modules\Wfe\Services\DefinitionService;
use App\Modules\Wfe\Services\Engine;
use Illuminate\Testing\TestResponse;

class KelulusanTest extends PerTestCase
{
    /** Take a tanah_warta case to the door of the approval stage. */
    private function readyForApproval(User $user, string $channel = 'tanah_warta', array $overrides = []): Pemerolehan
    {
        $case = $this->newCase($user, $channel, $overrides);
        for ($i = 0; $i < 6; $i++) {
            $case->refresh()->load('peringkat');
            $stage = $case->peringkatSemasa();
            $next = $case->peringkat->first(fn ($s) => $s->susunan > $stage->susunan);
            $this->actingAs($user)->putJson("/api/v1/per/kes/{$case->id}", ['butiran' => $this->fieldsFor($channel, $stage->kod_peringkat)])->assertOk();
            $this->uploadRequired($user, $case, $next->kod_peringkat);
            if ($next->jenis === 'kelulusan') {
                return $case;
            }
            $this->advance($user, $case)->assertOk();
        }
        $this->fail('tiada peringkat kelulusan');
    }

    public function test_SRS_PER_29_entering_approval_starts_wfe_flow_with_channel_and_value_context(): void
    {
        $registrar = $this->officer();
        $case = $this->readyForApproval($registrar, 'tanah_warta', ['nilai_pemerolehan' => 2500000]);
        $this->advance($registrar, $case)->assertOk()->assertJsonPath('data.status', Pemerolehan::MENUNGGU_KELULUSAN);

        $started = $this->workflow->last();
        $this->assertSame(ApprovalService::FLOW, $started['flow']);
        $this->assertSame('pemerolehan', $started['subjectType']);
        $this->assertSame($case->id, $started['subjectId']);
        $this->assertSame($registrar->id, $started['initiator'], 'Pendaftar kes ialah pemohon WFE.');
        $this->assertSame('tanah_warta', $started['context']['kod_saluran']);
        $this->assertSame(2500000.0, $started['context']['nilai_pemerolehan']);
        $this->assertSame('hartanah', $started['context']['kod_bahagian']);
        $this->assertSame('/admin/per/kes/'.$case->id, $started['context']['pautan']);

        // No progress while waiting; the decision comes only from WFE.
        $this->advance($registrar, $case)->assertStatus(409);
    }

    public function test_SRS_PER_29_rejection_returns_case_for_resubmission(): void
    {
        $registrar = $this->officer();
        $approver = $this->officer(['per.kes.lihat']);
        $case = $this->readyForApproval($registrar);
        $this->advance($registrar, $case)->assertOk();

        $this->decide($case, $approver, 'tolak');
        $case->refresh();
        $this->assertSame(Pemerolehan::DITOLAK, $case->status_pemerolehan);
        $this->assertSame('kelulusan', $case->peringkat_semasa);
        $this->assertAudited('tolak', ['modul' => 'per', 'id_rekod' => $case->id]);

        $this->advance($registrar, $case)->assertOk()->assertJsonPath('data.status', Pemerolehan::MENUNGGU_KELULUSAN);
        $this->assertCount(2, $this->workflow->started);
        $this->decide($case, $approver);
        $this->assertSame(Pemerolehan::DILULUSKAN, $case->refresh()->status_pemerolehan);
        $this->assertAudited('lulus', ['modul' => 'per', 'id_rekod' => $case->id]);
        $actions = $case->kelulusan()->pluck('tindakan')->all();
        $this->assertSame(['hantar', 'tolak', 'tolak', 'hantar', 'lulus', 'lulus'], $actions);
    }

    public function test_SRS_PER_29_registrar_approving_own_case_is_refused(): void
    {
        $registrar = $this->officer();
        $submitter = $this->officer();
        $case = $this->readyForApproval($registrar);
        $this->advance($submitter, $case)->assertOk();
        $this->assertSame($registrar->id, $this->workflow->last()['initiator']);

        $this->decide($case, $registrar);
        $case->refresh();
        $this->assertSame(Pemerolehan::DITOLAK, $case->status_pemerolehan);
        $this->assertStringContainsString('pendaftar kes', (string) $case->kelulusan()->where('aras_kelulusan', 'keputusan_akhir')->value('justifikasi'));

        $this->advance($submitter, $case)->assertOk();
        $this->decide($case, $submitter);
        $this->assertSame(Pemerolehan::DITOLAK, $case->refresh()->status_pemerolehan, 'Penghantar juga tidak boleh meluluskan.');
    }

    public function test_SRS_PER_29_without_an_active_flow_the_case_does_not_move(): void
    {
        $this->workflow->configured = false;
        $registrar = $this->officer();
        $case = $this->readyForApproval($registrar);
        $this->advance($registrar, $case)->assertStatus(503)->assertJsonPath('message', __('per::per.aliran_tiada'));
        $this->assertSame(Pemerolehan::DALAM_PROSES, $case->refresh()->status_pemerolehan);
        $this->assertNotSame('kelulusan', $case->peringkat_semasa);
    }

    public function test_SRS_PER_29_foreign_workflow_events_are_ignored(): void
    {
        $registrar = $this->officer();
        $case = $this->readyForApproval($registrar);
        $this->advance($registrar, $case)->assertOk();
        event(new WorkflowCompleted('wf-1', 'contoh.kelulusan', 'pemerolehan', $case->id, 'lulus', 1));
        event(new WorkflowCompleted('lain', ApprovalService::FLOW, 'pemerolehan', $case->id, 'lulus', 1));
        $this->assertSame(Pemerolehan::MENUNGGU_KELULUSAN, $case->refresh()->status_pemerolehan);
    }

    /**
     * Real MOD-WFE engine with the shipped definition (Database/wfe/per.kelulusan.json):
     * layers by bahagian and value, and the registrar can never act on a task.
     */
    public function test_SRS_PER_29_layered_approval_through_real_wfe_with_segregation_of_duties(): void
    {
        $this->app->forgetInstance(WorkflowEngine::class);
        $this->app->bind(WorkflowEngine::class, Engine::class);

        $wfePerms = ['wfe.tugasan.lihat', 'wfe.tugasan.lulus', 'per.kes.lihat'];
        $registrar = $this->officer([...self::PEGAWAI, 'wfe.tugasan.lihat', 'wfe.tugasan.lulus']);
        $reviewer = $this->officer($wfePerms);
        $headWakaf = $this->officer($wfePerms);
        $headHartanah = $this->officer($wfePerms);
        $management = $this->officer($wfePerms);
        foreach (['per_penyemak' => [$reviewer, $registrar], 'per_ketua_wakaf' => [$headWakaf], 'per_ketua_hartanah' => [$headHartanah], 'per_ketua_baitulmal' => [$headHartanah], 'per_pengurusan' => [$management]] as $code => $members) {
            $group = Kumpulan::create(['kod' => $code, 'nama' => $code]);
            $group->ahli()->sync(array_map(fn (User $u) => $u->id, $members));
        }
        $structure = json_decode((string) file_get_contents(base_path('app/Modules/Per/Database/wfe/per.kelulusan.json')), true);
        $definition = app(DefinitionService::class)->create($structure, $management);
        $definition->forceFill(['status' => Definisi::AKTIF, 'tarikh_berkuat_kuasa' => now()])->save();

        $case = $this->readyForApproval($registrar, 'wakaf', ['nilai_pemerolehan' => 80000]);
        $this->advance($registrar, $case)->assertOk()->assertJsonPath('data.status', Pemerolehan::MENUNGGU_KELULUSAN);
        $instance = (string) $case->refresh()->id_aliran_kerja;

        $task = Tugasan::query()->where('id_instans', $instance)->where('status', 'menunggu')->orderBy('id')->firstOrFail();
        $this->wfeAct($registrar, $task->id)->assertForbidden();
        $this->wfeAct($reviewer, $task->id)->assertOk();

        // Branch on kod_bahagian = wakaf → Ketua Bahagian Wakaf; value ≤ RM1 juta → no management layer.
        $next = Tugasan::query()->where('id_instans', $instance)->where('status', 'menunggu')->orderBy('id')->firstOrFail();
        $this->wfeAct($headHartanah, $next->id)->assertForbidden();
        $this->wfeAct($headWakaf, $next->id)->assertOk();

        $case->refresh();
        $this->assertSame(Pemerolehan::DILULUSKAN, $case->status_pemerolehan);
        $this->assertSame('pendaftaran_hak_milik', $case->peringkat_semasa);
        $layers = $case->kelulusan()->get(['aras_kelulusan', 'tindakan', 'id_pegawai_pelulus'])->map(fn ($k) => [$k->aras_kelulusan, $k->tindakan, $k->id_pegawai_pelulus])->all();
        $this->assertSame([
            ['penghantaran', 'hantar', $registrar->id],
            ['semakan_pegawai_kanan', 'lulus', $reviewer->id],
            ['kb_wakaf', 'lulus', $headWakaf->id],
            ['keputusan_akhir', 'lulus', $headWakaf->id],
        ], $layers);
    }

    private function wfeAct(User $user, int $taskId): TestResponse
    {
        $this->app['session']->forget('password_hash_web');

        return $this->actingAs($user)->postJson("/api/v1/wfe/tugasan/{$taskId}/tindakan", ['tindakan' => 'lulus', 'justifikasi' => 'Disokong']);
    }
}
