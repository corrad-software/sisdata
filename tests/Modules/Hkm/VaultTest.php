<?php

namespace Tests\Modules\Hkm;

use App\Models\User;
use App\Modules\Hkm\Models\DaftarPetiBesi;
use App\Modules\Hkm\Models\PengeluaranDokumen;
use App\Modules\Hkm\Reminders\DokumenLewatDipulangkan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/** SRS-HKM-03..07, 22 — vault register, inventory, document status, issue/return, overdue. */
class VaultTest extends HkmTestCase
{
    private function deposit(?User $custodian = null): DaftarPetiBesi
    {
        $custodian ??= $this->user(self::PENJAGA);
        $vault = $this->vault($custodian);
        $title = $this->title(['no_hak_milik' => 'GRN 777']);

        $id = $this->actingAs($custodian)->postJson("/api/v1/hkm/hak-milik/{$title->id}/daftar-peti-besi", [
            'peti_besi_id' => $vault->id, 'lokasi_rak' => 'R-03', 'no_fail' => 'F-0042', 'no_kotak_pengikat' => 'K-7', 'catatan_lokasi' => 'Laci atas',
        ])->assertCreated()->json('data.id');

        return DaftarPetiBesi::query()->findOrFail($id);
    }

    /** Acceptance criterion 2 (part 1). */
    public function test_SRS_HKM_03_title_shows_vault_file_rack_and_original_status(): void
    {
        $entry = $this->deposit();
        $viewer = $this->user(['hkm.hak_milik.lihat', 'hkm.peti_besi.lihat']);

        $doc = $this->actingAs($viewer)->getJson("/api/v1/hkm/hak-milik/{$entry->hak_milik_id}")->assertOk()->json('data.dokumen_asal.0');
        $this->assertSame('PB-01', $doc['kod_peti_besi']);
        $this->assertSame('Menara MAIS, Tingkat 3, Bilik Kebal A', $doc['lokasi_peti_besi']);
        $this->assertSame('R-03', $doc['lokasi_rak']);
        $this->assertSame('F-0042', $doc['no_fail']);
        $this->assertSame('K-7', $doc['no_kotak_pengikat']);
        $this->assertSame('dalam_simpanan', $doc['status_dokumen_asal']);

        // Without vault permission the title is visible but the location is not.
        $this->actingAs($this->user(['hkm.hak_milik.lihat']))->getJson("/api/v1/hkm/hak-milik/{$entry->hak_milik_id}")->assertJsonPath('data.dokumen_asal', null);
    }

    public function test_SRS_HKM_03_vault_master_is_managed_by_the_custodian_only(): void
    {
        $custodian = $this->user(self::PENJAGA);
        $this->actingAs($custodian)->postJson('/api/v1/hkm/peti-besi', ['kod_peti_besi' => 'PB-02', 'nama' => 'Peti Besi Daerah', 'lokasi_bangunan' => 'PTD Petaling', 'kapasiti' => 1])
            ->assertCreated()->assertJsonPath('data.kod_peti_besi', 'PB-02');
        $this->actingAs($this->user())->postJson('/api/v1/hkm/peti-besi', ['kod_peti_besi' => 'PB-03', 'nama' => 'X', 'lokasi_bangunan' => 'Y'])->assertForbidden();

        // Registrar (no vault permission) cannot deposit or issue.
        $title = $this->title();
        $this->actingAs($this->user())->postJson("/api/v1/hkm/hak-milik/{$title->id}/daftar-peti-besi", ['kod_peti_besi' => 'PB-02'])->assertForbidden();
    }

    /** Acceptance criterion 2 (part 2): the vault register prints as a correct inventory. */
    public function test_SRS_HKM_04_vault_inventory_prints_and_is_audited(): void
    {
        $entry = $this->deposit();
        $custodian = $this->user(self::PENJAGA);

        $list = $this->actingAs($custodian)->getJson('/api/v1/hkm/daftar-peti-besi?peti_besi_id='.$entry->peti_besi_id)->assertOk();
        $list->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.no_hak_milik', 'GRN 777')->assertJsonPath('data.0.lot_id', $entry->lot_id);

        $pdf = $this->actingAs($custodian)->get('/api/v1/hkm/eksport/inventori-peti-besi?format=pdf&peti_besi_id='.$entry->peti_besi_id);
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());

        $csv = (string) $this->actingAs($custodian)->get('/api/v1/hkm/eksport/inventori-peti-besi?format=csv')->assertOk()->getContent();
        $this->assertStringContainsString('GRN 777', $csv);
        $this->assertStringContainsString('R-03', $csv);
        $this->assertStringContainsString('F-0042', $csv);

        $this->assertAudited('cetak', ['modul' => 'hkm', 'nama_entiti' => 'daftar_peti_besi', 'id_pengguna' => $custodian->id]);
        $this->actingAs($this->user(['hkm.peti_besi.lihat']))->get('/api/v1/hkm/eksport/inventori-peti-besi')->assertForbidden();
    }

    public function test_SRS_HKM_05_original_document_status_values_and_missing_flag(): void
    {
        $entry = $this->deposit();
        $custodian = $this->user(self::PENJAGA);
        $viewer = $this->user(['hkm.hak_milik.lihat']);

        $flags = fn () => collect($this->actingAs($viewer)->getJson("/api/v1/hkm/hak-milik/{$entry->hak_milik_id}")->json('data.bendera'))->pluck('kod');
        $this->assertNotContains('dokumen_asal_tiada', $flags());

        foreach (['dipegang_pihak_ketiga', 'hilang', 'diganti', 'dalam_proses_pt'] as $status) {
            $this->actingAs($custodian)->putJson("/api/v1/hkm/daftar-peti-besi/{$entry->id}", ['status_dokumen_asal' => $status])->assertOk()->assertJsonPath('data.status_dokumen_asal', $status);
            $this->assertContains('dokumen_asal_tiada', $flags());
        }

        // Filtered list of titles whose original is not in custody.
        $this->actingAs($custodian)->getJson('/api/v1/hkm/daftar-peti-besi?tiada_dalam_simpanan=1')->assertJsonPath('meta.total', 1);
        $this->actingAs($viewer)->getJson('/api/v1/hkm/hak-milik?bendera=dokumen_asal_tiada')->assertJsonPath('meta.total', 1);

        // A title with no vault entry at all is flagged too.
        $bare = $this->title();
        $this->assertContains('dokumen_asal_tiada', collect($this->actingAs($viewer)->getJson("/api/v1/hkm/hak-milik/{$bare->id}")->json('data.bendera'))->pluck('kod'));
    }

    /** Acceptance criterion 3: one full issue and return cycle is recorded completely. */
    public function test_SRS_HKM_06_full_issue_and_return_cycle_is_recorded(): void
    {
        $custodian = $this->user(self::PENJAGA);
        $entry = $this->deposit($custodian);
        $receiver = $this->user(self::PENJAGA);

        $issueId = $this->actingAs($custodian)->postJson("/api/v1/hkm/daftar-peti-besi/{$entry->id}/keluar", [
            'penerima_nama' => 'Ahmad bin Ali', 'penerima_jawatan' => 'Pegawai Undang-Undang', 'tujuan_pengeluaran' => 'Serahan ke PTD untuk urusan kaveat',
            'tarikh_jangka_pulang' => now()->addDays(7)->toDateString(), 'status_semasa_dikeluarkan' => 'dalam_proses_pt',
        ])->assertCreated()->json('data.id');
        $this->assertSame('dalam_proses_pt', $entry->refresh()->status_dokumen_asal);

        // Cannot issue twice.
        $this->actingAs($custodian)->postJson("/api/v1/hkm/daftar-peti-besi/{$entry->id}/keluar", ['penerima_nama' => 'X', 'tujuan_pengeluaran' => 'Y', 'tarikh_jangka_pulang' => now()->addDay()->toDateString()])->assertUnprocessable();

        $this->actingAs($receiver)->postJson("/api/v1/hkm/pengeluaran/{$issueId}/pulang", ['keadaan_dokumen_pulang' => 'baik', 'catatan_pulang' => 'Lengkap'])->assertOk();

        $issue = PengeluaranDokumen::query()->findOrFail($issueId);
        $this->assertTrue($issue->tarikh_pengeluaran->isToday());
        $this->assertSame($custodian->id, $issue->id_pegawai_keluar);
        $this->assertSame('Ahmad bin Ali', $issue->penerima_nama);
        $this->assertSame('Serahan ke PTD untuk urusan kaveat', $issue->tujuan_pengeluaran);
        $this->assertSame(now()->addDays(7)->toDateString(), $issue->tarikh_jangka_pulang->toDateString());
        $this->assertNotNull($issue->tarikh_pulang_sebenar);
        $this->assertSame($receiver->id, $issue->id_pegawai_terima_pulang);
        $this->assertSame('baik', $issue->keadaan_dokumen_pulang);
        $this->assertSame('dalam_simpanan', $entry->refresh()->status_dokumen_asal);

        $history = $this->actingAs($custodian)->getJson("/api/v1/hkm/daftar-peti-besi/{$entry->id}")->json('data.sejarah_pengeluaran');
        $this->assertCount(1, $history);
        $this->assertSame('dipulangkan', $history[0]['status_pengeluaran']);

        // SRS-HKM-22: explicit audit actions for issue and return.
        $this->assertAudited('keluar_dokumen', ['modul' => 'hkm', 'id_rekod' => $issueId, 'id_pengguna' => $custodian->id]);
        $this->assertAudited('pulang_dokumen', ['modul' => 'hkm', 'id_rekod' => $issueId, 'id_pengguna' => $receiver->id]);
    }

    public function test_SRS_HKM_06_issue_needs_issue_permission(): void
    {
        $entry = $this->deposit();
        $this->actingAs($this->user(['hkm.peti_besi.lihat']))->postJson("/api/v1/hkm/daftar-peti-besi/{$entry->id}/keluar", [
            'penerima_nama' => 'X', 'tujuan_pengeluaran' => 'Y', 'tarikh_jangka_pulang' => now()->addDay()->toDateString(),
        ])->assertForbidden();
    }

    /** Acceptance criterion 3 (part 2): overdue documents appear in the action list, report and notification. */
    public function test_SRS_HKM_07_overdue_documents_are_listed_and_notified_to_custodian_and_head(): void
    {
        $custodian = $this->user(self::PENJAGA);
        $head = $this->user(['hkm.pemantauan.lihat']);
        $entry = $this->deposit($custodian);

        $issueId = $this->actingAs($custodian)->postJson("/api/v1/hkm/daftar-peti-besi/{$entry->id}/keluar", [
            'penerima_nama' => 'Siti', 'tujuan_pengeluaran' => 'Semakan', 'tarikh_jangka_pulang' => now()->addDays(2)->toDateString(),
        ])->json('data.id');

        Carbon::setTestNow(now()->addDays(5));
        try {
            $this->actingAs($head)->getJson('/api/v1/hkm/pengeluaran?lewat=1')->assertOk()
                ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $issueId)->assertJsonPath('data.0.lewat', true)->assertJsonPath('data.0.hari_lewat', 3);
            $this->actingAs($this->user(['hkm.hak_milik.lihat']))->getJson('/api/v1/hkm/pengeluaran?lewat=1')->assertForbidden();

            $report = $this->actingAs($this->user(['hkm.hak_milik.lihat', 'hkm.pemantauan.lihat']))->getJson('/api/v1/hkm/laporan/dokumen_belum_pulang')->assertOk();
            $this->assertCount(1, $report->json('data.baris'));

            $due = iterator_to_array(app(DokumenLewatDipulangkan::class)->due(0, CarbonImmutable::today()), false);
            $this->assertCount(1, $due);
            $recipients = collect($due[0]->recipients)->pluck('id')->sort()->values()->all();
            $this->assertContains($custodian->id, $recipients);
            $this->assertContains($head->id, $recipients);
            $this->assertSame(3, $due[0]->data['hari_lewat']);
        } finally {
            Carbon::setTestNow();
        }
    }
}
