<?php

namespace Tests\Modules\Adu;

use App\Modules\Adu\Models\AuditCapaianPengadu;
use App\Modules\Adu\Models\PeraturanPenugasan;
use Illuminate\Support\Facades\DB;

class PrivasiPengaduTest extends AduTestCase
{
    public function test_SRS_ADU_20_only_assigned_officer_supervisor_and_unit_aduan_may_see_reporter_identity(): void
    {
        $officer = $this->user(self::PEGAWAI);
        $supervisor = $this->user(self::PEGAWAI);
        $this->supervise($supervisor, $officer);
        PeraturanPenugasan::query()->create(['id_pegawai' => $officer->id]);
        $aduan = $this->aduan();
        $other = $this->user(self::PEGAWAI);
        $unit = $this->user(self::UNIT_ADUAN);

        // Everyone with access sees the complaint, but identity masked and nowhere in the payload.
        $res = $this->actingAs($other)->getJson("/api/v1/adu/aduan/{$aduan->id}")->assertOk();
        $this->assertStringNotContainsString('Ali bin Abu', $res->getContent());
        $this->assertStringNotContainsString('012-3456789', $res->getContent());
        $this->assertStringNotContainsString('ali@contoh.test', $res->getContent());
        $this->assertFalse($res->json('data.pengadu.boleh_dedah'));
        $this->assertSame('A••••••••••', $res->json('data.pengadu.bertopeng.nama'));

        $this->actingAs($other)->postJson("/api/v1/adu/aduan/{$aduan->id}/pengadu/dedah", ['tujuan' => 'Ingin tahu sahaja'])->assertForbidden();
        $this->assertSame(0, AuditCapaianPengadu::query()->count());
        $this->assertAudited('lihat', ['modul' => 'adu', 'id_pengguna' => $other->id, 'status_transaksi' => 'gagal']);

        foreach ([[$officer, 'pegawai_ditugaskan'], [$supervisor, 'penyelia'], [$unit, 'unit_aduan']] as [$who, $basis]) {
            $this->actingAs($who)->postJson("/api/v1/adu/aduan/{$aduan->id}/pengadu/dedah", ['tujuan' => 'Menghubungi pengadu untuk lawatan tapak'])->assertOk()
                ->assertJsonPath('data.nama', 'Ali bin Abu')->assertJsonPath('data.no_telefon', '012-3456789');
            $this->assertSame($basis, AuditCapaianPengadu::query()->where('id_pegawai', $who->id)->value('asas_capaian'));
        }
    }

    public function test_SRS_ADU_21_identity_is_encrypted_at_rest_and_every_access_is_logged_with_purpose(): void
    {
        $aduan = $this->aduan();
        $raw = DB::table('pengadu')->where('aduan_id', $aduan->id)->first();
        foreach (['nama' => 'Ali bin Abu', 'no_telefon' => '012-3456789', 'emel' => 'ali@contoh.test', 'alamat' => 'No. 1, Jalan Ujian'] as $col => $plain) {
            $this->assertNotEmpty($raw->{$col});
            $this->assertStringNotContainsString($plain, (string) $raw->{$col});
        }
        $this->assertSame('sulit', $raw->klasifikasi_data);

        $unit = $this->user(self::UNIT_ADUAN);
        $this->actingAs($unit)->postJson("/api/v1/adu/aduan/{$aduan->id}/pengadu/dedah", ['tujuan' => 'Pengesahan maklumat pengadu', 'medan' => ['no_telefon']])->assertOk()
            ->assertExactJson(['data' => ['no_telefon' => '012-3456789']]);
        $this->actingAs($unit)->postJson("/api/v1/adu/aduan/{$aduan->id}/pengadu/dedah", [])->assertUnprocessable()->assertJsonValidationErrors('tujuan');

        $log = AuditCapaianPengadu::query()->sole();
        $this->assertSame([$unit->id, ['no_telefon'], 'Pengesahan maklumat pengadu'], [$log->id_pegawai, $log->medan_dicapai, $log->tujuan_capaian]);
        $this->assertNotNull($log->alamat_ip);
        $this->assertAudited('lihat', ['modul' => 'adu', 'nama_entiti' => 'pengadu', 'id_pengguna' => $unit->id]);

        // The audit trail of the reporter row masks the values.
        $this->assertDatabaseMissing('audit_perubahan_medan', ['nilai_baharu' => 'Ali bin Abu']);

        // Access-audit report.
        $auditor = $this->user(['adu.laporan.lihat', 'adu.audit_capaian.lihat']);
        $this->actingAs($auditor)->getJson('/api/v1/adu/laporan/audit_capaian')->assertOk()->assertJsonPath('data.0.tujuan', 'Pengesahan maklumat pengadu');
        $this->actingAs($this->user(['adu.laporan.lihat']))->getJson('/api/v1/adu/laporan/audit_capaian')->assertForbidden();
    }
}
