<?php

namespace Tests\Modules\Wfe;

use App\Modules\Wfe\Models\Instans;
use App\Modules\Wfe\Models\Tugasan;

/** SRS-WFE-21: pemangku for a date range. */
class DelegationTest extends WfeTestCase
{
    public function test_SRS_WFE_21_new_tasks_route_to_delegate_and_both_identities_are_recorded(): void
    {
        $approver = $this->user();
        $delegate = $this->user();
        $this->activeFlow($this->structure('ujian.pemangku', [$this->stage('a', [$approver])]));

        $this->actingAs($approver)->postJson('/api/v1/wfe/pemangku', [
            'id_pemangku' => $delegate->id, 'tarikh_mula' => '2026-11-01', 'tarikh_tamat' => '2026-11-15', 'sebab' => 'Cuti tahunan',
        ])->assertCreated();

        $id = $this->engine()->start('ujian.pemangku', 'rekod_ujian', 1, $this->user());
        $task = Tugasan::where('id_instans', $id)->firstOrFail();
        $this->assertSame('individu:'.$delegate->id, $task->penerima_kunci);
        $this->assertSame($approver->id, $task->id_pelulus_asal);
        $this->assertSame([$delegate->id], $this->sentOfType('wfe.tugasan_baharu')[0]['to']);

        $this->actingAs($delegate)->getJson('/api/v1/wfe/tugasan')->assertJsonPath('data.0.bagi_pihak', $approver->name);
        $this->act($delegate, $task->id, 'lulus')->assertOk();

        $entry = Instans::find($id)->sejarah()->where('peristiwa', 'lulus')->firstOrFail();
        $this->assertSame($delegate->id, $entry->id_pengguna);
        $this->assertSame($approver->id, $entry->id_bagi_pihak);
        $this->assertSame($approver->id, collect($this->audited)->firstWhere('action', 'lulus')['meta']['bagi_pihak']);
        $this->assertSame('cipta_pemangku', $this->audited[0]['action']);
    }

    public function test_SRS_WFE_21_delegate_acts_on_pool_tasks_of_the_absent_approver(): void
    {
        $approver = $this->user(null, ['pegawai_hq']);
        $delegate = $this->user();
        $this->activeFlow($this->structure('ujian.pemangku_pool', [$this->stage('a', [['jenis' => 'peranan', 'nilai' => 'pegawai_hq']])]));
        $id = $this->engine()->start('ujian.pemangku_pool', 'rekod_ujian', 1, $this->user());
        $task = Tugasan::where('id_instans', $id)->firstOrFail();

        $this->act($delegate, $task->id, 'lulus')->assertForbidden();
        $this->actingAs($approver)->postJson('/api/v1/wfe/pemangku', ['id_pemangku' => $delegate->id, 'tarikh_mula' => '2026-11-02', 'tarikh_tamat' => '2026-11-02'])->assertCreated();
        $this->act($delegate, $task->id, 'lulus')->assertOk();
        $this->assertSame($approver->id, Instans::find($id)->sejarah()->where('peristiwa', 'lulus')->value('id_bagi_pihak'));
    }

    public function test_SRS_WFE_21_delegation_only_inside_its_period_and_respects_segregation(): void
    {
        $approver = $this->user();
        $applicantAsDelegate = $this->user();
        $this->activeFlow($this->structure('ujian.pemangku_sod', [$this->stage('a', [$approver])]));

        // future period: not yet in force
        $this->actingAs($approver)->postJson('/api/v1/wfe/pemangku', ['id_pemangku' => $applicantAsDelegate->id, 'tarikh_mula' => '2026-12-01', 'tarikh_tamat' => '2026-12-05'])->assertCreated();
        $id = $this->engine()->start('ujian.pemangku_sod', 'rekod_ujian', 1, $applicantAsDelegate);
        $this->assertSame('individu:'.$approver->id, Tugasan::where('id_instans', $id)->value('penerima_kunci'));

        // overlapping period and self-delegation are refused
        $this->actingAs($approver)->postJson('/api/v1/wfe/pemangku', ['id_pemangku' => $applicantAsDelegate->id, 'tarikh_mula' => '2026-12-04', 'tarikh_tamat' => '2026-12-10'])->assertUnprocessable();
        $this->actingAs($approver)->postJson('/api/v1/wfe/pemangku', ['id_pemangku' => $approver->id, 'tarikh_mula' => '2027-01-04', 'tarikh_tamat' => '2027-01-10'])->assertUnprocessable();

        // inside the period the applicant, acting for the approver, still may not approve their own request
        $this->travelTo(now()->setDate(2026, 12, 2));
        $this->act($applicantAsDelegate, Tugasan::where('id_instans', $id)->value('id'), 'lulus')
            ->assertForbidden()->assertJsonPath('code', 'PENGASINGAN_TUGAS');
    }

    public function test_SRS_WFE_21_only_admins_set_a_delegate_for_someone_else(): void
    {
        $approver = $this->user();
        $other = $this->user();
        $delegate = $this->user();
        $admin = $this->user(['wfe.pemangku.kemas_kini']);
        $payload = ['id_pengguna' => $approver->id, 'id_pemangku' => $delegate->id, 'tarikh_mula' => '2026-11-02', 'tarikh_tamat' => '2026-11-03'];

        $this->actingAs($other)->postJson('/api/v1/wfe/pemangku', $payload)->assertForbidden();
        $id = $this->actingAs($admin)->postJson('/api/v1/wfe/pemangku', $payload)->assertCreated()->json('data.id');
        $this->actingAs($other)->deleteJson("/api/v1/wfe/pemangku/{$id}")->assertForbidden();
        $this->actingAs($approver)->deleteJson("/api/v1/wfe/pemangku/{$id}")->assertOk();
        $this->actingAs($this->user([]))->getJson('/api/v1/wfe/pemangku')->assertForbidden();
    }
}
