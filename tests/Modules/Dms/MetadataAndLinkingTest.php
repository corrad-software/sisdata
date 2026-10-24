<?php

namespace Tests\Modules\Dms;

use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Dms\Contracts\RecordLinks;
use App\Modules\Dms\Models\Dokumen;
use App\Modules\Dms\Models\DokumenKategori;
use App\Modules\Dms\Models\DokumenMedanMetadata;
use Tests\Modules\Dms\Support\TestFiles;

class MetadataAndLinkingTest extends DmsTestCase
{
    public function test_SRS_DMS_10_structured_metadata_is_stored(): void
    {
        $user = $this->userWith();
        $owner = $this->userWith();

        $doc = $this->uploadDoc($user, TestFiles::pdf(), [
            'kategori' => 'perjanjian',
            'klasifikasi' => 'dalaman',
            'modul_sumber' => 'pjk',
            'jenis_entiti_induk' => 'kontrak_pajakan',
            'id_rekod_induk' => 'KP-2026-001',
            'kod_daerah' => '10',
            'kod_mukim' => '1001',
            'tarikh_dokumen' => '2025-03-15',
            'tarikh_luput' => '2030-03-14',
            'pemilik_proses_id' => $owner->id,
            'kata_kunci' => 'sewaan, kedai, Klang',
            'tajuk' => 'Perjanjian sewa kedai',
        ]);

        $this->assertSame('perjanjian', $doc->kategori->kod);
        $this->assertSame('pjk', $doc->modul_sumber);
        $this->assertSame(['kontrak_pajakan', 'KP-2026-001'], [$doc->jenis_entiti_induk, $doc->id_rekod_induk]);
        $this->assertSame(['10', '1001'], [$doc->kod_daerah, $doc->kod_mukim]);
        $this->assertSame(2025, $doc->tahun);
        $this->assertSame('aktif', $doc->status);
        $this->assertSame('2030-03-14', $doc->tarikh_luput->toDateString());
        $this->assertSame($owner->id, $doc->pemilik_proses_id);
        $this->assertSame(['sewaan', 'kedai', 'Klang'], $doc->kata_kunci);
        $this->assertSame('dalaman', $doc->klasifikasi_keselamatan);
        $this->assertMatchesRegularExpression('/^DMS-\d{4}-\d{6}$/', $doc->no_rujukan);
    }

    public function test_SRS_DMS_11_admin_adds_changes_and_deactivates_metadata_fields_without_code(): void
    {
        $admin = $this->userWith(['dms.dokumen.lihat', 'dms.konfigurasi.lihat', 'dms.konfigurasi.kemas_kini']);
        $user = $this->userWith();
        $kategori = DokumenKategori::query()->where('kod', 'geran')->firstOrFail();

        $this->actingAs($admin)->postJson('/api/v1/dms/medan', [
            'kategori_id' => $kategori->id, 'kod' => 'no_hakmilik', 'label' => 'No. Hak Milik', 'jenis' => 'teks', 'wajib' => true,
        ])->assertCreated();
        $field = $this->actingAs($admin)->postJson('/api/v1/dms/medan', [
            'kategori_id' => $kategori->id, 'kod' => 'jenis_hakmilik', 'label' => 'Jenis Hak Milik', 'jenis' => 'senarai', 'pilihan' => ['Geran', 'PM', 'HSD'],
        ])->assertCreated()->json('data.id');
        $this->actingAs($admin)->postJson('/api/v1/dms/kategori', ['kod' => 'pelan_ukur', 'nama' => 'Pelan Ukur', 'had_saiz_mb' => 100])->assertCreated();

        // Required field enforced; list values enforced.
        $this->upload($user, TestFiles::pdf(), ['kategori' => 'geran', 'klasifikasi' => 'dalaman'])
            ->assertStatus(422)->assertJsonValidationErrors('metadata.no_hakmilik');
        $this->upload($user, TestFiles::pdf(), ['kategori' => 'geran', 'klasifikasi' => 'dalaman', 'metadata' => ['no_hakmilik' => 'GRN 123', 'jenis_hakmilik' => 'Lain']])
            ->assertStatus(422)->assertJsonValidationErrors('metadata.jenis_hakmilik');

        $doc = $this->uploadDoc($user, TestFiles::pdf(), ['kategori' => 'geran', 'klasifikasi' => 'dalaman', 'metadata' => ['no_hakmilik' => 'GRN 123', 'jenis_hakmilik' => 'PM', 'tidak_wujud' => 'x']]);
        $this->assertSame(['no_hakmilik' => 'GRN 123', 'jenis_hakmilik' => 'PM'], $doc->metadata);

        // Change the list values, then deactivate the field — old values are kept, new uploads no longer need it.
        $this->actingAs($admin)->putJson("/api/v1/dms/medan/{$field}", ['pilihan' => ['Geran', 'PM', 'HSD', 'PN']])->assertOk();
        $this->actingAs($admin)->putJson('/api/v1/dms/medan/'.DokumenMedanMetadata::query()->where('kod', 'no_hakmilik')->value('id'), ['aktif' => false])->assertOk();
        $this->uploadDoc($user, TestFiles::pdf(), ['kategori' => 'geran', 'klasifikasi' => 'dalaman', 'metadata' => ['jenis_hakmilik' => 'PN']]);

        $this->actingAs($admin)->getJson('/api/v1/dms/kategori?semua=1')->assertOk()
            ->assertJsonFragment(['kod' => 'pelan_ukur'])
            ->assertJsonFragment(['kod' => 'no_hakmilik', 'aktif' => false]);

        // Officers can't configure.
        $this->actingAs($user)->postJson('/api/v1/dms/medan', ['kod' => 'x', 'label' => 'X', 'jenis' => 'teks'])->assertForbidden();
    }

    public function test_SRS_DMS_10_metadata_update_keeps_extra_fields_and_resets_expiry_reminder(): void
    {
        $user = $this->userWith();
        $doc = $this->uploadDoc($user, TestFiles::pdf(), ['tarikh_luput' => now()->addDays(3)->toDateString()]);
        $doc->forceFill(['amaran_luput_pada' => now()])->save();

        $this->actingAs($user)->putJson("/api/v1/dms/dokumen/{$doc->id}", [
            'tajuk' => 'Tajuk baharu', 'tarikh_luput' => now()->addYear()->toDateString(), 'kata_kunci' => ['a', 'b'],
        ])->assertOk()->assertJsonPath('data.tajuk', 'Tajuk baharu');

        $doc->refresh();
        $this->assertNull($doc->amaran_luput_pada);
        $this->assertSame(['a', 'b'], $doc->kata_kunci);

        $this->actingAs($this->userWith(['dms.dokumen.lihat']))->putJson("/api/v1/dms/dokumen/{$doc->id}", ['tajuk' => 'x'])->assertForbidden();
    }

    public function test_SRS_DMS_16_documents_attach_to_any_record_and_list_from_the_record(): void
    {
        $user = $this->userWith();

        $this->actingAs($user)->post('/api/v1/dms/rekod/lot_tanah/123/dokumen', [
            'fail' => [TestFiles::pdf('Pelan tanah'), TestFiles::kml()],
            'kategori' => 'fail_gis',
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('diterima', 2);
        $this->uploadDoc($user, TestFiles::pdf(), ['jenis_entiti_induk' => 'lot_tanah', 'id_rekod_induk' => '999']);

        $list = $this->actingAs($user)->getJson('/api/v1/dms/rekod/lot_tanah/123/dokumen')->assertOk()->json('data');
        $this->assertCount(2, $list);
        $this->assertEqualsCanonicalizing(['pdf', 'kml'], array_column($list, 'sambungan'));

        $this->actingAs($this->userWith([]))->getJson('/api/v1/dms/rekod/lot_tanah/123/dokumen')->assertForbidden();
    }

    public function test_SRS_DMS_17_document_shows_its_parent_with_an_access_checked_deep_link(): void
    {
        app(RecordLinks::class)->register('lot_tanah', 'Lot Tanah', '/admin/pro/lot/{id}', 'pro.lot.lihat');
        $user = $this->userWith();
        $doc = $this->uploadDoc($user, TestFiles::pdf(), ['jenis_entiti_induk' => 'lot_tanah', 'id_rekod_induk' => 'L 12']);

        $this->actingAs($user)->getJson("/api/v1/dms/dokumen/{$doc->id}")->assertOk()
            ->assertJsonPath('data.induk.label', 'Lot Tanah')
            ->assertJsonPath('data.induk.pautan', null); // no pro.lot.lihat

        $this->acl->grant($user, 'pro.lot.lihat');
        $this->actingAs($user)->getJson("/api/v1/dms/dokumen/{$doc->id}")
            ->assertJsonPath('data.induk.pautan', '/admin/pro/lot/L%2012')
            ->assertJsonPath('data.induk.id', 'L 12');

        // A caller-supplied link wins.
        $doc2 = $this->uploadDoc($user, TestFiles::pdf(), ['jenis_entiti_induk' => 'aduan', 'id_rekod_induk' => '5', 'pautan_induk' => '/admin/adu/aduan/5']);
        $this->actingAs($user)->getJson("/api/v1/dms/dokumen/{$doc2->id}")->assertJsonPath('data.induk.pautan', '/admin/adu/aduan/5');
    }

    public function test_SRS_DMS_18_other_modules_use_the_document_store_contract(): void
    {
        $user = $this->userWith();
        $store = app(DocumentStore::class);

        $id = $store->store(TestFiles::pdf(), 'hak_milik', 42, 'geran', $user, 'dalaman', ['kod_daerah' => '10', 'modul_sumber' => 'hmt']);
        $this->assertTrue(Dokumen::query()->whereKey($id)->where('modul_sumber', 'hmt')->exists());

        $list = $store->forOwner('hak_milik', 42, $user);
        $this->assertSame($id, $list[0]['id']);
        $this->assertSame('geran', $list[0]['category']);

        $url = $store->temporaryUrl($id, $user);
        $this->assertNotNull($url);
        $this->actingAs($user)->get($url)->assertOk();

        $this->assertSame([], $store->forOwner('hak_milik', 42, $this->userWith([])));
        $this->assertNull($store->temporaryUrl($id, $this->userWith([])));

        $this->expectException(DocumentRejected::class);
        $store->store(TestFiles::exe(), 'hak_milik', 42, 'geran', $user);
    }

    public function test_SRS_DMS_18_document_store_rejects_unknown_category_in_malay(): void
    {
        try {
            app(DocumentStore::class)->store(TestFiles::pdf(), 'x', 1, 'tiada_kategori', $this->userWith());
            $this->fail('expected rejection');
        } catch (DocumentRejected $e) {
            $this->assertStringContainsString('tidak wujud', $e->getMessage());
        }
    }
}
