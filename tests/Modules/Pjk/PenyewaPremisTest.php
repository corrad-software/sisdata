<?php

namespace Tests\Modules\Pjk;

use App\Modules\Pjk\Models\PermohonanSewaan;

class PenyewaPremisTest extends PjkTestCase
{
    public function test_SRS_PJK_01_tenant_register_masks_identity_and_full_view_needs_sulit_permission(): void
    {
        $user = $this->user();
        $res = $this->actingAs($user)->postJson('/api/v1/pjk/penyewa', [
            'jenis_entiti' => 'individu', 'nama' => 'Siti Sintetik', 'no_pengenalan' => '990000991111', 'alamat' => 'No 1 Jalan Ujian',
            'telefon' => '0123334444', 'emel' => 'siti@contoh.invalid',
        ])->assertCreated();
        $id = $res->json('data.id');
        $this->assertSame('******-**-1111', $res->json('data.no_pengenalan'));

        $list = $this->actingAs($user)->getJson('/api/v1/pjk/penyewa?q=990000991111')->assertOk();
        $this->assertSame('******-**-1111', $list->json('data.0.no_pengenalan'));
        $this->assertSame('s***@contoh.invalid', $list->json('data.0.emel'));
        $this->assertNull($list->json('data.0.alamat'));
        $this->actingAs($user)->getJson("/api/v1/pjk/penyewa/{$id}")->assertOk()->assertJsonPath('data.no_pengenalan', '******-**-1111')->assertJsonPath('data.boleh_lihat_penuh', false);

        $sulit = $this->user(['pjk.penyewa.lihat', 'pjk.sulit.lihat']);
        $this->actingAs($sulit)->getJson("/api/v1/pjk/penyewa/{$id}")->assertOk()->assertJsonPath('data.no_pengenalan', '990000-99-1111')->assertJsonPath('data.alamat', 'No 1 Jalan Ujian');
        $this->assertAudited('lihat', ['nama_entiti' => 'penyewa', 'id_rekod' => $id, 'id_pengguna' => $sulit->id]);
        // The audit trail masks the identity number too.
        $this->assertAudited('cipta', ['nama_entiti' => 'penyewa', 'id_rekod' => $id])->assertFieldMasked('no_pengenalan');
    }

    public function test_SRS_PJK_01_tenant_validation_identity_formats_and_uniqueness(): void
    {
        $user = $this->user();
        $this->actingAs($user)->postJson('/api/v1/pjk/penyewa', ['jenis_entiti' => 'individu', 'nama' => 'A'])->assertStatus(422)->assertJsonValidationErrors(['no_pengenalan']);
        $this->actingAs($user)->postJson('/api/v1/pjk/penyewa', ['jenis_entiti' => 'syarikat', 'nama' => 'B Sdn Bhd'])->assertStatus(422)->assertJsonValidationErrors(['no_pendaftaran_syarikat']);
        $this->actingAs($user)->postJson('/api/v1/pjk/penyewa', ['jenis_entiti' => 'individu', 'nama' => 'C', 'no_pengenalan' => '12345'])->assertStatus(422);
        $this->actingAs($user)->postJson('/api/v1/pjk/penyewa', ['jenis_entiti' => 'syarikat', 'nama' => 'D Sdn Bhd', 'no_pendaftaran_syarikat' => '202601000001'])->assertCreated()->assertJsonPath('data.no_pendaftaran_syarikat', '********0001');
        $this->actingAs($user)->postJson('/api/v1/pjk/penyewa', ['jenis_entiti' => 'syarikat', 'nama' => 'E', 'no_pendaftaran_syarikat' => '202601000001'])->assertStatus(422);
        $this->actingAs($this->user(['pjk.kontrak.lihat']))->getJson('/api/v1/pjk/penyewa')->assertForbidden();
    }

    public function test_SRS_PJK_01_district_officer_sees_only_tenants_of_contracts_in_scope(): void
    {
        $owner = $this->user();
        $k = $this->draft($owner);
        $sepang = $this->user(self::PENDAFTAR, ['1009']);
        $this->actingAs($sepang)->getJson("/api/v1/pjk/penyewa/{$k->penyewa_id}")->assertNotFound();
        $this->assertSame(0, $this->actingAs($sepang)->getJson('/api/v1/pjk/penyewa')->json('meta.total'));
        $this->actingAs($this->user(self::PENDAFTAR, ['1008']))->getJson("/api/v1/pjk/penyewa/{$k->penyewa_id}")->assertOk();
    }

    public function test_SRS_PJK_03_premise_register_statuses_and_history(): void
    {
        $user = $this->user();
        $lot = $this->lot(['bahagian_pemilik' => 'baitulmal']);
        $res = $this->actingAs($user)->postJson('/api/v1/pjk/premis', [
            'lot_id' => $lot, 'nama_premis' => 'Rumah Baitulmal A', 'jenis_aset' => 'rumah', 'kategori_hartanah' => 'premis_kediaman', 'keluasan' => 90, 'kadar_tawaran' => 650,
        ])->assertCreated();
        $id = $res->json('data.id');
        $res->assertJsonPath('data.status_premis', 'kosong')->assertJsonPath('data.bahagian', 'baitulmal')->assertJsonPath('data.kadar_tawaran.nilai', '650.00')->assertJsonPath('data.kod_daerah', '1008');

        $this->actingAs($user)->putJson("/api/v1/pjk/premis/{$id}", ['status_premis' => 'penyelenggaraan', 'sebab' => 'Baik pulih bumbung'])->assertOk()->assertJsonPath('data.status_premis', 'penyelenggaraan');
        $this->actingAs($user)->putJson("/api/v1/pjk/premis/{$id}", ['status_premis' => 'disewa'])->assertStatus(422);
        $show = $this->actingAs($user)->getJson("/api/v1/pjk/premis/{$id}")->assertOk();
        $this->assertSame(['penyelenggaraan', 'kosong'], array_column($show->json('data.sejarah_status'), 'status_selepas'));

        // A premise under maintenance can't be let.
        $this->actingAs($user)->postJson('/api/v1/pjk/kontrak', ['premis_id' => $id, 'penyewa_id' => $this->penyewa()->id, 'tarikh_mula' => '2026-11-01', 'tarikh_tamat' => '2027-10-31', 'kadar' => '650'])
            ->assertStatus(422)->assertJsonValidationErrors(['premis_id']);
        // Out-of-scope lot.
        $this->actingAs($this->user(self::PENDAFTAR, ['1009']))->postJson('/api/v1/pjk/premis', ['lot_id' => $lot, 'nama_premis' => 'X', 'jenis_aset' => 'rumah', 'kategori_hartanah' => 'premis_kediaman'])
            ->assertStatus(422)->assertJsonValidationErrors(['lot_id']);
    }

    public function test_SRS_PJK_04_catalogue_filters_and_public_api_exposes_only_awam_fields(): void
    {
        $user = $this->user();
        $this->premis(['kategori_hartanah' => 'bazar', 'jenis_aset' => 'gerai', 'kadar_tawaran' => '300.00', 'keluasan' => '9.00']);
        $this->premis(['kategori_hartanah' => 'premis_perniagaan', 'kadar_tawaran' => '2500.00']);
        $this->premis(['kategori_hartanah' => 'tanah', 'jenis_aset' => 'tanah', 'kadar_tawaran' => '800.00', 'kod_mukim' => '100901']);
        $this->premis(['kategori_hartanah' => 'tanah', 'jenis_aset' => 'tanah', 'kadar_tawaran' => '900.00', 'papar_portal' => false]);
        $this->active($user); // one occupied premise

        $q = fn (string $qs) => $this->actingAs($user)->getJson('/api/v1/pjk/premis?'.$qs)->assertOk()->json('meta.total');
        $this->assertSame(5, $q(''));
        $this->assertSame(1, $q('kod_daerah=1009'));
        $this->assertSame(1, $q('kod_mukim=100901'));
        $this->assertSame(2, $q('kategori_hartanah=tanah'));
        $this->assertSame(2, $q('kadar_min=500&kadar_max=1000'));
        $this->assertSame(1, $q('keluasan_max=10'));
        $this->assertSame(4, $q('status_premis=kosong'));

        $pub = $this->getJson('/api/v1/pjk/awam/katalog')->assertOk();
        $this->assertSame(3, $pub->json('meta.total')); // vacant + papar_portal
        $row = $pub->json('data.0');
        foreach (['lot_id', 'id', 'status_premis', 'bahagian', 'alamat'] as $hidden) {
            $this->assertArrayNotHasKey($hidden, $row);
        }
        $this->assertSame(1, $this->getJson('/api/v1/pjk/awam/katalog?kategori_hartanah=bazar')->json('meta.total'));
    }

    public function test_SRS_PJK_06_counter_applications_join_the_same_flow_and_can_be_rejected(): void
    {
        $user = $this->user();
        $premis = $this->premis();
        $res = $this->actingAs($user)->postJson('/api/v1/pjk/permohonan', ['premis_id' => $premis->id, 'penyewa_id' => $this->penyewa()->id, 'tempoh_bulan' => 12])->assertCreated();
        $res->assertJsonPath('data.sumber', 'kaunter')->assertJsonPath('data.status', 'diterima');
        $this->assertStringStartsWith('PJK/P/2026/', $res->json('data.no_permohonan'));
        $this->actingAs($user)->postJson('/api/v1/pjk/permohonan/'.$res->json('data.id').'/tolak', ['catatan' => 'Dokumen tidak lengkap'])->assertOk()->assertJsonPath('data.status', 'tolak');

        // Portal applications need the integration permission and a vacant, published premise.
        $this->actingAs($user)->postJson('/api/v1/pjk/portal/permohonan', ['rujukan_portal' => 'X'])->assertForbidden();
        $portal = $this->user(['pjk.portal.cipta']);
        $hidden = $this->premis(['papar_portal' => false]);
        $this->actingAs($portal)->postJson('/api/v1/pjk/portal/permohonan', ['rujukan_portal' => 'PWS-9', 'kod_premis' => $hidden->kod_premis, 'pemohon' => ['jenis_entiti' => 'individu', 'nama' => 'P', 'no_pengenalan' => '990000-99-0009']])
            ->assertStatus(422);
        $this->assertSame(1, PermohonanSewaan::query()->withoutGlobalScope('access')->count());
    }
}
