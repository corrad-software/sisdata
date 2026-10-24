<?php

namespace Tests\Modules\Kes;

use App\Models\User;
use App\Modules\Kes\Contracts\MfaAssurance;
use App\Modules\Kes\Models\PanelPeguam;
use App\Modules\Kes\Support\Akses;
use App\Modules\Kes\Support\SsoMfaAssurance;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class AksesTest extends KesTestCase
{
    public function test_SRS_KES_13_user_without_legal_role_cannot_open_records_or_documents(): void
    {
        $k = $this->kes([$this->lot()]);
        // Pegawai Operasi style: kes.kes.lihat via wildcard but no explicit Rahsia grant.
        $operasi = $this->user(['kes.kes.lihat', 'kes.kes.kemas_kini', 'pro.lot.lihat']);
        $this->actingAs($operasi)->getJson('/api/v1/kes/kes')->assertForbidden();
        $this->actingAs($operasi)->getJson('/api/v1/kes/kes/'.$k->id)->assertForbidden();
        $this->actingAs($operasi)->getJson('/api/v1/kes/kes/'.$k->id.'/dokumen')->assertForbidden();
        $this->actingAs($this->user([]))->getJson('/api/v1/kes/kes/'.$k->id)->assertForbidden();
        $this->assertDatabaseHas('kes_log_capaian', ['id_pengguna' => $operasi->id, 'jenis_tindakan' => 'ditolak', 'kes_mahkamah_id' => $k->id]);
        $this->assertAudited('akses_ditolak', ['modul' => 'kes']);
    }

    public function test_SRS_KES_13_every_view_download_and_print_logged_with_ip_and_time(): void
    {
        $k = $this->kes([$this->lot()]);
        $u = $this->user();
        $this->actingAs($u)->getJson('/api/v1/kes/kes/'.$k->id, ['REMOTE_ADDR' => '10.1.2.3'])->assertOk();
        $row = DB::table('kes_log_capaian')->where('kes_mahkamah_id', $k->id)->where('jenis_tindakan', 'lihat')->first();
        $this->assertNotNull($row);
        $this->assertSame($u->id, $row->id_pengguna);
        $this->assertNotNull($row->alamat_ip);
        $this->assertNotNull($row->cap_masa);
        $this->assertAudited('lihat', ['modul' => 'kes', 'id_rekod' => $k->id]);

        $this->actingAs($u)->getJson('/api/v1/kes/kes')->assertOk();
        $this->assertDatabaseHas('kes_log_capaian', ['id_pengguna' => $u->id, 'jenis_tindakan' => 'lihat_senarai']);

        $pdf = $this->actingAs($u)->get('/api/v1/kes/kes/'.$k->id.'/cetak')->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertDatabaseHas('kes_log_capaian', ['kes_mahkamah_id' => $k->id, 'jenis_tindakan' => 'cetak']);
        $this->assertAudited('cetak', ['modul' => 'kes']);

        // Access log is append-only.
        $this->expectException(QueryException::class);
        DB::table('kes_log_capaian')->where('id', $row->id)->update(['alamat_ip' => '0.0.0.0']);
    }

    public function test_SRS_KES_13_access_log_report_for_auditors(): void
    {
        $k = $this->kes([$this->lot()]);
        $u = $this->user();
        $this->actingAs($u)->getJson('/api/v1/kes/kes/'.$k->id)->assertOk();
        $this->actingAs($u)->getJson('/api/v1/kes/log-capaian')->assertForbidden();
        $auditor = $this->user(['kes.log_capaian.lihat', 'kes.laporan.eksport']);
        $rows = $this->actingAs($auditor)->getJson('/api/v1/kes/log-capaian?kes_id='.$k->id)->assertOk()->json('data');
        $this->assertSame('lihat', $rows[0]['jenis_tindakan']);
        $this->assertSame($k->no_rujukan, $rows[0]['no_rujukan']);
        $this->actingAs($auditor)->get('/api/v1/kes/log-capaian/eksport?format=xlsx')->assertOk();
    }

    public function test_SRS_KES_14_records_are_rahsia_and_need_mfa_through_sso(): void
    {
        $k = $this->kes([$this->lot()]);
        $this->assertSame('rahsia', $k->klasifikasi);
        $this->app->instance(MfaAssurance::class, app(SsoMfaAssurance::class));
        $this->app->forgetScopedInstances();

        $password = $this->user();
        $this->actingAs($password)->getJson('/api/v1/kes/kes/'.$k->id)->assertForbidden()->assertJsonPath('code', 'MFA_DIPERLUKAN');
        $this->assertDatabaseHas('kes_log_capaian', ['id_pengguna' => $password->id, 'jenis_tindakan' => 'ditolak']);

        $sso = $this->user();
        $sso->forceFill(['jenis_pengesahan' => 'sso'])->save();
        $this->actingAs($sso)->withSession(['oidc.id_token' => 'token-ujian'])->getJson('/api/v1/kes/kes/'.$k->id)->assertOk();

        // Classification is enforced by scope too: a user limited to "dalaman" sees nothing.
        $this->app->instance(MfaAssurance::class, $this->mfa);
        $this->app->forgetScopedInstances();
        $dalaman = User::factory()->withPermissions(KesTestCase::PEGAWAI)->withScope('klasifikasi', ['awam', 'dalaman'])->create(['role' => 'user', 'is_active' => true]);
        $this->actingAs($dalaman)->getJson('/api/v1/kes/kes/'.$k->id)->assertNotFound();
    }

    public function test_out_of_scope_district_is_not_found_and_lists_are_scoped(): void
    {
        $petaling = $this->kes([$this->lot()]);
        $sepang = $this->kes([$this->lot('100901')]);
        $district = $this->user(KesTestCase::PEGAWAI, ['1008']);
        $this->actingAs($district)->getJson('/api/v1/kes/kes/'.$sepang->id)->assertNotFound();
        $this->actingAs($district)->getJson('/api/v1/kes/kes/'.$petaling->id)->assertOk();
        $ids = collect($this->actingAs($district)->getJson('/api/v1/kes/kes')->json('data'))->pluck('id')->all();
        $this->assertSame([$petaling->id], $ids);
        // Cannot link a lot outside the district.
        $this->actingAs($district)->postJson('/api/v1/kes/kes/'.$petaling->id.'/lot', ['lot_ids' => [$this->lot('100901')]])->assertUnprocessable();
    }

    public function test_external_panel_counsel_sees_only_assigned_cases_after_mais_approval(): void
    {
        $ketua = $this->user(KesTestCase::KETUA);
        $assigned = $this->kes([$this->lot()], $ketua);
        $other = $this->kes([$this->lot()], $ketua);
        $firmUser = $this->user(['kes.kes.lihat', 'kes.rahsia.lihat', 'kes.kes.kemas_kini']);
        $panel = PanelPeguam::query()->create(['nama_firma' => 'Tetuan Panel Ujian', 'status' => 'aktif', 'id_pengguna' => $firmUser->id]);

        $this->actingAs($ketua)->postJson('/api/v1/kes/kes/'.$assigned->id.'/peguam/panel', ['kes_panel_peguam_id' => $panel->id])->assertCreated()->assertJsonPath('data.status_lantikan', 'menunggu_kelulusan');
        $this->assertSame('kes.lantikan_panel', end($this->workflow->started)['flow']);
        $this->actingAs($firmUser)->getJson('/api/v1/kes/kes/'.$assigned->id)->assertNotFound();

        $peguam = $assigned->peguam()->where('kes_panel_peguam_id', $panel->id)->firstOrFail();
        $this->selesaiAliran('kes.lantikan_panel', 'kes_peguam', $peguam->id, (string) $peguam->id_instans_aliran);
        app(Akses::class)->lupa();
        $this->actingAs($firmUser)->getJson('/api/v1/kes/kes/'.$assigned->id)->assertOk();
        $this->actingAs($firmUser)->getJson('/api/v1/kes/kes/'.$other->id)->assertNotFound();
        $this->assertSame([$assigned->id], collect($this->actingAs($firmUser)->getJson('/api/v1/kes/kes')->json('data'))->pluck('id')->all());
    }
}
