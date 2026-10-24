<?php

namespace Tests\Modules\Hkm;

use App\Modules\Hkm\Contracts\TitleChangeProposals;
use App\Modules\Hkm\Models\CadanganPerubahan;
use App\Modules\Hkm\Models\PerubahanHakMilik;
use App\Modules\Hkm\Reminders\HakMilikSementaraTamat;
use App\Modules\Hkm\Reminders\LotTanpaHakMilikDisahkan;
use App\Modules\Hkm\Reminders\TolPerluDiperbaharui;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** SRS-HKM-17 reminders and SRS-HKM-18 external proposals as drafts. */
class ProposalAndReminderTest extends HkmTestCase
{
    private const INTEGRASI = ['hkm.cadangan.cipta'];

    private const PENYEMAK = ['hkm.hak_milik.lihat', 'hkm.hak_milik.kemas_kini', 'hkm.cadangan.lihat', 'hkm.cadangan.kemas_kini'];

    public function test_SRS_HKM_18_external_proposal_lands_as_draft_and_never_writes_the_master(): void
    {
        $title = $this->title(['no_hak_milik' => 'GRN 4040', 'pemilik_berdaftar' => 'Pemilik A']);
        $payload = ['sumber' => 'e_tanah', 'rujukan_luaran' => 'ETANAH-2026-0001', 'no_hak_milik' => 'grn 4040', 'kod_mukim' => '100803',
            'jenis_perubahan' => 'pemilik', 'data' => ['pemilik_baharu' => 'Majlis Agama Islam Selangor', 'tarikh_pertukaran' => '2026-09-30', 'sebab_pertukaran' => 'Pindah milik didaftarkan di PTD']];

        $id = $this->actingAs($this->user(self::INTEGRASI))->postJson('/api/v1/hkm/cadangan', $payload)->assertCreated()
            ->assertJsonPath('data.status', 'draf')->assertJsonPath('data.hak_milik_id', $title->id)->json('data.id');
        // Same external reference again is idempotent.
        $this->actingAs($this->user(self::INTEGRASI))->postJson('/api/v1/hkm/cadangan', $payload)->assertOk()->assertJsonPath('data.id', $id);

        $this->assertSame('Pemilik A', $title->refresh()->pemilik_berdaftar);
        $this->assertSame(0, PerubahanHakMilik::query()->count());
        $this->assertSame([], $this->workflow->started);

        $reviewer = $this->user(self::PENYEMAK);
        $this->actingAs($reviewer)->getJson('/api/v1/hkm/cadangan')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.rujukan_luaran', 'ETANAH-2026-0001');

        // Confirming turns it into a normal change request (two-stage approval) — still no master write.
        $changeId = $this->actingAs($reviewer)->postJson("/api/v1/hkm/cadangan/{$id}/sahkan", ['justifikasi' => 'Disemak dengan carian rasmi'])->assertOk()
            ->assertJsonPath('data.status', 'disahkan')->json('data.id_perubahan');
        $this->assertSame('Pemilik A', $title->refresh()->pemilik_berdaftar);
        $this->assertSame('hkm.perubahan_hak_milik', array_values($this->workflow->started)[0]['flow']);
        $this->assertSame($id, PerubahanHakMilik::query()->findOrFail($changeId)->cadangan_perubahan_id);

        $this->completeWorkflow($changeId, 'lulus');
        $this->assertSame('Majlis Agama Islam Selangor', $title->refresh()->pemilik_berdaftar);

        // Already reviewed.
        $this->actingAs($reviewer)->postJson("/api/v1/hkm/cadangan/{$id}/tolak", ['catatan' => 'Tidak sah lagi'])->assertUnprocessable();
    }

    public function test_SRS_HKM_18_reviewer_can_reject_and_integration_account_cannot_review(): void
    {
        $this->title(['no_hak_milik' => 'GRN 5']);
        $id = app(TitleChangeProposals::class)->propose(['sumber' => 'ptd', 'rujukan_luaran' => 'PTD-1', 'no_hak_milik' => 'GRN 5', 'jenis_perubahan' => 'status', 'data' => ['status_selepas' => 'dipajak', 'tarikh_berkuat_kuasa' => '2026-10-01']]);

        $this->actingAs($this->user(self::INTEGRASI))->postJson("/api/v1/hkm/cadangan/{$id}/sahkan", ['justifikasi' => 'x'])->assertForbidden();
        $this->actingAs($this->user(self::PENYEMAK))->postJson("/api/v1/hkm/cadangan/{$id}/tolak", ['catatan' => 'Rujukan PTD tidak sepadan'])->assertOk()->assertJsonPath('data.status', 'ditolak');
        $this->assertSame('ditolak', CadanganPerubahan::query()->findOrFail($id)->status);
        $this->assertSame(0, PerubahanHakMilik::query()->count());
    }

    public function test_SRS_HKM_18_unmatched_proposal_needs_the_officer_to_pick_the_title(): void
    {
        $title = $this->title(['no_hak_milik' => 'GRN 6']);
        $id = app(TitleChangeProposals::class)->propose(['sumber' => 'penyedia_berpusat', 'rujukan_luaran' => 'MBI-9', 'no_hak_milik' => 'GRN 0006', 'jenis_perubahan' => 'butiran', 'data' => ['syarat_nyata' => 'Baru']]);
        $reviewer = $this->user(self::PENYEMAK);

        $this->actingAs($reviewer)->postJson("/api/v1/hkm/cadangan/{$id}/sahkan", ['justifikasi' => 'Semak'])->assertUnprocessable()->assertJsonValidationErrors('hak_milik_id');
        $this->actingAs($reviewer)->postJson("/api/v1/hkm/cadangan/{$id}/sahkan", ['justifikasi' => 'Semak manual', 'hak_milik_id' => $title->id])->assertOk();
    }

    /** SRS-HKM-17: temporary titles at 180/90/30 days. */
    public function test_SRS_HKM_17_temporary_title_expiry_reminders_at_180_90_30_days(): void
    {
        $registrar = $this->user();
        $today = CarbonImmutable::today();
        $hsd = $this->title(['jenis_hak_milik' => 'HSD', 'no_hak_milik' => 'HS(D) 1', 'tarikh_tamat_tempoh' => $today->addDays(90)->toDateString()], null, $registrar);
        $this->title(['jenis_hak_milik' => 'GRN', 'no_hak_milik' => 'GRN X', 'tarikh_tamat_tempoh' => $today->addDays(90)->toDateString()]); // not temporary
        $source = app(HakMilikSementaraTamat::class);

        $this->assertSame([180, 90, 30], $source->defaultDays());
        $due = iterator_to_array($source->due(90, $today), false);
        $this->assertCount(1, $due);
        $this->assertSame('hak_milik:'.$hsd->id, $due[0]->key);
        $this->assertSame([$registrar->id], collect($due[0]->recipients)->pluck('id')->all());
        $this->assertSame('/admin/hkm/hak-milik/'.$hsd->id, $due[0]->link);
        $this->assertCount(0, iterator_to_array($source->due(30, $today), false));
    }

    public function test_SRS_HKM_17_tol_renewal_and_lot_without_verified_title(): void
    {
        $today = CarbonImmutable::today();
        $this->title(['jenis_hak_milik' => 'TOL', 'no_hak_milik' => 'TOL 1', 'tarikh_tamat_tempoh' => $today->addDays(30)->toDateString()]);
        $this->assertCount(1, iterator_to_array(app(TolPerluDiperbaharui::class)->due(30, $today), false));
        $this->assertCount(0, iterator_to_array(app(HakMilikSementaraTamat::class)->due(30, $today), false));

        // A lot that passed the 180-day target today without a verified title.
        $late = $this->lot(['no_lot' => 'Lot LEWAT', 'created_at' => $today->subDays(180)]);
        $verifiedLot = $this->lot(['created_at' => $today->subDays(180)]);
        $t = $this->title([], $verifiedLot);
        DB::table('hak_milik')->where('id', $t->id)->update(['disahkan' => true]);
        $this->lot(['created_at' => $today->subDays(10)]); // still within target

        $due = iterator_to_array(app(LotTanpaHakMilikDisahkan::class)->due(0, $today), false);
        $this->assertSame(['lot_tanpa_hak_milik:'.$late], collect($due)->pluck('key')->all());
        $this->assertSame('Lot LEWAT', $due[0]->data['no_lot']);

        $report = $this->actingAs($this->user())->getJson('/api/v1/hkm/laporan/lot_tanpa_hak_milik')->assertOk()->json('data.baris');
        $this->assertContains('Lot LEWAT', collect($report)->pluck(0)->all());
    }

    public function test_SRS_HKM_17_expiring_titles_report(): void
    {
        $this->title(['jenis_hak_milik' => 'HSD', 'no_hak_milik' => 'HS(D) 7', 'tarikh_tamat_tempoh' => now()->addDays(20)->toDateString()]);
        $this->title(['jenis_hak_milik' => 'PN', 'no_hak_milik' => 'PN 7', 'tarikh_tamat_tempoh' => '2099-01-01']);

        $rows = $this->actingAs($this->user())->getJson('/api/v1/hkm/laporan/hampir_tamat')->assertOk()->json('data.baris');
        $this->assertSame(['HS(D) 7'], collect($rows)->pluck(0)->all());
        $this->assertSame('30 hari', $rows[0][5]);
        $this->actingAs($this->user())->get('/api/v1/hkm/laporan/hampir_tamat?format=xlsx')->assertOk();
        $this->actingAs($this->user(['hkm.hak_milik.lihat']))->get('/api/v1/hkm/laporan/hampir_tamat?format=xlsx')->assertForbidden();
    }
}
