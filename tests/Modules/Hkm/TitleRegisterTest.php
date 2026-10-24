<?php

namespace Tests\Modules\Hkm;

use App\Modules\Hkm\Events\TitleRegistered;
use App\Modules\Hkm\Models\HakMilik;
use Illuminate\Support\Facades\Event;

/** SRS-HKM-01/02/21/22 — title registration, register fields, scope, audit. */
class TitleRegisterTest extends HkmTestCase
{
    /** @return array<string, mixed> */
    private function payload(string $lotId, array $extra = []): array
    {
        return $extra + [
            'lot_id' => $lotId,
            'no_hak_milik' => 'GRN 51234',
            'jenis_hak_milik' => 'GRN',
            'kategori_tanah' => 'BANGUNAN',
            'syarat_nyata' => 'Tanah ini hendaklah digunakan untuk bangunan kediaman sahaja.',
            'keluasan' => 2023.5,
            'unit_keluasan' => 'm2',
            'pemilik_berdaftar' => 'Majlis Agama Islam Selangor',
            'tarikh_pendaftaran' => '2010-03-15',
            'pejabat_pendaftaran' => 'Pejabat Pendaftaran Hakmilik Selangor',
            'no_perserahan' => '12345/2010',
            'tarikh_perserahan' => '2010-03-01',
        ];
    }

    /** Acceptance criterion 1: every A-5.01 field is recorded and shown. */
    public function test_SRS_HKM_01_registered_title_shows_all_a501_fields(): void
    {
        Event::fake([TitleRegistered::class]);
        $user = $this->user();
        $lotId = $this->lot(['no_lot' => 'Lot 4321']);

        $id = $this->actingAs($user)->postJson('/api/v1/hkm/hak-milik', $this->payload($lotId))->assertCreated()->json('data.id');

        $data = $this->actingAs($user)->getJson("/api/v1/hkm/hak-milik/{$id}")->assertOk()->json('data');
        $this->assertSame('GRN 51234', $data['no_hak_milik']);
        $this->assertSame('Lot 4321', $data['no_lot']);
        $this->assertSame('100803', $data['kod_mukim']);
        $this->assertSame('Damansara', $data['mukim']);
        $this->assertSame('1008', $data['kod_daerah']);
        $this->assertSame('Petaling', $data['daerah']);
        $this->assertSame('BANGUNAN', $data['kategori_tanah']);
        $this->assertStringContainsString('bangunan kediaman', $data['syarat_nyata']);
        $this->assertEquals(2023.5, (float) $data['keluasan']);
        $this->assertSame('Majlis Agama Islam Selangor', $data['pemilik_berdaftar']);
        $this->assertSame('aktif', $data['status_semasa']);
        $this->assertSame('sulit', $data['klasifikasi']);
        Event::assertDispatched(TitleRegistered::class, fn ($e) => $e->titleId === $id && $e->lotId === $lotId);
    }

    public function test_SRS_HKM_01_title_number_is_unique_per_mukim(): void
    {
        $user = $this->user();
        $this->actingAs($user)->postJson('/api/v1/hkm/hak-milik', $this->payload($this->lot()))->assertCreated();

        // Same number, same mukim (different lot, different spacing/case) is refused…
        $this->actingAs($user)->postJson('/api/v1/hkm/hak-milik', $this->payload($this->lot(), ['no_hak_milik' => 'grn 51234']))
            ->assertUnprocessable()->assertJsonValidationErrors('no_hak_milik');
        // …but the same number in another mukim is fine.
        $this->actingAs($user)->postJson('/api/v1/hkm/hak-milik', $this->payload($this->lot(['kod_mukim' => '100901'])))->assertCreated();
    }

    public function test_SRS_HKM_01_registration_writes_first_history_rows_and_version(): void
    {
        $title = $this->title(['pemilik_berdaftar' => 'MAIS']);

        $this->assertDatabaseHas('sejarah_status_tanah', ['hak_milik_id' => $title->id, 'status_sebelum' => null, 'status_selepas' => 'aktif']);
        $this->assertDatabaseHas('kronologi_pemilikan', ['hak_milik_id' => $title->id, 'pemilik_selepas' => 'MAIS', 'susunan_kronologi' => 1]);
        $this->assertDatabaseHas('hak_milik_versi', ['hak_milik_id' => $title->id, 'versi' => 1, 'jenis_perubahan' => 'pendaftaran']);
    }

    public function test_SRS_HKM_02_type_comes_from_ref_jenis_hak_milik(): void
    {
        $user = $this->user();
        $this->actingAs($user)->postJson('/api/v1/hkm/hak-milik', $this->payload($this->lot(), ['jenis_hak_milik' => 'XYZ']))
            ->assertUnprocessable()->assertJsonValidationErrors('jenis_hak_milik');

        foreach (['PN', 'HSD', 'RIZAB'] as $i => $type) {
            $this->actingAs($user)->postJson('/api/v1/hkm/hak-milik', $this->payload($this->lot(), ['jenis_hak_milik' => $type, 'no_hak_milik' => "X {$i}", 'tarikh_tamat_tempoh' => $type === 'PN' ? '2109-03-15' : null]))
                ->assertCreated()->assertJsonPath('data.jenis_hak_milik', $type);
        }
    }

    public function test_SRS_HKM_02_tol_needs_expiry_and_records_dates_and_office(): void
    {
        $user = $this->user();
        $this->actingAs($user)->postJson('/api/v1/hkm/hak-milik', $this->payload($this->lot(), ['jenis_hak_milik' => 'TOL', 'no_hak_milik' => 'TOL 1/2024']))
            ->assertUnprocessable()->assertJsonValidationErrors('tarikh_tamat_tempoh');

        $this->actingAs($user)->postJson('/api/v1/hkm/hak-milik', $this->payload($this->lot(), ['jenis_hak_milik' => 'TOL', 'no_hak_milik' => 'TOL 1/2024', 'tarikh_tamat_tempoh' => '2027-12-31']))
            ->assertCreated()
            ->assertJsonPath('data.tarikh_pendaftaran', '2010-03-15')
            ->assertJsonPath('data.tarikh_tamat_tempoh', '2027-12-31')
            ->assertJsonPath('data.pejabat_pendaftaran', 'Pejabat Pendaftaran Hakmilik Selangor')
            ->assertJsonPath('data.sementara', true);
    }

    public function test_SRS_HKM_21_presentation_number_and_land_office_transactions_are_tracked(): void
    {
        $user = $this->user();
        $title = $this->title();

        $id = $this->actingAs($user)->postJson("/api/v1/hkm/hak-milik/{$title->id}/urusan", [
            'jenis_urusan' => 'kaveat', 'pejabat_pendaftaran' => 'PTD Petaling', 'no_perserahan' => '998/2026', 'tarikh_perserahan' => now()->subDays(10)->toDateString(),
        ])->assertCreated()->assertJsonPath('data.status_urusan', 'diserah')->json('data.id');

        $this->actingAs($user)->putJson("/api/v1/hkm/urusan/{$id}", ['status_urusan' => 'didaftarkan'])
            ->assertOk()->assertJsonPath('data.status_urusan', 'didaftarkan')->assertJsonPath('data.tarikh_selesai', today()->toDateString());

        $this->actingAs($user)->getJson("/api/v1/hkm/hak-milik/{$title->id}")->assertJsonPath('data.urusan.0.no_perserahan', '998/2026');
    }

    public function test_SRS_HKM_01_user_without_permission_is_refused(): void
    {
        $title = $this->title();
        $nobody = $this->user(['dms.dokumen.lihat']);

        $this->actingAs($nobody)->getJson('/api/v1/hkm/hak-milik')->assertForbidden();
        $this->actingAs($nobody)->getJson("/api/v1/hkm/hak-milik/{$title->id}")->assertForbidden();
        $this->actingAs($nobody)->postJson('/api/v1/hkm/hak-milik', $this->payload($this->lot()))->assertForbidden();
        $this->actingAs($this->user(['hkm.hak_milik.lihat']))->postJson('/api/v1/hkm/hak-milik', $this->payload($this->lot()))->assertForbidden();
    }

    public function test_SRS_HKM_01_out_of_scope_district_is_invisible(): void
    {
        $petaling = $this->title();
        $sepang = $this->title(['kod_mukim' => '100901']);
        $officer = $this->user(self::PENDAFTAR, ['1009']);

        $this->actingAs($officer)->getJson("/api/v1/hkm/hak-milik/{$petaling->id}")->assertNotFound();
        $this->actingAs($officer)->getJson("/api/v1/hkm/hak-milik/{$sepang->id}")->assertOk();
        $ids = collect($this->actingAs($officer)->getJson('/api/v1/hkm/hak-milik')->json('data'))->pluck('id');
        $this->assertSame([$sepang->id], $ids->all());

        // Cannot register on a lot outside the scope either.
        $this->actingAs($officer)->postJson('/api/v1/hkm/hak-milik', $this->payload($this->lot()))->assertUnprocessable()->assertJsonValidationErrors('lot_id');
    }

    public function test_SRS_HKM_22_viewing_and_creating_a_title_is_audited(): void
    {
        $user = $this->user();
        $id = $this->actingAs($user)->postJson('/api/v1/hkm/hak-milik', $this->payload($this->lot()))->json('data.id');
        $this->actingAs($user)->getJson("/api/v1/hkm/hak-milik/{$id}")->assertOk();

        $this->assertAudited('cipta', ['nama_entiti' => 'hak_milik', 'id_rekod' => $id, 'id_pengguna' => $user->id]);
        $this->assertAudited('lihat', ['modul' => 'hkm', 'nama_entiti' => 'hak_milik', 'id_rekod' => $id, 'id_pengguna' => $user->id]);
    }

    public function test_SRS_HKM_17_verification_needs_another_officer(): void
    {
        $registrar = $this->user([...self::PENDAFTAR, 'hkm.pengesahan.lulus']);
        $id = $this->actingAs($registrar)->postJson('/api/v1/hkm/hak-milik', $this->payload($this->lot()))->json('data.id');

        $this->actingAs($registrar)->postJson("/api/v1/hkm/hak-milik/{$id}/sahkan")->assertUnprocessable();
        $this->actingAs($this->user(['hkm.hak_milik.lihat', 'hkm.pengesahan.lulus']))->postJson("/api/v1/hkm/hak-milik/{$id}/sahkan")
            ->assertOk()->assertJsonPath('data.disahkan', true)->assertJsonPath('data.versi_rekod', 2);
        $this->assertTrue(HakMilik::query()->withoutGlobalScopes()->findOrFail($id)->disahkan);
    }
}
