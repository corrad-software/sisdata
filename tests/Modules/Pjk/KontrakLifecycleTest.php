<?php

namespace Tests\Modules\Pjk;

use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\PermohonanSewaan;
use App\Modules\Pjk\Models\PremisAset;
use App\Modules\Pjk\Models\VersiKontrak;
use App\Modules\Pjk\Services\KontrakService;
use App\Modules\Pjk\Support\Aliran;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class KontrakLifecycleTest extends PjkTestCase
{
    public function test_SRS_PJK_01_contract_records_tenant_premise_period_rate_deposit_terms_category_and_status(): void
    {
        $user = $this->user();
        $k = $this->draft($user);

        $res = $this->actingAs($user)->getJson("/api/v1/pjk/kontrak/{$k->id}")->assertOk();
        $res->assertJsonPath('data.status', 'draf')
            ->assertJsonPath('data.kadar.nilai', '1500.00')
            ->assertJsonPath('data.kadar.rm', 'RM 1,500.00')
            ->assertJsonPath('data.deposit.nilai', '3000.00')
            ->assertJsonPath('data.kategori_hartanah', 'premis_perniagaan')
            ->assertJsonPath('data.terma', 'Terma ujian')
            ->assertJsonPath('data.klasifikasi', 'sulit')
            ->assertJsonPath('data.nilai_kontrak.nilai', '36000.00');
        $this->assertNotNull($res->json('data.penyewa_rekod.nama'));
        $this->assertStringStartsWith('PJK/K/2026/', (string) $k->no_kontrak);
        $this->assertSame($k->kod_daerah, '1008');
        $this->assertAudited('cipta', ['nama_entiti' => 'kontrak_pajakan', 'id_rekod' => $k->id]);
        $this->assertAudited('lihat', ['nama_entiti' => 'kontrak_pajakan', 'id_rekod' => $k->id]);
    }

    public function test_SRS_PJK_01_validation_rejects_bad_dates_rate_and_overlapping_premise(): void
    {
        $user = $this->user();
        $premis = $this->premis();
        $penyewa = $this->penyewa();
        $this->actingAs($user)->postJson('/api/v1/pjk/kontrak', ['premis_id' => $premis->id, 'penyewa_id' => $penyewa->id, 'tarikh_mula' => '2026-05-01', 'tarikh_tamat' => '2026-04-01', 'kadar' => '-1'])
            ->assertStatus(422)->assertJsonValidationErrors(['tarikh_tamat', 'kadar']);

        $this->active($user, null, ['premis_id' => $premis->id, 'penyewa_id' => $penyewa->id]);
        $this->actingAs($user)->postJson('/api/v1/pjk/kontrak', ['premis_id' => $premis->id, 'penyewa_id' => $penyewa->id, 'tarikh_mula' => '2027-01-01', 'tarikh_tamat' => '2028-01-01', 'kadar' => '100'])
            ->assertStatus(422)->assertJsonValidationErrors(['premis_id']);
    }

    public function test_SRS_PJK_02_status_transitions_recorded_with_date_and_officer(): void
    {
        $user = $this->user();
        $approver = $this->user(['pjk.kontrak.lihat']);
        $k = $this->active($user, $approver);

        $history = DB::table('sejarah_status_kontrak')->where('kontrak_id', $k->id)->orderBy('id')->get();
        $this->assertSame(['draf', 'dalam_kelulusan', 'aktif'], $history->pluck('status_selepas')->all());
        $this->assertSame($user->id, $history[1]->id_pegawai);
        $this->assertSame($approver->id, $history[2]->id_pegawai);
        $this->assertNotNull($history[2]->created_at);

        $res = $this->actingAs($user)->getJson("/api/v1/pjk/kontrak/{$k->id}/pindaan")->assertOk();
        $this->assertCount(3, $res->json('sejarah_status'));
    }

    public function test_SRS_PJK_02_automatic_hampir_tamat_and_tamat_tempoh(): void
    {
        $user = $this->user();
        $near = $this->active($user, null, ['tarikh_mula' => '2025-12-01', 'tarikh_tamat' => '2026-12-31']);
        $ended = $this->active($user, null, ['tarikh_mula' => '2025-01-01', 'tarikh_tamat' => '2026-10-14']);

        $r = app(KontrakService::class)->refreshStatuses(Carbon::today());

        $this->assertSame(1, $r['hampir_tamat']);
        $this->assertSame(1, $r['tamat_tempoh']);
        $this->assertSame('hampir_tamat', $near->refresh()->status);
        $this->assertSame('tamat_tempoh', $ended->refresh()->status);
        $this->assertSame('kosong', PremisAset::query()->withoutGlobalScope('access')->find($ended->premis_id)?->status_premis);
        $this->assertSame('disewa', PremisAset::query()->withoutGlobalScope('access')->find($near->premis_id)?->status_premis);
    }

    public function test_SRS_PJK_03_premise_status_changes_automatically_on_activation(): void
    {
        $user = $this->user();
        $k = $this->draft($user);
        $premis = PremisAset::query()->withoutGlobalScope('access')->findOrFail($k->premis_id);
        $this->assertSame('kosong', $premis->status_premis);

        $this->actingAs($user)->postJson("/api/v1/pjk/kontrak/{$k->id}/hantar")->assertOk();
        $this->assertSame('kosong', $premis->refresh()->status_premis);
        $this->complete(Aliran::KONTRAK, 'kontrak_pajakan', $k->id, 'lulus', $this->user(['pjk.kontrak.lihat'])->id);

        $this->assertSame('disewa', $premis->refresh()->status_premis);
        $h = DB::table('sejarah_status_premis')->where('premis_id', $premis->id)->sole();
        $this->assertSame(['kosong', 'disewa'], [$h->status_sebelum, $h->status_selepas]);
        // "telah disewa" can't be set by hand.
        $this->actingAs($user)->putJson("/api/v1/pjk/premis/{$premis->id}", ['status_premis' => 'penyelenggaraan'])->assertStatus(422);
    }

    public function test_SRS_PJK_05_approval_runs_in_wfe_with_value_and_category_context_never_locally(): void
    {
        $user = $this->user();
        $k = $this->draft($user);
        $this->actingAs($user)->postJson("/api/v1/pjk/kontrak/{$k->id}/hantar")->assertOk()->assertJsonPath('data.status', 'dalam_kelulusan');

        $wf = $this->workflow->last();
        $this->assertSame(Aliran::KONTRAK, $wf['flow']);
        $this->assertSame('kontrak_pajakan', $wf['subjectType']);
        $this->assertSame(36000.0, $wf['context']['nilai_kontrak']);
        $this->assertSame('premis_perniagaan', $wf['context']['kategori_hartanah']);
        $this->assertSame('1008', $wf['context']['kod_daerah']);
        $this->assertSame('/admin/pjk/kontrak/'.$k->id, $wf['context']['pautan']);
        $this->assertSame('dalam_kelulusan', $k->refresh()->status);

        // No local approve endpoint exists; rejection returns the draft.
        $this->complete(Aliran::KONTRAK, 'kontrak_pajakan', $k->id, 'tolak', $this->user(['pjk.kontrak.lihat'])->id);
        $this->assertSame('draf', $k->refresh()->status);
        $this->assertCount(1, $this->notifier->ofType('pjk.keputusan_kelulusan'));
    }

    public function test_SRS_PJK_05_missing_flow_is_a_clear_error(): void
    {
        $this->workflow->configured = false;
        $user = $this->user();
        $k = $this->draft($user);
        $this->actingAs($user)->postJson("/api/v1/pjk/kontrak/{$k->id}/hantar")->assertStatus(422)->assertJsonValidationErrors(['aliran']);
        $this->assertSame('draf', $k->refresh()->status);
    }

    public function test_SRS_PJK_05_activation_creates_schedule_deposit_and_first_version(): void
    {
        $k = $this->active($this->user());

        $this->assertSame(24, DB::table('jadual_caj')->where('kontrak_id', $k->id)->where('kod_caj', 'sewa')->count());
        $this->assertSame(1, DB::table('deposit_cagaran')->where('kontrak_id', $k->id)->count());
        $v = VersiKontrak::query()->where('kontrak_id', $k->id)->sole();
        $this->assertSame('asal', $v->jenis_pindaan);
        $this->assertSame(1, $k->no_versi);
    }

    public function test_SRS_PJK_01_access_requires_permission_and_district_scope(): void
    {
        $owner = $this->user();
        $k = $this->draft($owner);

        $this->actingAs($this->user(['pjk.penyewa.lihat']))->getJson('/api/v1/pjk/kontrak')->assertForbidden();
        $this->actingAs($this->user(['pjk.kontrak.lihat']))->postJson('/api/v1/pjk/kontrak', [])->assertForbidden();

        $sepang = $this->user(self::PENDAFTAR, ['1009']);
        $this->actingAs($sepang)->getJson("/api/v1/pjk/kontrak/{$k->id}")->assertNotFound();
        $this->assertSame(0, $this->actingAs($sepang)->getJson('/api/v1/pjk/kontrak')->assertOk()->json('meta.total'));
        $this->actingAs($sepang)->putJson("/api/v1/pjk/kontrak/{$k->id}", ['kadar' => '1.00'])->assertNotFound();

        $petaling = $this->user(self::PENDAFTAR, ['1008']);
        $this->actingAs($petaling)->getJson("/api/v1/pjk/kontrak/{$k->id}")->assertOk();
    }

    public function test_SRS_PJK_01_acceptance_1_portal_application_to_active_contract(): void
    {
        $portal = $this->user(['pjk.portal.cipta']);
        $premis = $this->premis();
        $payload = ['rujukan_portal' => 'PWS-0001', 'kod_premis' => $premis->kod_premis, 'tempoh_bulan' => 24, 'tarikh_mula_dicadang' => '2026-11-01',
            'pemohon' => ['jenis_entiti' => 'individu', 'nama' => 'Pemohon Portal', 'no_pengenalan' => '990000991234', 'emel' => 'portal@contoh.invalid']];
        $this->actingAs($portal)->postJson('/api/v1/pjk/portal/permohonan', $payload)->assertCreated()->assertJsonPath('data.status', 'diterima');
        $this->actingAs($portal)->postJson('/api/v1/pjk/portal/permohonan', $payload)->assertOk(); // idempotent
        $this->assertSame(1, PermohonanSewaan::query()->withoutGlobalScope('access')->count());

        $officer = $this->user();
        $p = PermohonanSewaan::query()->withoutGlobalScope('access')->sole();
        $res = $this->actingAs($officer)->postJson("/api/v1/pjk/permohonan/{$p->id}/kontrak", ['kadar' => '1500.00', 'hantar' => true])->assertCreated();
        $k = Kontrak::query()->withoutGlobalScope('access')->findOrFail($res->json('data.id'));
        $this->assertSame('dalam_kelulusan', $k->status);
        $this->assertSame('portal', $k->sumber);
        $this->assertSame('990000-99-1234', DB::table('penyewa')->where('id', $k->penyewa_id)->value('no_pengenalan'));
        $this->actingAs($portal)->getJson('/api/v1/pjk/portal/permohonan/PWS-0001')->assertOk()->assertJsonPath('data.status', 'kontrak_disediakan');

        $this->complete(Aliran::KONTRAK, 'kontrak_pajakan', $k->id, 'lulus', $this->user(['pjk.kontrak.lihat'])->id);

        $this->assertSame('aktif', $k->refresh()->status);
        $this->assertSame('disewa', $premis->refresh()->status_premis);
        $this->actingAs($portal)->getJson('/api/v1/pjk/portal/permohonan/PWS-0001')->assertOk()->assertJsonPath('data.status', 'lulus');
    }
}
