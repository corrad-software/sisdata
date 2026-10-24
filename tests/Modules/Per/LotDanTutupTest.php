<?php

namespace Tests\Modules\Per;

use App\Modules\Hkm\Contracts\TitleRegistry;
use App\Modules\Per\Models\Pemerolehan;
use App\Support\Fallbacks\FallbackTitleRegistry;

class LotDanTutupTest extends PerTestCase
{
    public function test_SRS_PER_20_registered_lot_number_is_linked_directly(): void
    {
        $user = $this->officer();
        $lotId = $this->lot('PT 777', '100803');
        $case = $this->newCase($user, 'wakaf', ['lot_id' => null, 'no_lot' => 'PT 777', 'kod_mukim' => '100803']);

        $this->assertSame($lotId, $case->lot_id);
        $this->assertNull($case->no_lot_dijangka);
        $this->assertSame('1008', $case->kod_daerah);
        $this->createCase($user, 'wakaf', ['lot_id' => '00000000-0000-4000-8000-000000000000'])->assertStatus(422)->assertJsonValidationErrors('lot_id');
    }

    public function test_SRS_PER_20_expected_lot_relinked_when_lot_is_registered(): void
    {
        $user = $this->officer();
        $case = $this->newCase($user, 'pembelian_terus', ['lot_id' => null, 'no_lot' => 'PT 4242', 'kod_mukim' => '100501']);
        $this->assertNull($case->lot_id);
        $this->assertSame('PT 4242', $case->no_lot_dijangka);
        $this->assertSame('1005', $case->kod_daerah);

        $show = $this->actingAs($user)->getJson("/api/v1/per/kes/{$case->id}")->assertOk();
        $this->assertFalse($show->json('data.lot.berdaftar'));
        $this->assertSame('PT 4242', $show->json('data.lot.no_lot'));

        $this->actingAs($user)->postJson("/api/v1/per/kes/{$case->id}/pautkan-lot")->assertOk()->assertJsonPath('dipautkan', false);

        $other = $this->newCase($user, 'wakaf', ['lot_id' => null, 'no_lot' => 'PT 4243', 'kod_mukim' => '100501']);
        $lotId = $this->lot('PT 4242', '100501');
        $this->artisan('per:pautkan-lot')->expectsOutputToContain('1 kes dipautkan')->assertSuccessful();

        $this->assertSame($lotId, $case->refresh()->lot_id);
        $this->assertNull($other->refresh()->lot_id);
        $this->assertAudited('kemas_kini', ['nama_entiti' => 'pemerolehan', 'id_rekod' => $case->id, 'sebab_perubahan' => __('per::per.sebab_pautan_lot')]);

        $otherLot = $this->lot('PT 4243', '100501');
        $this->actingAs($user)->postJson("/api/v1/per/kes/{$other->id}/pautkan-lot")->assertOk()->assertJsonPath('dipautkan', true)
            ->assertJsonPath('data.lot.id', $otherLot)->assertJsonPath('data.lot.berdaftar', true);
    }

    /** Kriteria penerimaan 5 + SRS-PER-21 summary. */
    public function test_SRS_PER_21_closure_creates_title_in_hkm_with_vault_and_duration_summary(): void
    {
        $user = $this->officer();
        $approver = $this->officer(['per.kes.lihat']);
        $closer = $this->officer(['per.kes.lihat', 'per.hak_milik.cipta']);
        $case = $this->newCase($user, 'tanah_warta', ['tarikh_mula_kes' => now()->subDays(40)->toDateString()]);
        $case = $this->driveToFinalStage($user, $approver, $approver, $case);

        $this->actingAs($user)->postJson("/api/v1/per/kes/{$case->id}/tutup", $this->closePayload())->assertForbidden();
        $this->actingAs($closer)->postJson("/api/v1/per/kes/{$case->id}/tutup", ['hak_milik' => ['no_hak_milik' => 'X']])->assertStatus(422);

        $response = $this->actingAs($closer)->postJson("/api/v1/per/kes/{$case->id}/tutup", $this->closePayload())->assertOk();
        $response->assertJsonPath('message', 'Kes ditutup dalam 40 hari (sasaran 90 hari). Hak milik didaftarkan.')
            ->assertJsonPath('data.ringkasan_tempoh.tempoh_keseluruhan_hari', 40)
            ->assertJsonPath('data.ringkasan_tempoh.tempoh_sasaran_hari', 90)
            ->assertJsonPath('data.hak_milik.peti_besi.kod_peti_besi', 'PB-02')
            ->assertJsonPath('data.hak_milik.peti_besi.no_fail', 'F-2201')
            ->assertJsonPath('data.hak_milik.peti_besi.lokasi_rak', 'R4-1')
            ->assertJsonPath('data.hak_milik.peti_besi.jenis_dokumen_asal', 'geran_asal');

        $title = $this->titles->titles[0];
        $this->assertSame('GRN 55123', $title['data']['no_hak_milik']);
        $this->assertSame('Majlis Agama Islam Selangor', $title['data']['pemilik_berdaftar']);
        $this->assertSame($closer->id, $title['actor']);
        $this->assertSame($title['id'], $case->refresh()->id_hak_milik_dijana);
        $this->assertTrue(collect($response->json('data.senarai_semak'))->firstWhere('dijana_sistem', true) === null
            || collect($response->json('data.senarai_semak'))->where('dijana_sistem', true)->every(fn ($i) => $i['dipenuhi']));
        $this->assertAudited('tutup', ['modul' => 'per', 'id_rekod' => $case->id, 'id_pengguna' => $closer->id]);

        $this->actingAs($closer)->postJson("/api/v1/per/kes/{$case->id}/tutup", $this->closePayload())->assertStatus(409);
        $this->actingAs($user)->putJson("/api/v1/per/kes/{$case->id}", ['tajuk' => 'Ubah'])->assertStatus(409);
    }

    public function test_SRS_PER_21_cannot_close_before_final_stage_or_with_unregistered_lot(): void
    {
        $user = $this->officer([...self::PEGAWAI, 'per.hak_milik.cipta']);
        $approver = $this->officer(['per.kes.lihat']);
        $case = $this->newCase($user, 'tanah_warta', ['lot_id' => null, 'no_lot' => 'PT 1', 'kod_mukim' => '100803']);
        $this->actingAs($user)->postJson("/api/v1/per/kes/{$case->id}/tutup", $this->closePayload())->assertStatus(409);

        $case = $this->driveToFinalStage($user, $approver, $approver, $case);
        $this->actingAs($user)->postJson("/api/v1/per/kes/{$case->id}/tutup", $this->closePayload())->assertStatus(422)
            ->assertJsonPath('message', __('per::per.lot_belum_berdaftar', ['no_lot' => 'PT 1']));
        $this->assertSame([], $this->titles->titles);

        $this->lot('PT 1', '100803');
        $this->actingAs($user)->postJson("/api/v1/per/kes/{$case->id}/pautkan-lot")->assertOk();
        $this->actingAs($user)->postJson("/api/v1/per/kes/{$case->id}/tutup", $this->closePayload())->assertOk();
    }

    public function test_SRS_PER_21_without_hkm_the_case_stays_open(): void
    {
        $this->app->instance(TitleRegistry::class, new FallbackTitleRegistry);
        $user = $this->officer([...self::PEGAWAI, 'per.hak_milik.cipta']);
        $approver = $this->officer(['per.kes.lihat']);
        $case = $this->driveToFinalStage($user, $approver, $approver, $this->newCase($user, 'tanah_warta'));

        $this->actingAs($user)->postJson("/api/v1/per/kes/{$case->id}/tutup", $this->closePayload())->assertStatus(503)
            ->assertJsonPath('message', __('per::per.hkm_tiada'));
        $case->refresh();
        $this->assertSame(Pemerolehan::DILULUSKAN, $case->status_pemerolehan);
        $this->assertSame('simpanan_dokumen', $case->peringkat_semasa);
        $this->assertNull($case->id_hak_milik_dijana);
    }

    public function test_SRS_PER_24_acquisition_values_recorded(): void
    {
        $user = $this->officer();
        $case = $this->newCase($user, 'pembelian_terus', [
            'nilai_pasaran' => 1500000, 'nilai_pemerolehan' => 1350000, 'sumber_dana' => 'Dana Baitulmal', 'rujukan_penilaian_jpph' => 'JPPH/SEL/2026/77',
            'butiran' => ['harga_belian' => 1350000, 'deposit_dibayar' => 135000],
        ]);
        $this->actingAs($user)->getJson("/api/v1/per/kes/{$case->id}")->assertOk()
            ->assertJsonPath('data.nilai_pasaran', '1500000.00')->assertJsonPath('data.nilai_pemerolehan', '1350000.00')
            ->assertJsonPath('data.sumber_dana', 'Dana Baitulmal')->assertJsonPath('data.rujukan_penilaian_jpph', 'JPPH/SEL/2026/77')
            ->assertJsonPath('data.butiran.harga_belian', '1350000');

        $this->actingAs($user)->putJson("/api/v1/per/kes/{$case->id}", ['nilai_pemerolehan' => 1300000, 'sebab' => 'Rundingan semula harga'])->assertOk();
        $this->assertAudited('kemas_kini', ['nama_entiti' => 'pemerolehan', 'id_rekod' => $case->id, 'sebab_perubahan' => 'Rundingan semula harga'])
            ->assertFieldChanged('nilai_pemerolehan', '1350000.00', '1300000');
    }
}
