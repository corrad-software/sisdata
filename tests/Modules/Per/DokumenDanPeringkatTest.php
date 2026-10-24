<?php

namespace Tests\Modules\Per;

use App\Modules\Per\Models\Pemerolehan;
use Illuminate\Support\Carbon;

class DokumenDanPeringkatTest extends PerTestCase
{
    /** Kriteria penerimaan 4. */
    public function test_SRS_PER_16_incomplete_mandatory_documents_block_approval_and_list_is_exact(): void
    {
        $user = $this->officer();
        $case = $this->newCase($user, 'tanah_warta');
        foreach (['rekod_warta', 'semakan'] as $stage) {
            $this->actingAs($user)->putJson("/api/v1/per/kes/{$case->id}", ['butiran' => $this->fieldsFor('tanah_warta', $stage)])->assertOk();
        }
        $this->advance($user, $case)->assertOk();

        $items = $case->saluran->senaraiSemak()->get()->keyBy('jenis_dokumen');
        $this->upload($user, $case, $items['petikan_warta']->id)->assertCreated();
        $this->upload($user, $case, $items['carian_rasmi']->id)->assertCreated();

        $response = $this->advance($user, $case)->assertStatus(422);
        $this->assertSame(__('per::per.dokumen_kurang_kelulusan'), $response->json('message'));
        $this->assertSame(['Surat kelulusan kerajaan negeri', 'Pelan kawasan diwartakan'], $response->json('errors.kekurangan'));
        $this->assertSame([], $this->workflow->started, 'Tiada aliran kelulusan dimulakan.');

        $detail = $this->actingAs($user)->getJson("/api/v1/per/kes/{$case->id}")->assertOk();
        $this->assertSame('tidak_lengkap', $detail->json('data.status_kelengkapan_dokumen'));
        $this->assertSame(['Surat kelulusan kerajaan negeri', 'Pelan kawasan diwartakan', 'Hak milik yang dikeluarkan'], $detail->json('data.kekurangan_dokumen'));
        $this->assertSame(['Surat kelulusan kerajaan negeri', 'Pelan kawasan diwartakan'], $detail->json('data.sekatan'));
        $this->assertFalse(collect($detail->json('data.senarai_semak'))->firstWhere('jenis_dokumen', 'pelan_kawasan_diwarta')['dipenuhi']);

        $this->uploadRequired($user, $case, 'kelulusan');
        $this->advance($user, $case)->assertOk()->assertJsonPath('data.status', Pemerolehan::MENUNGGU_KELULUSAN);
        $this->assertCount(1, $this->workflow->started);
    }

    public function test_SRS_PER_16_documents_go_through_dms_as_sulit_with_owner_pemerolehan(): void
    {
        $user = $this->officer();
        $case = $this->newCase($user, 'wakaf');
        $item = $case->saluran->senaraiSemak()->where('jenis_dokumen', 'borang_ikrar_wakaf')->firstOrFail();

        $this->upload($user, $case, $item->id)->assertCreated()->assertJsonPath('data.nama_dokumen', 'Borang ikrar wakaf (akad)');
        $stored = array_values($this->dms->docs)[0];
        $this->assertSame('pemerolehan', $stored['owner_type']);
        $this->assertSame($case->id, $stored['owner_id']);
        $this->assertSame('sulit', $stored['classification']);
        $this->assertSame('surat_rasmi', $stored['category']);
        $this->assertSame('per', $stored['metadata']['modul_sumber']);
        $this->assertSame('/admin/per/kes/'.$case->id, $stored['metadata']['pautan_induk']);

        // A document already attached in DMS (via <DocumentAttachments/>) can tick another item.
        $other = $case->saluran->senaraiSemak()->where('jenis_dokumen', 'carian_rasmi')->firstOrFail();
        $dmsId = array_key_first($this->dms->docs);
        $this->actingAs($user)->postJson("/api/v1/per/kes/{$case->id}/dokumen", ['id_semak' => $other->id, 'rujukan_dms' => $dmsId])->assertCreated();
        $this->actingAs($user)->postJson("/api/v1/per/kes/{$case->id}/dokumen", ['id_semak' => $other->id, 'rujukan_dms' => '7d1f6a8e-0000-4000-8000-000000000000'])->assertStatus(422);

        $noDocs = $this->officer(['per.kes.lihat', 'per.kes.kemas_kini']);
        $this->upload($noDocs, $case, $item->id)->assertForbidden();
    }

    public function test_SRS_PER_22_physical_original_location_and_issue_return_history(): void
    {
        $user = $this->officer();
        $case = $this->newCase($user, 'serah_milik');
        $item = $case->saluran->senaraiSemak()->where('jenis_dokumen', 'hak_milik_asal')->firstOrFail();
        $docId = $this->upload($user, $case, $item->id)->assertCreated()
            ->assertJsonPath('data.kod_peti_besi', 'PB-01')->assertJsonPath('data.lokasi_rak', 'R2-3')->assertJsonPath('data.status_dokumen_asal', 'asal_dalam_simpanan')
            ->json('data.id');

        $this->actingAs($user)->putJson("/api/v1/per/kes/{$case->id}/dokumen/{$docId}", ['lokasi_rak' => 'R9-9', 'no_fail' => 'F-900'])->assertOk()->assertJsonPath('data.lokasi_rak', 'R9-9');
        $this->actingAs($user)->postJson("/api/v1/per/kes/{$case->id}/dokumen/{$docId}/pergerakan", ['jenis' => 'pulang'])->assertStatus(409);
        $this->actingAs($user)->postJson("/api/v1/per/kes/{$case->id}/dokumen/{$docId}/pergerakan", ['jenis' => 'keluar', 'tujuan' => 'Perserahan di PTG'])->assertOk()
            ->assertJsonPath('data.status_dokumen_asal', 'dikeluarkan');
        $this->actingAs($user)->postJson("/api/v1/per/kes/{$case->id}/dokumen/{$docId}/pergerakan", ['jenis' => 'pulang'])->assertOk()
            ->assertJsonPath('data.status_dokumen_asal', 'asal_dalam_simpanan')
            ->assertJsonPath('data.pergerakan.0.jenis', 'keluar')->assertJsonPath('data.pergerakan.0.tujuan', 'Perserahan di PTG')
            ->assertJsonPath('data.pergerakan.1.jenis', 'pulang');
        $this->assertAudited('kemas_kini', ['nama_entiti' => 'dokumen_pemerolehan', 'id_rekod' => $docId])->assertFieldChanged('status_dokumen_asal', 'dikeluarkan', 'asal_dalam_simpanan');
    }

    public function test_SRS_PER_18_each_stage_records_officer_entry_exit_duration_and_target(): void
    {
        $user = $this->officer();
        $next = $this->officer();
        Carbon::setTestNow('2026-06-01 09:00');
        $case = $this->newCase($user, 'tanah_warta', ['tarikh_mula_kes' => '2026-06-01']);
        $this->actingAs($user)->putJson("/api/v1/per/kes/{$case->id}", ['butiran' => $this->fieldsFor('tanah_warta', 'rekod_warta')])->assertOk();

        Carbon::setTestNow('2026-06-04 10:00');
        $this->actingAs($user)->postJson("/api/v1/per/kes/{$case->id}/maju", ['id_pegawai_seterusnya' => $next->id])->assertOk();
        Carbon::setTestNow();

        $stages = $case->peringkat()->get()->keyBy('kod_peringkat');
        $first = $stages['rekod_warta'];
        $this->assertSame($user->id, $first->id_pegawai_bertanggungjawab);
        $this->assertSame('2026-06-01', $first->tarikh_masuk->toDateString());
        $this->assertSame('2026-06-04', $first->tarikh_keluar->toDateString());
        $this->assertSame(3, $first->tempoh_hari);
        $this->assertSame(14, $first->tempoh_sasaran_hari);
        $this->assertSame('selesai', $first->status_peringkat);
        $this->assertSame($next->id, $stages['semakan']->id_pegawai_bertanggungjawab);
        $this->assertSame('semasa', $stages['semakan']->status_peringkat);
        $this->assertSame('semakan', $case->refresh()->peringkat_semasa);

        $other = $this->officer();
        $this->actingAs($user)->putJson("/api/v1/per/kes/{$case->id}/pegawai", ['id_pegawai' => $other->id])->assertOk();
        $this->assertSame($other->id, $stages['semakan']->refresh()->id_pegawai_bertanggungjawab);
    }

    public function test_SRS_PER_19_timeline_shows_stages_officers_durations_chronologically(): void
    {
        $user = $this->officer();
        $approver = $this->officer(['per.kes.lihat']);
        $case = $this->newCase($user, 'tanah_warta');
        $this->driveToFinalStage($user, $approver, $approver, $case);

        $items = $this->actingAs($user)->getJson("/api/v1/per/kes/{$case->id}/garis-masa")->assertOk()->json('data');
        $dates = array_column($items, 'tarikh');
        $sorted = $dates;
        sort($sorted);
        $this->assertSame($sorted, $dates);
        $stages = array_values(array_filter($items, fn ($i) => $i['jenis'] === 'peringkat'));
        $this->assertSame(['rekod_warta', 'semakan', 'kelulusan', 'pendaftaran_hak_milik', 'simpanan_dokumen'], array_column($stages, 'kod'));
        $this->assertSame($user->name, $stages[0]['pegawai']);
        $this->assertArrayHasKey('tempoh_hari', $stages[0]);
        $this->assertSame(14, $stages[0]['tempoh_sasaran_hari']);
        $this->assertContains('lulus', array_column(array_filter($items, fn ($i) => $i['jenis'] === 'kelulusan'), 'tajuk'));
    }

    /** Kriteria penerimaan 6 (second half). */
    public function test_SRS_PER_26_stalled_case_escalated_automatically_to_officer_and_head(): void
    {
        $officer = $this->officer();
        $this->directory->head = $head = $this->officer(['per.kes.lihat']);
        $case = $this->newCase($officer, 'tanah_warta');
        $fresh = $this->newCase($officer, 'wakaf');

        $case->peringkat()->where('kod_peringkat', 'rekod_warta')->update(['tarikh_masuk' => now()->subDays(20)]);

        $this->artisan('per:eskalasi')->assertSuccessful();

        $stage = $case->peringkat()->where('kod_peringkat', 'rekod_warta')->firstOrFail();
        $this->assertSame('dieskalasi', $stage->status_eskalasi);
        $this->assertNotNull($stage->tarikh_eskalasi);
        $this->assertSame('tiada', $fresh->peringkat()->where('status_peringkat', 'semasa')->value('status_eskalasi'));

        $sent = $this->notifier->ofType('per.kes_lewat');
        $this->assertCount(1, $sent);
        $this->assertEqualsCanonicalizing([$officer->id, $head->id], $sent[0]['to']);
        $this->assertSame(6, $sent[0]['data']['hari_lewat']);
        $this->assertSame($case->no_kes, $sent[0]['data']['no_kes']);
        $this->assertAudited('eskalasi', ['modul' => 'per', 'id_rekod' => $case->id]);

        // Not escalated twice.
        $this->artisan('per:eskalasi')->assertSuccessful();
        $this->assertCount(1, $this->notifier->ofType('per.kes_lewat'));

        $late = $this->actingAs($officer)->getJson('/api/v1/per/kes?lewat=1')->assertOk();
        $this->assertSame([$case->id], array_column($late->json('data'), 'id'));
        $this->assertTrue($late->json('data.0.peringkat_semasa.dieskalasi'));
    }
}
