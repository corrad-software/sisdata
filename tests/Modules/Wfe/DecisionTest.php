<?php

namespace Tests\Modules\Wfe;

use App\Models\User;
use App\Modules\Wfe\Events\StepDecided;
use App\Modules\Wfe\Events\TaskAssigned;
use App\Modules\Wfe\Events\WorkflowCompleted;
use App\Modules\Wfe\Models\Instans;
use App\Modules\Wfe\Models\Tugasan;
use Illuminate\Support\Facades\Event;

class DecisionTest extends WfeTestCase
{
    private User $reviewer;

    private User $approver;

    private User $applicant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reviewer = $this->user(null, ['pegawai_hq']);
        $this->approver = $this->user();
        $this->applicant = $this->user();
        $this->activeFlow($this->structure('ujian.dua', [
            $this->stage('semakan', [['jenis' => 'peranan', 'nilai' => 'pegawai_hq']]),
            $this->stage('kelulusan', [$this->approver]),
        ], [], ['susulan' => [['bila' => 'lulus', 'tajuk' => 'Kemas kini rekod', 'jenis_penerima' => 'pemohon', 'sla_nilai' => 3]]]));
    }

    private function startFlow(string|int $record = 1, array $context = []): string
    {
        return $this->engine()->start('ujian.dua', 'rekod_ujian', $record, $this->applicant, $context, 'Permohonan ujian '.$record);
    }

    private function openTask(string $instanceId): Tugasan
    {
        return Tugasan::where('id_instans', $instanceId)->whereIn('status', ['menunggu', 'ditangguh'])->orderBy('id')->firstOrFail();
    }

    public function test_SRS_WFE_14_task_board_shows_only_my_tasks_with_sla_remaining(): void
    {
        $id = $this->startFlow();
        $task = $this->openTask($id);

        $row = $this->actingAs($this->reviewer)->getJson('/api/v1/wfe/tugasan')->assertOk()->json('data.0');
        $this->assertSame($task->id, $row['id']);
        $this->assertSame('Permohonan ujian 1', $row['tajuk']);
        // 2 working days from Mon 09:00 → Wed 17:00 = 8 + 9 + 9 working hours (08:00–17:00)
        $this->assertSame(26 * 60, $row['baki_sla_minit']);
        $this->assertTrue($row['boleh_bertindak']);

        $this->actingAs($this->approver)->getJson('/api/v1/wfe/tugasan')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->approver)->getJson("/api/v1/wfe/tugasan/{$task->id}")->assertForbidden();

        // without the permission the board is closed
        $this->actingAs($this->user([]))->getJson('/api/v1/wfe/tugasan')->assertForbidden();
    }

    public function test_SRS_WFE_14_quick_actions_approve_reject_return_from_the_board(): void
    {
        $a = $this->startFlow('a');
        $this->act($this->reviewer, $this->openTask($a)->id, 'lulus')->assertOk()->assertJsonPath('data.keputusan', 'lulus');

        $b = $this->startFlow('b');
        $this->act($this->reviewer, $this->openTask($b)->id, 'tolak', 'Pelan tidak lengkap')->assertOk();
        $this->assertSame(Instans::TOLAK, Instans::find($b)->status);

        $c = $this->startFlow('c');
        $this->act($this->reviewer, $this->openTask($c)->id, 'lulus')->assertOk();
        $this->act($this->approver, $this->openTask($c)->id, 'kembali', 'Sila semak semula nilai')->assertOk();
        $this->assertSame('semakan', Instans::find($c)->peringkat->kod);
    }

    /** Acceptance criterion 5. */
    public function test_SRS_WFE_17_reject_return_defer_need_written_justification(): void
    {
        $id = $this->startFlow();
        $task = $this->openTask($id);

        foreach (['tolak', 'kembali', 'tangguh'] as $action) {
            $this->act($this->reviewer, $task->id, $action)->assertUnprocessable()->assertJsonValidationErrors('justifikasi');
            $this->act($this->reviewer, $task->id, $action, '   ')->assertUnprocessable();
        }
        $this->assertSame(Tugasan::MENUNGGU, $task->refresh()->status);

        $this->act($this->reviewer, $task->id, 'tolak', 'Geran tidak disertakan')->assertOk();
        $history = $this->actingAs($this->applicant)->getJson("/api/v1/wfe/instans/{$id}")->assertOk()->json('data.sejarah');
        $reject = collect($history)->firstWhere('peristiwa', 'tolak');
        $this->assertSame('Geran tidak disertakan', $reject['justifikasi']);
        $this->assertSame($this->reviewer->name, $reject['pengguna']);
    }

    public function test_SRS_WFE_17_defer_pauses_sla_and_keeps_task_with_approver(): void
    {
        $id = $this->startFlow();
        $task = $this->openTask($id);
        $originalDue = $task->tarikh_akhir_sla;

        $this->act($this->reviewer, $task->id, 'tangguh', 'Menunggu laporan tapak', ['tangguh_hingga' => '2026-11-09T09:00:00+08:00'])->assertOk();
        $task->refresh();
        $this->assertSame(Tugasan::DITANGGUH, $task->status);
        $this->assertTrue($task->tarikh_akhir_sla->greaterThan($originalDue));

        // still on the board and still actionable
        $this->actingAs($this->reviewer)->getJson('/api/v1/wfe/tugasan')->assertJsonPath('data.0.status', 'ditangguh');
        $this->act($this->reviewer, $task->id, 'lulus')->assertOk();
    }

    /** Acceptance criterion 3, through the API directly. */
    public function test_SRS_WFE_18_applicant_cannot_approve_own_request(): void
    {
        // the applicant also holds the approver role
        $this->access->grant($this->applicant, ['wfe.tugasan.lihat', 'wfe.tugasan.lulus'], ['pegawai_hq']);
        $id = $this->startFlow();

        $this->act($this->applicant, $this->openTask($id)->id, 'lulus')
            ->assertForbidden()
            ->assertJsonPath('code', 'PENGASINGAN_TUGAS')
            ->assertJsonPath('message', __('wfe::wfe.error.sod_pemohon'));
        $this->assertSame('semakan', Instans::find($id)->peringkat->kod);
    }

    public function test_SRS_WFE_18_same_user_cannot_act_on_two_consecutive_stages(): void
    {
        // reviewer is also the individual approver of stage 2
        $this->activeFlow($this->structure('ujian.berturutan', [
            $this->stage('satu', [$this->reviewer]),
            $this->stage('dua', [$this->reviewer, $this->approver], ['mod_kelulusan' => 'mana_mana']),
        ]));
        $id = $this->engine()->start('ujian.berturutan', 'rekod_ujian', 9, $this->applicant);
        $this->act($this->reviewer, $this->openTask($id)->id, 'lulus')->assertOk();

        $mine = Tugasan::where('id_instans', $id)->where('status', 'menunggu')->where('penerima_kunci', 'individu:'.$this->reviewer->id)->firstOrFail();
        $this->act($this->reviewer, $mine->id, 'lulus')->assertForbidden()->assertJsonPath('message', __('wfe::wfe.error.sod_berturutan'));

        $other = Tugasan::where('id_instans', $id)->where('status', 'menunggu')->where('penerima_kunci', 'individu:'.$this->approver->id)->firstOrFail();
        $this->act($this->approver, $other->id, 'lulus')->assertOk();
        $this->assertSame(Instans::LULUS, Instans::find($id)->status);
    }

    /** Acceptance criterion 7: complete, ordered decision history. */
    public function test_SRS_WFE_16_history_lists_every_stage_actor_decision_justification_and_time(): void
    {
        $id = $this->startFlow();
        $this->act($this->reviewer, $this->openTask($id)->id, 'lulus')->assertOk();
        $this->act($this->approver, $this->openTask($id)->id, 'kembali', 'Lampirkan pelan ukur')->assertOk();
        $this->act($this->reviewer, $this->openTask($id)->id, 'lulus', 'Pelan ukur dilampirkan')->assertOk();
        $this->act($this->approver, $this->openTask($id)->id, 'lulus')->assertOk();

        $history = $this->actingAs($this->applicant)->getJson("/api/v1/wfe/instans/{$id}")->assertOk()->json('data.sejarah');
        $decisions = array_values(array_filter($history, fn ($h) => in_array($h['peristiwa'], ['lulus', 'tolak', 'kembali', 'tangguh'], true)));

        $this->assertSame(['lulus', 'kembali', 'lulus', 'lulus'], array_column($decisions, 'peristiwa'));
        $this->assertSame(['Peringkat semakan', 'Peringkat kelulusan', 'Peringkat semakan', 'Peringkat kelulusan'], array_column($decisions, 'peringkat'));
        $this->assertSame([$this->reviewer->name, $this->approver->name, $this->reviewer->name, $this->approver->name], array_column($decisions, 'pengguna'));
        $this->assertSame('Lampirkan pelan ukur', $decisions[1]['justifikasi']);
        $this->assertSame(['pegawai_hq'], $decisions[0]['peranan_semasa']);
        $this->assertNotEmpty($decisions[0]['alamat_ip']);
        $ids = array_column($history, 'id');
        $sorted = $ids;
        sort($sorted);
        $this->assertSame($sorted, $ids);
        $this->assertSame('mula', $history[0]['peristiwa']);
        $this->assertSame('selesai', collect($history)->last(fn ($h) => $h['peristiwa'] === 'selesai')['peristiwa']);
    }

    public function test_SRS_WFE_16_role_at_time_of_action_is_recorded_not_current_role(): void
    {
        $id = $this->startFlow();
        $this->act($this->reviewer, $this->openTask($id)->id, 'lulus')->assertOk();
        $this->access->grant($this->reviewer, ['wfe.tugasan.lihat'], ['pegawai_bersara']);

        $entry = Instans::find($id)->sejarah()->where('peristiwa', 'lulus')->firstOrFail();
        $this->assertSame(['pegawai_hq'], $entry->peranan_semasa);
    }

    public function test_SRS_AUD_18_decisions_are_explicit_audit_entries_with_justification(): void
    {
        $id = $this->startFlow();
        $this->act($this->reviewer, $this->openTask($id)->id, 'lulus')->assertOk();
        $this->act($this->approver, $this->openTask($id)->id, 'tolak', 'Tidak menepati syarat')->assertOk();

        $actions = collect($this->audited)->where('entity', 'wf_instans');
        $this->assertSame(['lulus', 'tolak'], $actions->pluck('action')->all());
        $reject = $actions->firstWhere('action', 'tolak');
        $this->assertSame('Tidak menepati syarat', $reject['reason']);
        $this->assertSame($id, $reject['id']);
        $this->assertSame('kelulusan', $reject['meta']['peringkat']);
    }

    public function test_SRS_WFE_20_final_outcome_dispatches_event_notifies_and_creates_follow_up(): void
    {
        Event::fake([WorkflowCompleted::class, StepDecided::class, TaskAssigned::class]);
        $id = $this->startFlow('rekod-77');
        $this->act($this->reviewer, $this->openTask($id)->id, 'lulus')->assertOk();
        $this->act($this->approver, $this->openTask($id)->id, 'lulus', 'Diluluskan mesyuarat')->assertOk();

        Event::assertDispatched(WorkflowCompleted::class, fn (WorkflowCompleted $e) => $e->instanceId === $id
            && $e->flowCode === 'ujian.dua' && $e->subjectType === 'rekod_ujian' && $e->subjectId === 'rekod-77'
            && $e->outcome === 'lulus' && $e->decidedBy === $this->approver->id);
        Event::assertDispatched(StepDecided::class, 2);
        Event::assertDispatched(TaskAssigned::class);

        $decision = collect($this->sentOfType('wfe.keputusan'))->last();
        $this->assertSame([$this->applicant->id], $decision['to']);

        // follow-up task for the applicant
        $followUp = Tugasan::where('id_instans', $id)->where('jenis', Tugasan::SUSULAN)->firstOrFail();
        $this->assertSame('individu:'.$this->applicant->id, $followUp->penerima_kunci);
        $this->actingAs($this->applicant)->getJson('/api/v1/wfe/tugasan')->assertJsonPath('data.0.jenis', 'susulan');
        $this->actingAs($this->applicant)->postJson("/api/v1/wfe/tugasan/{$followUp->id}/selesai", ['catatan' => 'Rekod dikemas kini'])->assertOk();
        $this->assertSame(Tugasan::SELESAI, $followUp->refresh()->status);
    }

    public function test_SRS_WFE_20_new_task_notifies_role_holders(): void
    {
        $other = $this->user(null, ['pegawai_hq']);
        // the default ApproverDirectory reads the legacy users.role column
        $this->reviewer->forceFill(['role' => 'pegawai_hq'])->save();
        $other->forceFill(['role' => 'pegawai_hq'])->save();
        $id = $this->startFlow();

        $sent = $this->sentOfType('wfe.tugasan_baharu')[0];
        $this->assertEqualsCanonicalizing([$this->reviewer->id, $other->id], $sent['to']);
        $this->assertSame('/admin/wfe/tugasan/'.$this->openTask($id)->id, $sent['link']);
    }

    public function test_SRS_WFE_22_applicant_withdraws_pending_request(): void
    {
        Event::fake([WorkflowCompleted::class]);
        $id = $this->startFlow();

        $this->actingAs($this->reviewer)->postJson("/api/v1/wfe/instans/{$id}/tarik-balik", ['justifikasi' => 'x'])->assertForbidden();
        $this->actingAs($this->applicant)->postJson("/api/v1/wfe/instans/{$id}/tarik-balik", [])->assertUnprocessable();
        $this->actingAs($this->applicant)->postJson("/api/v1/wfe/instans/{$id}/tarik-balik", ['justifikasi' => 'Permohonan dibuat semula'])->assertOk();

        $instance = Instans::find($id);
        $this->assertSame(Instans::DITARIK_BALIK, $instance->status);
        $this->assertSame(0, Tugasan::where('id_instans', $id)->where('status', 'menunggu')->count());
        $this->assertTrue($instance->sejarah()->where('peristiwa', 'tarik_balik')->where('justifikasi', 'Permohonan dibuat semula')->exists());
        Event::assertDispatched(WorkflowCompleted::class, fn ($e) => $e->outcome === 'ditarik_balik');

        // a decided request cannot be withdrawn
        $this->actingAs($this->applicant)->postJson("/api/v1/wfe/instans/{$id}/tarik-balik", ['justifikasi' => 'lagi'])->assertStatus(409);
    }

    public function test_SRS_WFE_22_return_from_first_stage_goes_back_to_applicant_who_resubmits(): void
    {
        $id = $this->startFlow();
        $this->act($this->reviewer, $this->openTask($id)->id, 'kembali', 'Lengkapkan borang')->assertOk();
        $this->assertSame(Instans::MENUNGGU_PEMOHON, Instans::find($id)->keadaan);
        $this->assertSame(Instans::BERJALAN, $this->engine()->status($id));

        $this->actingAs($this->applicant)->getJson('/api/v1/wfe/permohonan')->assertOk()->assertJsonPath('data.0.keadaan', 'menunggu_pemohon');
        $this->actingAs($this->applicant)->postJson("/api/v1/wfe/instans/{$id}/hantar-semula", ['catatan' => 'Borang dilengkapkan'])->assertOk();
        $this->assertSame('semakan', Instans::find($id)->peringkat->kod);
    }

    public function test_SRS_WFE_01_engine_contract_status_and_active_instance(): void
    {
        $id = $this->startFlow('k1');
        $this->assertSame('berjalan', $this->engine()->status($id));
        $this->assertSame($id, $this->engine()->activeInstanceFor('rekod_ujian', 'k1'));
        $this->assertNull($this->engine()->activeInstanceFor('rekod_ujian', 'tiada'));

        $this->expectException(\InvalidArgumentException::class);
        $this->engine()->start('tiada.aliran', 'rekod_ujian', 1, $this->applicant);
    }
}
