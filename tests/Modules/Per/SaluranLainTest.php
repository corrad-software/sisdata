<?php

namespace Tests\Modules\Per;

use App\Modules\Per\Models\Pemerolehan;
use App\Modules\Per\Support\ChannelCatalog;

/**
 * Kriteria penerimaan 3: one test case per each of the other ten channels is
 * registered, moved through its stages and closed, with its specific fields
 * and mandatory documents fully recorded.
 */
class SaluranLainTest extends PerTestCase
{
    private function endToEnd(string $channel, array $parties = []): Pemerolehan
    {
        $user = $this->officer([...self::PEGAWAI, 'per.sulit.lihat']);
        $approver = $this->officer(['per.kes.lihat']);
        $closer = $this->officer(['per.kes.lihat', 'per.hak_milik.cipta']);

        $case = $this->newCase($user, $channel, ['pihak' => $parties, 'nilai_pemerolehan' => 120000]);
        $case = $this->driveToFinalStage($user, $approver, $approver, $case);

        $this->actingAs($closer)->postJson("/api/v1/per/kes/{$case->id}/tutup", $this->closePayload())->assertOk()
            ->assertJsonPath('data.status', Pemerolehan::SELESAI)
            ->assertJsonPath('data.status_kelengkapan_dokumen', 'lengkap')
            ->assertJsonPath('data.kekurangan_dokumen', []);

        $case = Pemerolehan::query()->withoutGlobalScope('access')->with('peringkat', 'dokumen')->findOrFail($case->id);
        foreach (ChannelCatalog::fields($channel) as $key => $f) {
            if (isset($f['peringkat'])) {
                $this->assertNotEmpty($case->butiran()[$key] ?? null, "Medan {$key} direkodkan");
            }
        }
        $this->assertTrue($case->peringkat->every(fn ($s) => $s->status_peringkat === 'selesai' && $s->tarikh_masuk && $s->tarikh_keluar));
        $this->assertNotNull($case->id_hak_milik_dijana);
        $this->assertSame(1, collect($this->titles->titles)->where('source', 'pemerolehan:'.$case->id)->count());
        $this->assertSame(0, $case->tempoh_keseluruhan_hari);
        $this->assertTrue($case->dokumen->every(fn ($d) => $d->rujukan_dms !== null && $this->dms->docs[$d->rujukan_dms]['owner_type'] === 'pemerolehan'));

        return $case;
    }

    public function test_SRS_PER_06_serah_milik_memo_to_registration_and_vault(): void
    {
        $this->endToEnd('serah_milik', [['peranan_pihak' => 'penyerah_hak', 'nama' => 'Penyerah Sintetik']]);
    }

    public function test_SRS_PER_06_serahan_hakmilik_flow_closes(): void
    {
        $this->endToEnd('serahan_hakmilik', [['peranan_pihak' => 'penyerah_hak', 'nama' => 'Penyerah Hak Sintetik']]);
    }

    public function test_SRS_PER_07_daftar_kematian_heirs_and_property_verification(): void
    {
        $case = $this->endToEnd('daftar_kematian', [
            ['peranan_pihak' => 'si_mati', 'nama' => 'Allahyarham Sintetik'],
            ['peranan_pihak' => 'waris', 'nama' => 'Waris Sintetik', 'hubungan' => 'Anak', 'status_pengesahan' => 'disahkan'],
        ]);
        $this->assertSame('disahkan', $case->butiran_saluran['status_pengesahan_harta']);
    }

    public function test_SRS_PER_08_perintah_arb_order_and_title_status_update(): void
    {
        $case = $this->endToEnd('perintah_arb', [['peranan_pihak' => 'si_mati', 'nama' => 'Si Mati ARB']]);
        $this->assertNotEmpty($case->butiran_saluran['status_hak_milik_selepas_perintah']);
    }

    public function test_SRS_PER_09_serahan_hakmilik_records_ptg_registration(): void
    {
        $case = $this->endToEnd('serahan_hakmilik');
        $this->assertSame('didaftar', $case->butiran_saluran['status_pendaftaran_ptg']);
        $this->assertNotEmpty($case->butiran_saluran['no_perserahan_ptg']);
    }

    public function test_SRS_PER_10_perintah_mahkamah_la_probet_with_heirs(): void
    {
        $case = $this->endToEnd('perintah_mahkamah', [['peranan_pihak' => 'waris', 'nama' => 'Waris LA', 'hubungan' => 'Isteri']]);
        $this->assertContains($case->butiran_saluran['jenis_perintah'], ['letter_of_administration', 'probet']);
    }

    public function test_SRS_PER_11_pusaka_jkptgs_small_estate_certificate(): void
    {
        $case = $this->endToEnd('pusaka_jkptgs');
        $this->assertNotEmpty($case->butiran_saluran['no_sijil_pusaka_kecil']);
    }

    public function test_SRS_PER_12_wakaf_ikrar_syariah_and_ptg_registration(): void
    {
        $case = $this->endToEnd('wakaf', [['peranan_pihak' => 'pewakaf', 'nama' => 'Pewakaf Sintetik']]);
        $this->assertSame('disahkan', $case->butiran_saluran['status_pengesahan_syariah']);
    }

    public function test_SRS_PER_12_wakaf_cannot_pass_syariah_stage_unverified(): void
    {
        $user = $this->officer();
        $case = $this->newCase($user, 'wakaf');
        $this->actingAs($user)->putJson("/api/v1/per/kes/{$case->id}", ['butiran' => $this->fieldsFor('wakaf', 'penerimaan_ikrar')])->assertOk();
        $this->advance($user, $case)->assertOk()->assertJsonPath('data.peringkat_semasa.kod', 'pengesahan_syariah');

        $this->actingAs($user)->putJson("/api/v1/per/kes/{$case->id}", ['butiran' => ['status_pengesahan_syariah' => 'belum_disahkan', 'pegawai_syariah_pengesah' => 'Ustaz Ujian']])->assertOk();
        $this->uploadRequired($user, $case, 'kelulusan');
        $this->advance($user, $case)->assertStatus(422)->assertJsonPath('errors.kekurangan.0', 'Status pengesahan syariah mesti "Disahkan"');
    }

    public function test_SRS_PER_13_faraid_heirs_verified_through_e_faraid(): void
    {
        $user = $this->officer([...self::PEGAWAI, 'per.sulit.lihat']);
        $case = $this->newCase($user, 'faraid', ['butiran' => ['no_kes_faraid' => 'FRD-2026-0042', 'tarikh_keputusan' => '2026-01-10']]);

        $response = $this->actingAs($user)->postJson("/api/v1/per/kes/{$case->id}/e-faraid")->assertOk();
        $this->assertSame('disahkan', $response->json('data.butiran.status_pengesahan_waris'));
        $this->assertStringContainsString('SINTETIK', $response->json('data.butiran.nama_si_mati'));
        $heirs = collect($response->json('data.pihak'))->where('sumber_pengesahan', 'e_faraid');
        $this->assertGreaterThanOrEqual(2, $heirs->count());
        $this->assertTrue($heirs->every(fn ($h) => $h['status_pengesahan'] === 'disahkan' && str_starts_with((string) $h['no_dokumen_pengenalan'], '******-**-')));

        // Re-verifying replaces, not duplicates, the e-Faraid heirs.
        $this->actingAs($user)->postJson("/api/v1/per/kes/{$case->id}/e-faraid")->assertOk();
        $this->assertSame($heirs->count(), $case->pihak()->where('sumber_pengesahan', 'e_faraid')->count());

        $this->actingAs($user)->putJson("/api/v1/per/kes/{$case->id}", ['butiran' => ['no_kes_faraid' => 'FRD-TIADA-1']])->assertOk();
        $this->actingAs($user)->postJson("/api/v1/per/kes/{$case->id}/e-faraid")->assertNotFound();
    }

    public function test_SRS_PER_13_faraid_flow_closes(): void
    {
        $this->endToEnd('faraid', [['peranan_pihak' => 'si_mati', 'nama' => 'Si Mati Faraid']]);
    }

    public function test_SRS_PER_14_pembelian_terus_spa_payment_and_ptg_confirmation(): void
    {
        $case = $this->endToEnd('pembelian_terus', [['peranan_pihak' => 'penjual', 'nama' => 'Penjual Sintetik Sdn Bhd', 'jenis_dokumen_pengenalan' => 'no_syarikat', 'no_dokumen_pengenalan' => '201901000123']]);
        $this->assertSame('didaftar', $case->butiran_saluran['status_pendaftaran_ptg']);
        $this->assertNotEmpty($case->butiran_saluran['harga_belian']);
    }

    public function test_SRS_PER_15_tanah_warta_gazette_type_purpose_number_and_date(): void
    {
        $case = $this->endToEnd('tanah_warta');
        foreach (['no_warta_kerajaan', 'tarikh_warta', 'jenis_pewartaan', 'tujuan_pewartaan'] as $f) {
            $this->assertNotEmpty($case->butiran_saluran[$f]);
        }
    }
}
