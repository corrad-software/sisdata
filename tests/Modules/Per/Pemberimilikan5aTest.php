<?php

namespace Tests\Modules\Per;

use App\Modules\Per\Events\AcquisitionCompleted;
use App\Modules\Per\Integration\Notis5aReminder;
use App\Modules\Per\Models\Pemerolehan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

class Pemberimilikan5aTest extends PerTestCase
{
    private function fiveA(): array
    {
        $user = $this->officer();
        $approver = $this->officer(['per.kes.lihat']);
        $finance = $this->officer(['per.kes.lihat', 'per.bayaran.lulus']);
        $case = $this->newCase($user, 'pemberimilikan_5a', ['nilai_pemerolehan' => 250000]);

        return [$user, $approver, $finance, $case];
    }

    /** Kriteria penerimaan 2: e-Tanah → MMKN → notis 5A → bayaran → hak milik → pendaftaran → peti besi. */
    public function test_SRS_PER_02_five_a_case_tracked_from_etanah_to_vault(): void
    {
        Event::fake([AcquisitionCompleted::class]);
        [$user, $approver, $finance, $case] = $this->fiveA();
        $this->assertSame('permohonan_etanah', $case->peringkat_semasa);

        // Permohonan e-Tanah: fields required to leave the stage.
        $this->advance($user, $case)->assertStatus(422)->assertJsonPath('errors.kekurangan.0', 'No. permohonan e-Tanah');
        $this->actingAs($user)->putJson("/api/v1/per/kes/{$case->id}", ['butiran' => $this->fieldsFor('pemberimilikan_5a', 'permohonan_etanah')])->assertOk();
        $this->advance($user, $case)->assertOk()->assertJsonPath('data.peringkat_semasa.kod', 'keputusan_mmkn');

        // MMKN decision recorded (SRS-PER-03).
        $this->actingAs($user)->putJson("/api/v1/per/kes/{$case->id}", ['butiran' => [
            'no_mesyuarat_mmkn' => 'MMKN 12/2026', 'tarikh_mmkn' => '2026-03-04', 'no_kertas_mmkn' => 'KMMKN/2026/88',
            'jenis_keputusan_mmkn' => 'lulus_bersyarat', 'syarat_mmkn' => 'Tapak masjid sahaja', 'keluasan_diluluskan' => 4046.86,
        ]])->assertOk()->assertJsonPath('data.butiran.jenis_keputusan_mmkn', 'lulus_bersyarat');
        $this->advance($user, $case)->assertOk()->assertJsonPath('data.peringkat_semasa.kod', 'notis_5a');

        // Notis 5A (SRS-PER-04).
        $this->actingAs($user)->putJson("/api/v1/per/kes/{$case->id}", ['butiran' => [
            'no_notis_5a' => 'N5A/PTD/2026/101', 'tarikh_notis_5a' => '2026-04-01', 'tarikh_terima_notis' => '2026-04-03',
            'jumlah_premium' => 12000, 'cukai_tahun_pertama' => 350.50, 'bayaran_ukur' => 1800, 'bayaran_lain' => 49.50,
            'tarikh_akhir_bayaran' => now()->addDays(30)->toDateString(),
        ]])->assertOk();

        // Approval stage: documents first, then WFE.
        $this->advance($user, $case)->assertStatus(422);
        $this->uploadRequired($user, $case, 'kelulusan');
        $this->advance($user, $case)->assertOk()->assertJsonPath('data.status', Pemerolehan::MENUNGGU_KELULUSAN);
        $this->decide($case, $approver);
        $case->refresh();
        $this->assertSame(Pemerolehan::DILULUSKAN, $case->status_pemerolehan);
        $this->assertSame('bayaran_notis_5a', $case->peringkat_semasa);

        // Payment: due 14,200.00; partial + unconfirmed payments do not unblock.
        $this->uploadRequired($user, $case, 'penerimaan_hak_milik');
        $show = $this->actingAs($user)->getJson("/api/v1/per/kes/{$case->id}")->assertOk();
        $this->assertSame('14200.00', $show->json('data.bayaran_5a.jumlah_perlu_dibayar'));
        $this->actingAs($user)->postJson("/api/v1/per/kes/{$case->id}/bayaran", ['jumlah_dibayar' => 14200, 'tarikh_bayaran' => now()->toDateString(), 'mod_bayaran' => 'eft', 'no_resit' => 'GRP-778'])->assertCreated()
            ->assertJsonPath('data.baki_tertunggak', '14200.00')->assertJsonPath('data.lengkap', false);
        $this->advance($user, $case)->assertStatus(422)->assertJsonPath('message', 'Bayaran notis 5A belum disahkan lengkap oleh Bahagian Kewangan. Baki tertunggak: RM 14200.00.');

        $payment = $case->bayaran()->firstOrFail();
        $this->actingAs($finance)->postJson("/api/v1/per/kes/{$case->id}/bayaran/{$payment->id}/pengesahan", ['terima' => true])->assertOk()
            ->assertJsonPath('data.baki_tertunggak', '0.00')->assertJsonPath('data.lengkap', true);
        $this->assertAudited('sah', ['modul' => 'per', 'nama_entiti' => 'bayaran_notis_5a']);

        $this->advance($user, $case)->assertOk()->assertJsonPath('data.peringkat_semasa.kod', 'penerimaan_hak_milik');
        $case = $this->driveToFinalStage($user, $approver, $finance, $case);

        // Close: title + vault through MOD-HKM.
        $closer = $this->officer(['per.kes.lihat', 'per.hak_milik.cipta']);
        $this->actingAs($closer)->postJson("/api/v1/per/kes/{$case->id}/tutup", $this->closePayload())->assertOk()
            ->assertJsonPath('data.status', Pemerolehan::SELESAI);

        $this->assertCount(1, $this->titles->titles);
        $title = $this->titles->titles[0];
        $this->assertSame($case->lot_id, $title['lot_id']);
        $this->assertSame('PB-02', $title['vault']['kod_peti_besi']);
        $this->assertSame('R4-1', $title['vault']['lokasi_rak']);
        $this->assertSame('pemerolehan:'.$case->id, $title['source']);
        Event::assertDispatched(AcquisitionCompleted::class, fn ($e) => $e->caseId === $case->id && $e->channel === 'pemberimilikan_5a' && $e->titleId === $title['id']);
    }

    public function test_SRS_PER_03_mmkn_decision_other_than_approval_stops_the_case(): void
    {
        [$user, , , $case] = $this->fiveA();
        $this->actingAs($user)->putJson("/api/v1/per/kes/{$case->id}", ['butiran' => $this->fieldsFor('pemberimilikan_5a', 'permohonan_etanah')])->assertOk();
        $this->advance($user, $case)->assertOk();

        $this->actingAs($user)->putJson("/api/v1/per/kes/{$case->id}", ['butiran' => [
            'no_mesyuarat_mmkn' => 'MMKN 3/2026', 'tarikh_mmkn' => '2026-02-01', 'no_kertas_mmkn' => 'K/1', 'jenis_keputusan_mmkn' => 'tangguh', 'keluasan_diluluskan' => 0,
        ]])->assertOk();
        $this->advance($user, $case)->assertStatus(422)->assertJsonPath('message', __('per::per.mmkn_bukan_lulus'));

        $this->actingAs($user)->putJson("/api/v1/per/kes/{$case->id}", ['butiran' => ['jenis_keputusan_mmkn' => 'lulus']])->assertOk();
        $this->advance($user, $case)->assertOk()->assertJsonPath('data.peringkat_semasa.kod', 'notis_5a');

        $report = $this->actingAs($this->officer(['per.laporan.lihat']))->getJson('/api/v1/per/laporan/khas/keputusan_mmkn')->assertOk();
        $this->assertSame('lulus', $report->json('data.0.jenis_keputusan_mmkn'));
        $this->assertSame('K/1', $report->json('data.0.no_kertas_mmkn'));
    }

    public function test_SRS_PER_04_reminder_to_kewangan_and_officer_before_payment_deadline(): void
    {
        [$user, , , $case] = $this->fiveA();
        $deadline = now()->addDays(7)->toDateString();
        $this->actingAs($user)->putJson("/api/v1/per/kes/{$case->id}", ['butiran' => ['no_notis_5a' => 'N5A/1', 'jumlah_premium' => 5000, 'tarikh_akhir_bayaran' => $deadline]])->assertOk();

        $source = app(Notis5aReminder::class);
        $this->assertSame('per.notis_5a_tarikh_akhir', $source->type());
        $due = iterator_to_array($source->due(7, CarbonImmutable::now()), false);
        $this->assertCount(1, $due);
        $this->assertSame('N5A/1', $due[0]->data['no_notis_5a']);
        $this->assertSame('5000.00', $due[0]->data['baki_tertunggak']);
        $this->assertSame($user->id, collect($due[0]->recipients)->first()->id);
        $this->assertSame('/admin/per/kes/'.$case->id, $due[0]->link);
        $this->assertCount(0, iterator_to_array($source->due(6, CarbonImmutable::now()), false));

        $list = $this->actingAs($this->officer(['per.laporan.lihat']))->getJson('/api/v1/per/laporan/khas/notis_5a')->assertOk();
        $this->assertSame(7, $list->json('data.0.hari_lagi'));
    }

    public function test_SRS_PER_05_payment_confirmation_requires_kewangan_and_segregation(): void
    {
        [$user, , $finance, $case] = $this->fiveA();
        $this->actingAs($user)->putJson("/api/v1/per/kes/{$case->id}", ['butiran' => ['jumlah_premium' => 1000]])->assertOk();
        $this->actingAs($user)->postJson("/api/v1/per/kes/{$case->id}/bayaran", ['jumlah_dibayar' => 600, 'tarikh_bayaran' => now()->toDateString(), 'mod_bayaran' => 'cek', 'no_resit' => 'R1'])->assertCreated();
        $payment = $case->bayaran()->firstOrFail();

        // The case officer can neither confirm (no permission) nor — even with it — confirm own entry.
        $this->actingAs($user)->postJson("/api/v1/per/kes/{$case->id}/bayaran/{$payment->id}/pengesahan", ['terima' => true])->assertForbidden();
        $both = $this->officer([...self::PEGAWAI, 'per.bayaran.lulus']);
        $this->actingAs($both)->postJson("/api/v1/per/kes/{$case->id}/bayaran", ['jumlah_dibayar' => 100, 'tarikh_bayaran' => now()->toDateString(), 'mod_bayaran' => 'eft', 'no_resit' => 'R2'])->assertCreated();
        $own = $case->bayaran()->where('no_resit', 'R2')->firstOrFail();
        $this->actingAs($both)->postJson("/api/v1/per/kes/{$case->id}/bayaran/{$own->id}/pengesahan", ['terima' => true])->assertForbidden();

        $this->actingAs($finance)->postJson("/api/v1/per/kes/{$case->id}/bayaran/{$payment->id}/pengesahan", ['terima' => true])->assertOk()
            ->assertJsonPath('data.jumlah_disahkan', '600.00')->assertJsonPath('data.baki_tertunggak', '400.00')->assertJsonPath('data.lengkap', false);
        $this->actingAs($finance)->postJson("/api/v1/per/kes/{$case->id}/bayaran/{$payment->id}/pengesahan", ['terima' => true])->assertStatus(409);

        // Payments are only for 5A cases.
        $other = $this->newCase($user, 'wakaf');
        $this->actingAs($user)->postJson("/api/v1/per/kes/{$other->id}/bayaran", ['jumlah_dibayar' => 1, 'tarikh_bayaran' => now()->toDateString(), 'mod_bayaran' => 'eft', 'no_resit' => 'X'])->assertStatus(422);
    }
}
