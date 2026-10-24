<?php

namespace Tests\Modules\Adu;

use App\Modules\Adu\Models\SlaKategori;
use App\Modules\Adu\Models\SubkategoriAduan;
use App\Modules\Adu\Services\AduanSearchProvider;
use App\Modules\Rbc\Contracts\PermissionRegistry;

class CapaianKonfigurasiTest extends AduTestCase
{
    public function test_SRS_ADU_01_every_endpoint_requires_permission_and_respects_district_scope(): void
    {
        $sepangLot = $this->lot('100901');
        $aduan = $this->aduan(['lot_id' => $sepangLot]);

        $this->getJson('/api/v1/adu/aduan')->assertUnauthorized();
        $none = $this->user([]);
        $this->actingAs($none)->getJson('/api/v1/adu/aduan')->assertForbidden();
        $this->actingAs($none)->getJson("/api/v1/adu/aduan/{$aduan->id}")->assertForbidden();
        $this->actingAs($none)->postJson('/api/v1/adu/aduan', [])->assertForbidden();
        $this->actingAs($none)->getJson('/api/v1/adu/laporan/ringkasan')->assertForbidden();
        $this->actingAs($none)->getJson('/api/v1/adu/konfigurasi')->assertForbidden();

        $petaling = $this->user(self::UNIT_ADUAN, ['1008']);
        $this->actingAs($petaling)->getJson("/api/v1/adu/aduan/{$aduan->id}")->assertNotFound();
        $this->actingAs($petaling)->postJson("/api/v1/adu/aduan/{$aduan->id}/pengadu/dedah", ['tujuan' => 'Cubaan luar skop'])->assertNotFound();
        $this->actingAs($petaling)->postJson("/api/v1/adu/aduan/{$aduan->id}/tugaskan", ['id_pegawai' => $petaling->id])->assertNotFound();
        $this->assertSame([], $this->actingAs($petaling)->getJson('/api/v1/adu/aduan')->json('data'));

        $sepang = $this->user(['adu.aduan.lihat'], ['1009']);
        $this->actingAs($sepang)->getJson("/api/v1/adu/aduan/{$aduan->id}")->assertOk();
        $this->actingAs($sepang)->postJson("/api/v1/adu/aduan/{$aduan->id}/tindakan", ['jenis' => 'catatan', 'keterangan' => 'x'])->assertForbidden();

        $this->assertArrayHasKey('adu', app(PermissionRegistry::class)->all());
    }

    public function test_SRS_ADU_08_categories_and_subcategories_are_configurable(): void
    {
        $admin = $this->user(['adu.konfigurasi.lihat', 'adu.konfigurasi.kemas_kini', 'adu.aduan.lihat']);
        $this->actingAs($admin)->postJson('/api/v1/adu/konfigurasi/subkategori', ['kod_kategori' => 'PENCEROBOHAN', 'kod' => 'TERNAKAN', 'nama' => 'Ternakan tanpa izin'])->assertCreated();
        $this->actingAs($admin)->postJson('/api/v1/adu/konfigurasi/subkategori', ['kod_kategori' => 'PENCEROBOHAN', 'kod' => 'TERNAKAN', 'nama' => 'Dua'])->assertUnprocessable();
        $this->actingAs($admin)->postJson('/api/v1/adu/konfigurasi/subkategori', ['kod_kategori' => 'TIADA', 'kod' => 'X', 'nama' => 'X'])->assertUnprocessable();

        $ref = $this->actingAs($admin)->getJson('/api/v1/adu/rujukan')->assertOk()->json('data');
        $this->assertContains('Pencerobohan tanah', array_column($ref['kategori'], 'nama'));
        $this->assertContains('Ternakan tanpa izin', array_column($ref['subkategori']['PENCEROBOHAN'], 'nama'));
        $this->assertContains('Penghuni haram', array_column($ref['subkategori']['PENCEROBOHAN'], 'nama'));
        $this->assertContains('Kecurian aset / bahan', array_column($ref['subkategori']['KEROSAKAN_ASET'], 'nama'));

        // Subcategory must belong to the category.
        $officer = $this->user(self::PEGAWAI);
        $this->actingAs($officer)->postJson('/api/v1/adu/aduan', ['saluran_penerimaan' => 'telefon', 'kategori' => 'PENCEROBOHAN', 'subkategori' => 'KECURIAN', 'keterangan' => 'Kecurian kabel di tapak.', 'tanpa_nama' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('subkategori');
        $this->actingAs($officer)->postJson('/api/v1/adu/aduan', ['saluran_penerimaan' => 'telefon', 'kategori' => 'KEROSAKAN_ASET', 'subkategori' => 'KECURIAN', 'keterangan' => 'Kecurian kabel di tapak.', 'tanpa_nama' => true])
            ->assertCreated()->assertJsonPath('data.subkategori_nama', 'Kecurian aset / bahan');
        $this->assertSame(1, SubkategoriAduan::query()->where('kod', 'TERNAKAN')->count());
    }

    public function test_SRS_ADU_12_sla_and_escalation_path_are_configurable_with_versions(): void
    {
        $admin = $this->user(['adu.konfigurasi.lihat', 'adu.konfigurasi.kemas_kini']);
        $sla = SlaKategori::query()->where('kategori', 'PENCEROBOHAN')->firstOrFail();
        $payload = ['kategori' => 'PENCEROBOHAN', 'tempoh_respons' => 1, 'tempoh_penyelesaian' => 20, 'unit_masa' => 'hari_bekerja', 'tarikh_berkuat_kuasa' => now()->toDateString(),
            'laluan_eskalasi' => [['peringkat' => 1, 'sasaran' => 'selesai', 'peratus' => 75, 'penerima' => ['penyelia']], ['peringkat' => 2, 'sasaran' => 'selesai', 'peratus' => 100, 'penerima' => ['ketua_bahagian', 'peranan:pengarah']]]];
        $this->actingAs($admin)->putJson("/api/v1/adu/konfigurasi/sla/{$sla->id}", $payload)->assertCreated();
        $this->assertSame(30, $sla->refresh()->tempoh_penyelesaian, 'in-force row kept');
        $this->assertSame(2, SlaKategori::query()->where('kategori', 'PENCEROBOHAN')->count(), 'change stored as a new version');
        $this->actingAs($admin)->postJson('/api/v1/adu/konfigurasi/sla', ['laluan_eskalasi' => [['peringkat' => 1, 'sasaran' => 'x', 'peratus' => 1, 'penerima' => ['sesiapa']]]] + $payload)->assertUnprocessable();

        $aduan = $this->aduan();
        $this->assertSame(20, $aduan->slaSemasa->sla->tempoh_penyelesaian);
    }

    public function test_global_search_finds_complaints_by_reference_within_scope(): void
    {
        $aduan = $this->aduan(['lot_id' => $this->lot('100901')]);
        $provider = app(AduanSearchProvider::class);
        $user = $this->user(['adu.aduan.lihat']);
        $this->actingAs($user);
        $this->assertSame($aduan->id, $provider->search((string) $aduan->no_rujukan_aduan, $user)[0]['id']);
        $this->assertSame('/admin/adu/aduan/'.$aduan->id, $provider->link($aduan->id, $user));

        $petaling = $this->user(['adu.aduan.lihat'], ['1008']);
        $this->actingAs($petaling);
        $this->assertSame([], $provider->search((string) $aduan->no_rujukan_aduan, $petaling));
        $this->assertNull($provider->link($aduan->id, $petaling));
        $this->assertSame([], $provider->search('bin Abu', $user), 'reporter identity is not searchable');
    }
}
