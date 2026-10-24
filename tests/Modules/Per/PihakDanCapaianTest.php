<?php

namespace Tests\Modules\Per;

use App\Modules\Per\Models\Pemerolehan;
use Illuminate\Support\Facades\DB;

class PihakDanCapaianTest extends PerTestCase
{
    private function withHeir(): array
    {
        $registrar = $this->officer([...self::PEGAWAI, 'per.sulit.lihat']);
        $case = $this->newCase($registrar, 'faraid', [
            'butiran' => ['nama_si_mati' => 'Abdullah bin Hamid', 'no_kes_faraid' => 'FRD-1'],
            'pihak' => [
                ['peranan_pihak' => 'waris', 'nama' => 'Aminah binti Abdullah', 'hubungan' => 'Anak', 'jenis_dokumen_pengenalan' => 'mykad', 'no_dokumen_pengenalan' => '850101-10-5566', 'bahagian_pusaka' => '1/2'],
                ['peranan_pihak' => 'si_mati', 'nama' => 'Abdullah bin Hamid'],
            ],
        ]);

        return [$registrar, $case];
    }

    public function test_SRS_PER_23_parties_recorded_by_channel_role_with_relationship_and_verification(): void
    {
        [$registrar, $case] = $this->withHeir();
        $detail = $this->actingAs($registrar)->getJson("/api/v1/per/kes/{$case->id}")->assertOk();
        $heir = collect($detail->json('data.pihak'))->firstWhere('peranan_pihak', 'waris');
        $this->assertSame('Aminah binti Abdullah', $heir['nama']);
        $this->assertSame('Anak', $heir['hubungan']);
        $this->assertSame('1/2', $heir['bahagian_pusaka']);
        $this->assertSame('belum_disahkan', $heir['status_pengesahan']);
        $this->assertSame('******-**-5566', $heir['no_dokumen_pengenalan'], 'No. pengenalan sentiasa dikaburkan dalam senarai.');

        // A role that does not belong to the channel is refused.
        $this->actingAs($registrar)->postJson("/api/v1/per/kes/{$case->id}/pihak", ['peranan_pihak' => 'penjual', 'nama' => 'X'])->assertStatus(422);
        $id = $this->actingAs($registrar)->postJson("/api/v1/per/kes/{$case->id}/pihak", ['peranan_pihak' => 'pegawai_mahkamah', 'nama' => 'Pendaftar Mahkamah'])->assertCreated()->json('data.id');
        $this->actingAs($registrar)->putJson("/api/v1/per/kes/{$case->id}/pihak/{$id}", ['status_pengesahan' => 'disahkan'])->assertOk()->assertJsonPath('data.status_pengesahan', 'disahkan');
        $this->actingAs($registrar)->deleteJson("/api/v1/per/kes/{$case->id}/pihak/{$id}")->assertOk();

        // ID numbers are encrypted at rest.
        $raw = DB::table('pihak_berkaitan_pemerolehan')->where('nama', 'Aminah binti Abdullah')->value('no_dokumen_pengenalan');
        $this->assertStringNotContainsString('850101', (string) $raw);
        $this->assertStringNotContainsString('Abdullah bin Hamid', (string) DB::table('pemerolehan')->where('id', $case->id)->value('butiran_peribadi'));
    }

    public function test_SRS_PER_23_personal_data_masked_without_sulit_permission(): void
    {
        [, $case] = $this->withHeir();
        $viewer = $this->officer(['per.kes.lihat']);
        $detail = $this->actingAs($viewer)->getJson("/api/v1/per/kes/{$case->id}")->assertOk();
        $this->assertTrue($detail->json('data.peribadi_dikaburkan'));
        $this->assertSame('A***** b** H****', $detail->json('data.butiran.nama_si_mati'));
        $heir = collect($detail->json('data.pihak'))->firstWhere('peranan_pihak', 'waris');
        $this->assertSame('A***** b**** A*****', $heir['nama']);

        $partyId = $heir['id'];
        $this->actingAs($viewer)->getJson("/api/v1/per/kes/{$case->id}/pihak/{$partyId}/penuh")->assertForbidden();

        $cleared = $this->officer(['per.kes.lihat', 'per.sulit.lihat']);
        $this->actingAs($cleared)->getJson("/api/v1/per/kes/{$case->id}/pihak/{$partyId}/penuh")->assertOk()
            ->assertJsonPath('data.no_dokumen_pengenalan', '850101-10-5566');
        $this->assertAudited('lihat', ['modul' => 'per', 'nama_entiti' => 'pihak_berkaitan_pemerolehan', 'id_rekod' => $partyId, 'id_pengguna' => $cleared->id]);
    }

    public function test_SRS_PER_23_personal_fields_masked_in_audit_trail(): void
    {
        [$registrar, $case] = $this->withHeir();
        $partyId = $case->pihak()->where('peranan_pihak', 'waris')->value('id');
        $this->actingAs($registrar)->putJson("/api/v1/per/kes/{$case->id}/pihak/{$partyId}", ['no_dokumen_pengenalan' => '850101-10-9999'])->assertOk();

        $change = DB::table('audit_perubahan_medan')->join('audit_rekod', 'audit_rekod.id', '=', 'audit_perubahan_medan.id_audit')
            ->where('audit_rekod.nama_entiti', 'pihak_berkaitan_pemerolehan')->where('audit_rekod.id_rekod', (string) $partyId)
            ->where('nama_medan', 'no_dokumen_pengenalan')->orderByDesc('audit_rekod.no_urutan')->first(['nilai_asal', 'nilai_baharu']);
        $this->assertNotNull($change);
        $this->assertStringNotContainsString('9999', (string) $change->nilai_baharu);
        $this->assertStringNotContainsString('5566', (string) $change->nilai_asal);
    }

    public function test_SRS_PER_23_officer_without_sulit_cannot_overwrite_personal_fields(): void
    {
        [, $case] = $this->withHeir();
        $editor = $this->officer(self::PEGAWAI);
        $this->actingAs($editor)->putJson("/api/v1/per/kes/{$case->id}", ['butiran' => ['nama_si_mati' => 'Nama Lain']])->assertOk();
        $this->assertSame('Abdullah bin Hamid', $case->refresh()->butiran_peribadi['nama_si_mati']);
    }

    public function test_SRS_PER_01_out_of_scope_district_and_bahagian_cannot_see_or_register(): void
    {
        $owner = $this->officer();
        $case = $this->newCase($owner, 'wakaf', ['lot_id' => $this->lot('PT 9', '100803')]);

        $other = $this->officer(self::PEGAWAI, ['daerah' => ['1001']]);
        $this->actingAs($other)->getJson("/api/v1/per/kes/{$case->id}")->assertNotFound();
        $this->assertSame(0, $this->actingAs($other)->getJson('/api/v1/per/kes')->json('meta.total'));
        // Out-of-scope lots don't exist for this user (LotRegistry contract) → rejected as invalid lot.
        $this->createCase($other, 'wakaf', ['lot_id' => $this->lot('PT 10', '100803')])->assertUnprocessable()->assertJsonValidationErrors('lot_id');
        $this->createCase($other, 'wakaf', ['lot_id' => $this->lot('PT 11', '100101')])->assertCreated();

        $baitulmal = $this->officer(self::PEGAWAI, ['bahagian' => ['baitulmal']]);
        $this->actingAs($baitulmal)->getJson("/api/v1/per/kes/{$case->id}")->assertNotFound();
        $this->createCase($baitulmal, 'wakaf')->assertForbidden();
        $this->createCase($baitulmal, 'faraid')->assertCreated();

        $this->actingAs($this->officer(['per.kes.lihat']))->putJson("/api/v1/per/kes/{$case->id}", ['tajuk' => 'X'])->assertForbidden();
        $this->actingAs($this->officer(['per.kes.lihat']))->postJson("/api/v1/per/kes/{$case->id}/maju")->assertForbidden();
        $this->actingAs($this->officer(['per.kes.lihat']))->postJson("/api/v1/per/kes/{$case->id}/batal", ['sebab' => 'Tiada kebenaran'])->assertForbidden();
    }

    public function test_SRS_PER_30_cancel_recorded_with_reason(): void
    {
        $owner = $this->officer([...self::PEGAWAI, 'per.kes.padam']);
        $case = $this->newCase($owner, 'wakaf');
        $this->actingAs($owner)->postJson("/api/v1/per/kes/{$case->id}/batal", ['sebab' => 'Pewakaf menarik balik hasrat'])->assertOk()
            ->assertJsonPath('data.status', Pemerolehan::DIBATALKAN);
        $this->assertAudited('batal', ['modul' => 'per', 'id_rekod' => $case->id, 'sebab_perubahan' => 'Pewakaf menarik balik hasrat']);
        $this->assertAudited('kemas_kini', ['nama_entiti' => 'pemerolehan', 'id_rekod' => $case->id])
            ->assertFieldChanged('status_pemerolehan', 'dalam_proses', 'dibatalkan');
        $this->advance($owner, $case)->assertStatus(409);
    }

    public function test_SRS_PER_30_every_change_audited_with_old_new_values_and_classification(): void
    {
        $owner = $this->officer();
        $case = $this->newCase($owner, 'tanah_warta');
        $this->assertAudited('cipta', ['modul' => 'per', 'nama_entiti' => 'pemerolehan', 'id_rekod' => $case->id, 'id_pengguna' => $owner->id]);

        $this->actingAs($owner)->withHeader('X-Sebab-Perubahan', rawurlencode('Pembetulan tajuk'))
            ->putJson("/api/v1/per/kes/{$case->id}", ['tajuk' => 'Tapak masjid Kg. Ujian', 'sebab' => 'Pembetulan tajuk'])->assertOk();
        $this->assertAudited('kemas_kini', ['nama_entiti' => 'pemerolehan', 'id_rekod' => $case->id, 'sebab_perubahan' => 'Pembetulan tajuk'])
            ->assertFieldChanged('tajuk', 'Kes ujian tanah_warta', 'Tapak masjid Kg. Ujian');

        $this->actingAs($owner)->getJson("/api/v1/per/kes/{$case->id}")->assertOk();
        $this->assertAudited('lihat', ['modul' => 'per', 'nama_entiti' => 'pemerolehan', 'id_rekod' => $case->id]);
        $this->assertSame('sulit', DB::table('audit_rekod')->where('nama_entiti', 'pemerolehan')->where('id_rekod', $case->id)->where('jenis_tindakan', 'kemas_kini')->value('klasifikasi'));
    }
}
