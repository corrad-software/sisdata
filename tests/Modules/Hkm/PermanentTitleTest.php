<?php

namespace Tests\Modules\Hkm;

use App\Modules\Hkm\Models\DaftarPetiBesi;
use App\Modules\Hkm\Models\HakMilik;
use App\Modules\Hkm\Services\VaultService;

/** SRS-HKM-16 — permanent-title application from registration to re-registration. */
class PermanentTitleTest extends HkmTestCase
{
    private const PERMOHONAN = ['hkm.hak_milik.lihat', 'hkm.hak_milik.cipta', 'hkm.permohonan.lihat', 'hkm.permohonan.cipta', 'hkm.permohonan.kemas_kini'];

    /** Acceptance criterion 7. */
    public function test_SRS_HKM_16_application_is_tracked_to_receipt_and_links_old_and_new_title(): void
    {
        $officer = $this->user([...self::PERMOHONAN, ...self::PENJAGA]);
        $temp = $this->title(['no_hak_milik' => 'HS(D) 4455', 'jenis_hak_milik' => 'HSD', 'tarikh_pendaftaran' => '2015-02-01', 'tarikh_tamat_tempoh' => now()->addYear()->toDateString(), 'pemilik_berdaftar' => 'MAIS']);
        $vault = $this->vault($officer);
        app(VaultService::class)->deposit($temp, ['peti_besi_id' => $vault->id, 'no_fail' => 'F-1'], $officer);

        $id = $this->actingAs($officer)->postJson("/api/v1/hkm/hak-milik/{$temp->id}/permohonan-hmk", [
            'tarikh_permohonan' => now()->subDays(100)->toDateString(), 'pejabat_tanah' => 'Pejabat Tanah dan Galian Selangor',
        ])->assertCreated()->assertJsonPath('data.peringkat_semasa', 'pendaftaran')->json('data.id');

        // Can't receive before checklist + payment.
        $this->actingAs($officer)->postJson("/api/v1/hkm/permohonan-hmk/{$id}/terima", ['no_hak_milik' => 'GRN 88001', 'jenis_hak_milik' => 'GRN', 'tarikh_terima' => today()->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('senarai_semak_dokumen');

        $checklist = $this->actingAs($officer)->getJson("/api/v1/hkm/permohonan-hmk/{$id}")->json('data.senarai_semak_dokumen');
        $this->assertCount(5, $checklist);
        $this->actingAs($officer)->putJson("/api/v1/hkm/permohonan-hmk/{$id}/senarai-semak", ['senarai' => array_map(fn ($i) => ['kod' => $i['kod'], 'lengkap' => true], $checklist)])
            ->assertOk()->assertJsonPath('data.semak_lengkap', true);

        foreach (['semakan_dokumen', 'ukur_halus', 'kelulusan_pt'] as $i => $stage) {
            $this->actingAs($officer)->postJson("/api/v1/hkm/permohonan-hmk/{$id}/peringkat", ['peringkat' => $stage, 'status' => 'selesai', 'tarikh' => now()->subDays(80 - $i * 10)->toDateString()])->assertOk();
        }
        $this->actingAs($officer)->postJson("/api/v1/hkm/permohonan-hmk/{$id}/peringkat", ['peringkat' => 'semakan_dokumen', 'status' => 'selesai', 'tarikh' => today()->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('peringkat'); // no going back
        $this->actingAs($officer)->getJson("/api/v1/hkm/permohonan-hmk/{$id}")->assertJsonPath('data.status_kelulusan', 'lulus');

        $this->actingAs($officer)->postJson("/api/v1/hkm/permohonan-hmk/{$id}/bayaran", ['jumlah_bayaran' => 1520.50, 'tarikh_bayaran' => now()->subDays(20)->toDateString(), 'no_resit' => 'R-778899'])
            ->assertOk()->assertJsonPath('data.no_resit', 'R-778899');

        // A temporary type can't be the "permanent" title.
        $this->actingAs($officer)->postJson("/api/v1/hkm/permohonan-hmk/{$id}/terima", ['no_hak_milik' => 'HS(D) 9', 'jenis_hak_milik' => 'HSD', 'tarikh_terima' => today()->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('jenis_hak_milik');

        $done = $this->actingAs($officer)->postJson("/api/v1/hkm/permohonan-hmk/{$id}/terima", [
            'no_hak_milik' => 'GRN 88001', 'jenis_hak_milik' => 'GRN', 'tarikh_pendaftaran' => today()->toDateString(), 'tarikh_terima' => today()->toDateString(),
            'peti_besi' => ['peti_besi_id' => $vault->id, 'no_fail' => 'F-2', 'lokasi_rak' => 'R-1'],
        ])->assertOk()->json('data');

        $this->assertSame('selesai', $done['status_kelulusan']);
        $this->assertSame('GRN 88001', $done['no_hak_milik_baharu']);
        $this->assertSame(100, $done['tempoh_proses_hari']);
        $this->assertSame(['pendaftaran', 'semakan_dokumen', 'ukur_halus', 'kelulusan_pt', 'penerimaan'], collect($done['sejarah_peringkat'])->pluck('peringkat')->all());

        $new = HakMilik::query()->withoutGlobalScopes()->findOrFail($done['hak_milik_baharu_id']);
        $this->assertSame($temp->id, $new->id_hak_milik_digantikan);
        $this->assertSame($temp->lot_id, $new->lot_id);
        $this->assertSame('diganti', $temp->refresh()->status_semasa);
        $this->assertDatabaseHas('sejarah_status_tanah', ['hak_milik_id' => $temp->id, 'status_selepas' => 'diganti']);
        $this->assertSame('diganti', DaftarPetiBesi::query()->where('hak_milik_id', $temp->id)->value('status_dokumen_asal'));
        $this->assertSame('dalam_simpanan', DaftarPetiBesi::query()->where('hak_milik_id', $new->id)->value('status_dokumen_asal'));

        // The link stays visible from both sides.
        $this->actingAs($officer)->getJson("/api/v1/hkm/hak-milik/{$new->id}")->assertJsonPath('data.digantikan.no_hak_milik', 'HS(D) 4455');
        $this->actingAs($officer)->getJson("/api/v1/hkm/hak-milik/{$temp->id}")->assertJsonPath('data.pengganti.0.no_hak_milik', 'GRN 88001');
    }

    public function test_SRS_HKM_16_only_temporary_titles_qualify_and_permissions_apply(): void
    {
        $officer = $this->user(self::PERMOHONAN);
        $grant = $this->title(['jenis_hak_milik' => 'GRN']);
        $this->actingAs($officer)->postJson("/api/v1/hkm/hak-milik/{$grant->id}/permohonan-hmk", ['tarikh_permohonan' => today()->toDateString(), 'pejabat_tanah' => 'PTG'])
            ->assertUnprocessable()->assertJsonValidationErrors('hak_milik_sementara_id');

        $temp = $this->title(['jenis_hak_milik' => 'HSM', 'no_hak_milik' => 'HS(M) 1']);
        $this->actingAs($this->user(['hkm.hak_milik.lihat', 'hkm.permohonan.lihat']))->postJson("/api/v1/hkm/hak-milik/{$temp->id}/permohonan-hmk", ['tarikh_permohonan' => today()->toDateString(), 'pejabat_tanah' => 'PTG'])->assertForbidden();
        $this->actingAs($officer)->postJson("/api/v1/hkm/hak-milik/{$temp->id}/permohonan-hmk", ['tarikh_permohonan' => today()->toDateString(), 'pejabat_tanah' => 'PTG'])->assertCreated();
        $this->actingAs($officer)->postJson("/api/v1/hkm/hak-milik/{$temp->id}/permohonan-hmk", ['tarikh_permohonan' => today()->toDateString(), 'pejabat_tanah' => 'PTG'])->assertUnprocessable();

        $this->actingAs($officer)->getJson('/api/v1/hkm/permohonan-hmk')->assertJsonPath('meta.total', 1);
        $this->actingAs($this->user([...self::PERMOHONAN], ['1009']))->getJson('/api/v1/hkm/permohonan-hmk')->assertJsonPath('meta.total', 0);
    }
}
