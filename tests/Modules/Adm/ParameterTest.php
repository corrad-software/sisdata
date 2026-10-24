<?php

namespace Tests\Modules\Adm;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adm\Models\KonfigKelulusan;
use App\Modules\Adm\Models\Parameter;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Tests\Modules\Adm\Fakes\FakeWorkflowEngine;

class ParameterTest extends AdmTestCase
{
    private function params(): Parameters
    {
        return $this->app->make(Parameters::class);
    }

    public function test_SRS_ADM_01_parameters_are_listed_grouped_by_module_with_type_and_critical_flag(): void
    {
        $this->params()->declare('rbc.sesi_tamat_minit', 'int', 30, 'Tamat sesi (minit)', true);

        $res = $this->actingAs($this->admin())->getJson('/api/v1/adm/parameter')->assertOk();

        $groups = collect($res->json('data'))->keyBy('modul');
        $this->assertArrayHasKey('adm', $groups->all());
        $this->assertArrayHasKey('rbc', $groups->all());
        $rbc = collect($groups['rbc']['parameter'])->firstWhere('kunci', 'rbc.sesi_tamat_minit');
        $this->assertSame('int', $rbc['jenis']);
        $this->assertTrue($rbc['kritikal']);
        $this->assertSame(30, $rbc['nilai']);
        $this->assertFalse($rbc['ditetapkan']);
    }

    public function test_SRS_ADM_01_a_parameter_change_takes_effect_on_the_next_read_without_restart(): void
    {
        $this->assertSame(10, $this->params()->get('adm.carian_had_hasil'));
        $this->params()->get('adm.carian_had_hasil'); // warm the cache

        $this->actingAs($this->admin())
            ->putJson('/api/v1/adm/parameter/adm.carian_had_hasil', ['nilai' => 25, 'justifikasi' => 'Lebih hasil'])
            ->assertOk()
            ->assertJsonPath('data.nilai', 25);

        $this->assertSame(25, $this->params()->get('adm.carian_had_hasil'));
        $this->assertSame('Lebih hasil', Parameter::query()->where('kunci', 'adm.carian_had_hasil')->value('justifikasi'));
    }

    public function test_SRS_ADM_01_values_are_validated_against_the_declared_type(): void
    {
        $this->actingAs($this->admin())
            ->putJson('/api/v1/adm/parameter/adm.carian_had_hasil', ['nilai' => 'banyak'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION');

        $this->actingAs($this->admin())->putJson('/api/v1/adm/parameter/tiada.parameter', ['nilai' => 1])->assertNotFound();
    }

    public function test_SRS_ADM_01_reset_returns_to_the_declared_default(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->putJson('/api/v1/adm/parameter/adm.carian_had_hasil', ['nilai' => 7])->assertOk();
        $this->actingAs($admin)->deleteJson('/api/v1/adm/parameter/adm.carian_had_hasil')->assertOk()->assertJsonPath('data.nilai', 10);
        $this->assertSame(10, $this->params()->get('adm.carian_had_hasil'));
    }

    public function test_SRS_ADM_01_user_without_permission_cannot_view_or_change_parameters(): void
    {
        $user = $this->userWith([]);
        $this->actingAs($user)->getJson('/api/v1/adm/parameter')->assertForbidden();
        $this->actingAs($user)->putJson('/api/v1/adm/parameter/adm.carian_had_hasil', ['nilai' => 5])->assertForbidden();

        $viewer = $this->userWith(['adm.parameter.lihat']);
        $this->actingAs($viewer)->getJson('/api/v1/adm/parameter')->assertOk();
        $this->actingAs($viewer)->putJson('/api/v1/adm/parameter/adm.carian_had_hasil', ['nilai' => 5])->assertForbidden();
    }

    public function test_SRS_ADM_01_parameters_fall_back_to_defaults_when_the_cache_is_down(): void
    {
        config(['cache.default' => 'redis', 'database.redis.default.port' => 1]);
        $this->assertSame(10, $this->params()->get('adm.carian_had_hasil'));
    }

    public function test_SRS_ADM_18_critical_change_stays_pending_until_a_second_admin_approves(): void
    {
        $requester = $this->admin();
        $approver = $this->admin();

        $res = $this->actingAs($requester)
            ->putJson('/api/v1/adm/parameter/adm.log_ralat_simpan_hari', ['nilai' => 30, 'justifikasi' => 'Jimat storan'])
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'menunggu');

        // Not in effect yet (acceptance criterion 2).
        $this->assertSame(180, $this->params()->get('adm.log_ralat_simpan_hari'));
        $id = $res->json('data.id');

        // The requester can't approve their own change.
        $this->actingAs($requester)->postJson("/api/v1/adm/kelulusan/{$id}/lulus")->assertForbidden();
        $this->assertSame(180, $this->params()->get('adm.log_ralat_simpan_hari'));

        // Approvers were told (built-in mode), excluding the requester.
        $sent = $this->notifier->ofType('adm.kelulusan_konfigurasi');
        $this->assertCount(1, $sent);
        $this->assertNotContains($requester->id, $sent[0]['recipients']);
        $this->assertContains($approver->id, $sent[0]['recipients']);

        $this->actingAs($approver)->postJson("/api/v1/adm/kelulusan/{$id}/lulus", ['catatan' => 'OK'])
            ->assertOk()->assertJsonPath('data.status', 'lulus');

        $this->assertSame(30, $this->params()->get('adm.log_ralat_simpan_hari'));
        $this->assertCount(1, $this->notifier->ofType('adm.keputusan_konfigurasi'));
    }

    public function test_SRS_ADM_18_rejected_change_is_never_applied_and_duplicates_are_refused(): void
    {
        $requester = $this->admin();
        $approver = $this->admin();

        $id = $this->actingAs($requester)
            ->putJson('/api/v1/adm/parameter/adm.proksi_dipercayai', ['nilai' => '*', 'justifikasi' => 'WAF baharu'])
            ->assertStatus(202)->json('data.id');

        $this->actingAs($requester)
            ->putJson('/api/v1/adm/parameter/adm.proksi_dipercayai', ['nilai' => '10.0.0.1', 'justifikasi' => 'lagi'])
            ->assertStatus(409);

        $this->actingAs($approver)->postJson("/api/v1/adm/kelulusan/{$id}/tolak")->assertStatus(422); // remarks required
        $this->actingAs($approver)->postJson("/api/v1/adm/kelulusan/{$id}/tolak", ['catatan' => 'Terlalu luas'])
            ->assertOk()->assertJsonPath('data.status', 'tolak');
        $this->actingAs($approver)->postJson("/api/v1/adm/kelulusan/{$id}/lulus")->assertStatus(409);

        $this->assertSame('', $this->params()->get('adm.proksi_dipercayai'));
    }

    public function test_SRS_ADM_18_application_admin_cannot_change_security_settings(): void
    {
        $appAdmin = $this->userWith(['adm.parameter.lihat', 'adm.parameter.kemas_kini']);

        $this->actingAs($appAdmin)
            ->putJson('/api/v1/adm/parameter/adm.siem_pemacu', ['nilai' => 'tiada', 'justifikasi' => 'x'])
            ->assertForbidden();
        $this->actingAs($appAdmin)
            ->putJson('/api/v1/adm/parameter/adm.carian_had_hasil', ['nilai' => 12])
            ->assertOk();
    }

    public function test_SRS_ADM_18_requester_can_cancel_and_approval_needs_permission(): void
    {
        $requester = $this->admin();
        $id = $this->actingAs($requester)
            ->putJson('/api/v1/adm/parameter/adm.siem_hos', ['nilai' => 'siem:514', 'justifikasi' => 'x'])->json('data.id');

        $this->actingAs($this->userWith(['adm.konfigurasi.lihat']))->postJson("/api/v1/adm/kelulusan/{$id}/lulus")->assertForbidden();
        $this->actingAs($this->admin())->postJson("/api/v1/adm/kelulusan/{$id}/batal")->assertForbidden();
        $this->actingAs($requester)->postJson("/api/v1/adm/kelulusan/{$id}/batal")->assertOk()->assertJsonPath('data.status', 'dibatalkan');
    }

    public function test_SRS_ADM_18_uses_the_workflow_engine_when_available(): void
    {
        $engine = new FakeWorkflowEngine;
        $this->app->instance(WorkflowEngine::class, $engine);
        $requester = $this->admin();
        $approver = $this->admin();

        $id = $this->actingAs($requester)
            ->putJson('/api/v1/adm/parameter/adm.log_integrasi_simpan_hari', ['nilai' => 60, 'justifikasi' => 'Dasar baharu'])
            ->assertStatus(202)->json('data.id');

        $this->assertSame('adm.konfigurasi_kritikal', $engine->started[0]['flowCode']);
        $this->assertSame('adm_konfig_kelulusan', $engine->started[0]['subjectType']);
        $this->assertSame('wf-1', KonfigKelulusan::query()->find($id)->id_instans_aliran);
        $this->assertCount(0, $this->notifier->ofType('adm.kelulusan_konfigurasi'));

        // Decided in WFE, not on ADM's screen.
        $this->actingAs($approver)->postJson("/api/v1/adm/kelulusan/{$id}/lulus")->assertStatus(409);

        event(new WorkflowCompleted('wf-1', 'adm.konfigurasi_kritikal', 'adm_konfig_kelulusan', (string) $id, 'lulus', $approver->id, 'Diluluskan'));

        $this->assertSame(60, $this->params()->get('adm.log_integrasi_simpan_hari'));
        $this->assertSame('lulus', KonfigKelulusan::query()->find($id)->status);
    }

    public function test_SRS_ADM_18_workflow_approval_by_the_requester_is_refused(): void
    {
        $this->app->instance(WorkflowEngine::class, new FakeWorkflowEngine);
        $requester = $this->admin();
        $id = $this->actingAs($requester)
            ->putJson('/api/v1/adm/parameter/adm.log_integrasi_simpan_hari', ['nilai' => 60, 'justifikasi' => 'x'])->json('data.id');

        event(new WorkflowCompleted('wf-1', 'adm.konfigurasi_kritikal', 'adm_konfig_kelulusan', (string) $id, 'lulus', $requester->id));

        $this->assertSame(180, $this->params()->get('adm.log_integrasi_simpan_hari'));
        $this->assertSame('menunggu', KonfigKelulusan::query()->find($id)->status);
    }

    public function test_SRS_ADM_18_falls_back_to_built_in_approval_when_the_flow_is_not_configured(): void
    {
        $this->app->instance(WorkflowEngine::class, new FakeWorkflowEngine(flowActive: false));
        $id = $this->actingAs($this->admin())
            ->putJson('/api/v1/adm/parameter/adm.log_integrasi_simpan_hari', ['nilai' => 90, 'justifikasi' => 'x'])->json('data.id');

        $this->assertNull(KonfigKelulusan::query()->find($id)->id_instans_aliran);
        $this->actingAs($this->admin())->postJson("/api/v1/adm/kelulusan/{$id}/lulus")->assertOk();
        $this->assertSame(90, $this->params()->get('adm.log_integrasi_simpan_hari'));
    }

    public function test_SRS_ADM_19_change_record_keeps_before_after_justification_requester_ip_and_approver(): void
    {
        $requester = $this->admin();
        $approver = $this->admin();

        $this->actingAs($requester)
            ->putJson('/api/v1/adm/parameter/adm.sejarah_tugasan_simpan_hari', ['nilai' => 100])
            ->assertStatus(422); // justification mandatory for critical config

        $id = $this->actingAs($requester)
            ->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->putJson('/api/v1/adm/parameter/adm.sejarah_tugasan_simpan_hari', ['nilai' => 100, 'justifikasi' => 'Audit dalaman'])
            ->json('data.id');
        $this->actingAs($approver)->postJson("/api/v1/adm/kelulusan/{$id}/lulus", ['catatan' => 'Setuju']);

        $row = KonfigKelulusan::query()->find($id);
        $this->assertSame(365, $row->nilai_sebelum);
        $this->assertSame(100, $row->nilai_selepas);
        $this->assertSame('Audit dalaman', $row->justifikasi);
        $this->assertSame($requester->id, $row->id_pemohon);
        $this->assertSame('10.1.2.3', $row->alamat_ip_pemohon);
        $this->assertSame($approver->id, $row->id_pelulus);
        $this->assertNotNull($row->diputuskan_pada);

        // The approval decision is in the audit trail with before/after + justification.
        $audit = $this->audit->ofAction('lulus');
        $this->assertCount(1, $audit);
        $this->assertSame('adm', $audit[0]['module']);
        $this->assertSame(['adm.sejarah_tugasan_simpan_hari' => [365, 100]], $audit[0]['changes']);
        $this->assertSame('Audit dalaman', $audit[0]['reason']);

        // The applied row carries the justification, so MOD-AUD's model observer records it with the change.
        $this->assertSame('Audit dalaman', Parameter::query()->where('kunci', 'adm.sejarah_tugasan_simpan_hari')->value('justifikasi'));
    }

    public function test_SRS_ADM_19_pending_and_decided_changes_can_be_listed_and_filtered(): void
    {
        $requester = $this->admin();
        $this->actingAs($requester)->putJson('/api/v1/adm/parameter/adm.siem_hos', ['nilai' => 'a:1', 'justifikasi' => 'x']);

        $this->actingAs($this->admin())->getJson('/api/v1/adm/kelulusan?status=menunggu')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('mod', app()->bound(WorkflowEngine::class) ? 'wfe' : 'terbina')
            ->assertJsonPath('data.0.kunci', 'adm.siem_hos')
            ->assertJsonPath('data.0.boleh_putus', true);

        $this->actingAs($requester)->getJson('/api/v1/adm/kelulusan')->assertJsonPath('data.0.boleh_putus', false)->assertJsonPath('data.0.boleh_batal', true);
        $this->actingAs($this->userWith([]))->getJson('/api/v1/adm/kelulusan')->assertForbidden();
    }
}
