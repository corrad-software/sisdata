<?php

namespace Tests\Modules\Pro;

use App\Modules\Pro\Contracts\LotRegistry;
use App\Modules\Pro\Models\LotTanah;
use App\Modules\Pro\Models\VersiRekodInduk;
use App\Modules\Pro\Services\LotService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LotMasterTest extends ProTestCase
{
    public function test_SRS_PRO_06_schema_has_the_ten_core_entities_as_separate_tables(): void
    {
        // hak_milik / pemerolehan are created by MOD-HKM / MOD-PER, dokumen by MOD-DMS.
        foreach (['lot_tanah', 'penilaian', 'kontrak_pajakan', 'cukai', 'dokumen', 'aduan', 'kes_mahkamah', 'data_spatial'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Jadual {$table} tiada");
            $this->assertTrue(Schema::hasColumn($table, 'id'));
        }
        foreach (['penilaian', 'kontrak_pajakan', 'cukai', 'aduan'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'lot_id'), "{$table}.lot_id tiada");
        }
        $this->assertTrue(Schema::hasTable('kes_mahkamah_lot'), 'kes_mahkamah N:N lot');
    }

    public function test_SRS_PRO_07_data_spatial_is_a_full_entity_with_geometry_srid_source_date_and_status(): void
    {
        foreach (['geometri', 'srid_asal', 'sumber_data', 'tarikh_perolehan_data', 'updated_at', 'status_pengesahan', 'id_pegawai_pengesah', 'keluasan_dikira', 'versi'] as $column) {
            $this->assertTrue(Schema::hasColumn('data_spatial', $column), "data_spatial.{$column}");
        }
        $type = DB::selectOne("SELECT type FROM geometry_columns WHERE f_table_name = 'data_spatial' AND f_geometry_column = 'geometri'");
        $this->assertSame('MULTIPOLYGON', $type->type);
        $this->assertNotNull(DB::selectOne("SELECT 1 AS x FROM pg_indexes WHERE tablename = 'data_spatial' AND indexdef ILIKE '%gist%'"));
    }

    public function test_SRS_PRO_13_create_lot_normalises_number_and_derives_daerah(): void
    {
        $user = $this->officer();

        $response = $this->actingAs($user)->postJson('/api/v1/pro/lot', $this->lotData(['no_lot' => 'lot no. 0777']));

        $response->assertCreated()->assertJsonPath('data.no_lot', 'Lot 777')->assertJsonPath('data.kod_daerah', '1008')->assertJsonPath('data.nama_mukim', 'Petaling');
        $this->assertDatabaseHas('lot_tanah', ['no_lot' => 'Lot 777', 'no_lot_normal' => 'LOT 777', 'kod_negeri' => '10', 'id_pencipta' => $user->id]);
    }

    public function test_SRS_PRO_13_required_fields_and_ranges_are_enforced_with_malay_messages_naming_the_field(): void
    {
        $user = $this->officer();

        $response = $this->actingAs($user)->postJson('/api/v1/pro/lot', [
            'no_lot' => 'ABC-??', 'kod_mukim' => '999999', 'keluasan' => -5, 'bahagian_pemilik' => 'lain',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['no_lot', 'kod_mukim', 'keluasan', 'bahagian_pemilik']);
        $errors = $response->json('errors');
        $this->assertStringContainsString('No. Lot', $errors['no_lot'][0]);
        $this->assertStringContainsString('format', $errors['no_lot'][0]);
        $this->assertStringContainsString('Keluasan', $errors['keluasan'][0]);
        $this->assertStringContainsString('MOD-REF', $errors['kod_mukim'][0]);

        $this->actingAs($user)->postJson('/api/v1/pro/lot', ['kod_mukim' => '100803'])
            ->assertStatus(422)->assertJsonValidationErrors(['no_lot', 'keluasan', 'bahagian_pemilik']);
        $this->actingAs($user)->postJson('/api/v1/pro/lot', $this->lotData(['keluasan' => 'besar']))
            ->assertStatus(422)->assertJsonPath('errors.keluasan.0', 'Keluasan: Keluasan mesti nombor.');
    }

    public function test_SRS_DAT_01_lot_number_is_unique_per_mukim_even_when_written_differently(): void
    {
        $user = $this->officer();
        $this->makeLot(['no_lot' => 'Lot 500']);

        $this->actingAs($user)->postJson('/api/v1/pro/lot', $this->lotData(['no_lot' => 'L500']))
            ->assertStatus(422)->assertJsonPath('errors.no_lot.0', 'No. Lot: No. lot ini telah didaftarkan dalam mukim yang sama.');

        // Same number in another mukim is fine.
        $this->actingAs($user)->postJson('/api/v1/pro/lot', $this->lotData(['no_lot' => 'Lot 500', 'kod_mukim' => '100802']))->assertCreated();
    }

    public function test_SRS_DAT_01_daerah_must_match_the_mukim(): void
    {
        $this->actingAs($this->officer())->postJson('/api/v1/pro/lot', $this->lotData(['kod_daerah' => '1001']))
            ->assertStatus(422)->assertJsonValidationErrors(['kod_daerah']);
    }

    public function test_SRS_PRO_13_quality_rules_are_configurable(): void
    {
        $user = $this->officer();
        DB::table('peraturan_kualiti_data')->where(['entiti' => 'lot_tanah', 'medan' => 'zoning', 'jenis_peraturan' => 'panjang'])
            ->update(['ungkapan_peraturan' => json_encode(['max' => 5])]);

        $this->actingAs($user)->postJson('/api/v1/pro/lot', $this->lotData(['zoning' => 'Pertanian']))
            ->assertStatus(422)->assertJsonValidationErrors(['zoning']);
    }

    public function test_SRS_PRO_25_reference_lists_come_from_mod_ref(): void
    {
        $response = $this->actingAs($this->officer(['pro.lot.lihat']))->getJson('/api/v1/pro/rujukan')->assertOk();

        $this->assertSame(DB::table('ref_mukim')->whereNull('sah_hingga')->count(), count($response->json('data.mukim')));
        $this->assertContains('PERTANIAN', array_column($response->json('data.kategori_guna_tanah'), 'kod'));
        $this->assertContains('keluasan', $response->json('data.medan_kritikal'));
    }

    public function test_SRS_PRO_22_non_critical_change_applies_immediately_as_a_new_version(): void
    {
        $user = $this->officer();
        $lot = $this->makeLot();

        $this->actingAs($user)->putJson("/api/v1/pro/lot/{$lot->id}", ['zoning' => 'Kediaman', 'versi_rekod' => 1])
            ->assertOk()->assertJsonPath('tertunda', null)->assertJsonPath('data.zoning', 'Kediaman')->assertJsonPath('data.versi_rekod', 2);

        $version = VersiRekodInduk::query()->where('id_rekod', $lot->id)->where('no_versi', 2)->firstOrFail();
        $this->assertSame(['zoning' => null], $version->nilai_sebelum);
        $this->assertSame(['zoning' => 'Kediaman'], $version->nilai_selepas);
        $this->assertAudited('kemas_kini', ['nama_entiti' => 'lot_tanah', 'id_rekod' => $lot->id])->assertFieldChanged('zoning', null, 'Kediaman');
    }

    public function test_SRS_DAT_04_versions_keep_old_values_and_can_be_compared(): void
    {
        $user = $this->officer();
        $lot = $this->makeLot();
        $this->actingAs($user)->putJson("/api/v1/pro/lot/{$lot->id}", ['zoning' => 'A'])->assertOk();
        $this->actingAs($user)->putJson("/api/v1/pro/lot/{$lot->id}", ['zoning' => 'B'])->assertOk();

        $versions = $this->actingAs($user)->getJson("/api/v1/pro/lot/{$lot->id}/versi")->assertOk()->json('data');
        $this->assertCount(3, $versions);
        $latest = $versions[0];
        $compare = $this->actingAs($user)->getJson("/api/v1/pro/lot/{$lot->id}/versi/{$latest['id']}")->assertOk();
        $compare->assertJsonPath('data.perbandingan.0.medan', 'zoning')->assertJsonPath('data.perbandingan.0.sebelum', 'A')->assertJsonPath('data.perbandingan.0.selepas', 'B')
            ->assertJsonPath('data.perbandingan.0.label', 'Zoning');
    }

    public function test_SRS_PRO_22_critical_change_needs_second_officer_via_workflow(): void
    {
        $maker = $this->officer();
        $checker = $this->officer();
        $lot = $this->makeLot();

        $this->actingAs($maker)->putJson("/api/v1/pro/lot/{$lot->id}", ['keluasan' => 2.5])
            ->assertStatus(422)->assertJsonValidationErrors(['justifikasi']);

        $response = $this->actingAs($maker)->putJson("/api/v1/pro/lot/{$lot->id}", ['keluasan' => 2.5, 'zoning' => 'Z1', 'justifikasi' => 'Pelan akui baharu']);
        $response->assertOk()->assertJsonPath('diterapkan', ['zoning'])->assertJsonPath('tertunda.medan', ['keluasan']);

        // Not applied yet.
        $this->assertSame('1.2500', $lot->refresh()->keluasan);
        $this->assertSame('Z1', $lot->zoning);
        $started = array_values($this->workflow->started)[0];
        $this->assertSame(LotService::FLOW_CRITICAL, $started['flow']);
        $this->assertSame('1008', $started['context']['kod_daerah']);

        // A second critical change while one is pending is refused.
        $this->actingAs($maker)->putJson("/api/v1/pro/lot/{$lot->id}", ['bahagian_pemilik' => 'hartanah', 'justifikasi' => 'x'])->assertStatus(409);

        $this->completeWorkflow(LotService::FLOW_CRITICAL, LotService::SUBJECT_VERSION, $started['subjectId'], 'lulus', $checker->id);

        $this->assertSame('2.5000', $lot->refresh()->keluasan);
        $version = VersiRekodInduk::query()->findOrFail($started['subjectId']);
        $this->assertSame('lulus', $version->status_pengesahan);
        $this->assertSame($checker->id, $version->id_pengesah_kedua);
        $this->assertSame($lot->versi_rekod, $version->no_versi);
        $this->assertSame(['keluasan' => '1.2500'], $version->nilai_sebelum);
        $this->assertCount(1, $this->notifier->of('pro.perubahan_kritikal_keputusan'));
        $this->assertAudited('kemas_kini', ['nama_entiti' => 'lot_tanah', 'id_rekod' => $lot->id, 'sebab_perubahan' => 'Pelan akui baharu']);
    }

    public function test_SRS_PRO_22_rejected_or_self_approved_critical_change_is_not_applied(): void
    {
        $maker = $this->officer();
        $lot = $this->makeLot();

        $this->actingAs($maker)->putJson("/api/v1/pro/lot/{$lot->id}", ['no_lot' => 'Lot 999', 'justifikasi' => 'Salah taip'])->assertOk();
        $id = array_values($this->workflow->started)[0]['subjectId'];
        $this->completeWorkflow(LotService::FLOW_CRITICAL, LotService::SUBJECT_VERSION, $id, 'lulus', $maker->id);

        $this->assertSame('Lot 1234', $lot->refresh()->no_lot);
        $this->assertSame('tolak', VersiRekodInduk::query()->findOrFail($id)->status_pengesahan);

        $this->actingAs($maker)->putJson("/api/v1/pro/lot/{$lot->id}", ['no_lot' => 'Lot 999', 'justifikasi' => 'Cuba lagi'])->assertOk();
        $id2 = array_values($this->workflow->started)[1]['subjectId'];
        $this->completeWorkflow(LotService::FLOW_CRITICAL, LotService::SUBJECT_VERSION, $id2, 'tolak', $this->officer()->id);
        $this->assertSame('Lot 1234', $lot->refresh()->no_lot);
    }

    public function test_SRS_PRO_22_missing_workflow_configuration_is_reported_and_nothing_changes(): void
    {
        $this->workflow->missing = true;
        $lot = $this->makeLot();

        $this->actingAs($this->officer())->putJson("/api/v1/pro/lot/{$lot->id}", ['keluasan' => 9, 'justifikasi' => 'x'])
            ->assertStatus(422)->assertJsonPath('message', 'Aliran kelulusan "pro.perubahan_kritikal" belum dikonfigurasi dalam Workflow. Sila hubungi pentadbir.');
        $this->assertSame(0, VersiRekodInduk::query()->where('status_pengesahan', 'menunggu')->count());
    }

    public function test_SRS_PRO_01_concurrent_edit_is_rejected_by_optimistic_locking(): void
    {
        $user = $this->officer();
        $lot = $this->makeLot();
        $this->actingAs($user)->putJson("/api/v1/pro/lot/{$lot->id}", ['zoning' => 'A', 'versi_rekod' => 1])->assertOk();

        $this->actingAs($user)->putJson("/api/v1/pro/lot/{$lot->id}", ['zoning' => 'B', 'versi_rekod' => 1])->assertStatus(409);
        $this->assertSame('A', $lot->refresh()->zoning);
    }

    public function test_SRS_PRO_01_failed_transaction_rolls_back_completely(): void
    {
        $before = LotTanah::query()->count();
        try {
            DB::transaction(function () {
                $this->makeLot(['no_lot' => 'Lot 1']);
                DB::statement('SELECT 1/0');
            });
        } catch (\Throwable) {
        }
        $this->assertSame($before, LotTanah::query()->count());
        $this->assertSame(0, VersiRekodInduk::query()->count());
    }

    public function test_SRS_GEN_01_user_without_permission_is_refused(): void
    {
        $this->getJson('/api/v1/pro/lot')->assertUnauthorized();
        $lot = $this->makeLot();
        $viewer = $this->officer(['pro.lot.lihat']);

        $this->actingAs($this->officer([]))->getJson('/api/v1/pro/lot')->assertForbidden();
        $this->actingAs($viewer)->postJson('/api/v1/pro/lot', $this->lotData())->assertForbidden();
        $this->actingAs($viewer)->putJson("/api/v1/pro/lot/{$lot->id}", ['zoning' => 'x'])->assertForbidden();
    }

    public function test_SRS_RBC_07_out_of_scope_district_lots_are_invisible_and_cannot_be_created(): void
    {
        $petaling = $this->makeLot();
        $klang = $this->makeLot(['no_lot' => 'Lot 1', 'kod_mukim' => '100101']);
        $user = $this->officer(self::PEGAWAI, ['1001']);

        $ids = array_column($this->actingAs($user)->getJson('/api/v1/pro/lot')->assertOk()->json('data'), 'id');
        $this->assertSame([$klang->id], $ids);
        $this->actingAs($user)->getJson("/api/v1/pro/lot/{$petaling->id}")->assertNotFound();
        $this->actingAs($user)->getJson("/api/v1/pro/lot/{$petaling->id}/profil")->assertNotFound();
        $this->actingAs($user)->putJson("/api/v1/pro/lot/{$petaling->id}", ['zoning' => 'x'])->assertNotFound();
        $this->actingAs($user)->postJson('/api/v1/pro/lot', $this->lotData(['no_lot' => 'Lot 2']))
            ->assertStatus(422)->assertJsonValidationErrors(['kod_daerah']);
        // Moving a lot out of the user's district is refused too.
        $this->actingAs($user)->putJson("/api/v1/pro/lot/{$klang->id}", ['kod_mukim' => '100803', 'justifikasi' => 'x'])->assertStatus(422);
    }

    public function test_SRS_PRO_14_sulit_lots_and_fields_need_explicit_permission(): void
    {
        $open = $this->makeLot(['syarat_nyata' => 'Tanaman kekal sahaja']);
        $secret = $this->makeLot(['no_lot' => 'Lot 2', 'klasifikasi' => 'sulit']);
        $plain = $this->officer(['pro.lot.lihat']);
        $cleared = $this->officer(['pro.lot.lihat', 'pro.sulit.lihat']);

        $rows = $this->actingAs($plain)->getJson('/api/v1/pro/lot')->assertOk()->json('data');
        $this->assertSame([$open->id], array_column($rows, 'id'));
        $this->actingAs($plain)->getJson("/api/v1/pro/lot/{$secret->id}")->assertNotFound();
        $this->actingAs($plain)->getJson("/api/v1/pro/lot/{$open->id}")->assertOk()
            ->assertJsonPath('data.syarat_nyata', null)->assertJsonPath('data.medan_disamarkan', ['syarat_nyata']);

        $this->assertCount(2, $this->actingAs($cleared)->getJson('/api/v1/pro/lot')->json('data'));
        $this->actingAs($cleared)->getJson("/api/v1/pro/lot/{$open->id}")->assertJsonPath('data.syarat_nyata', 'Tanaman kekal sahaja');

        // Classifying above one's own level is refused.
        $this->actingAs($this->officer())->postJson('/api/v1/pro/lot', $this->lotData(['no_lot' => 'Lot 3', 'klasifikasi' => 'rahsia']))
            ->assertStatus(422)->assertJsonValidationErrors(['klasifikasi']);
    }

    public function test_SRS_PRO_19_create_update_delete_are_audited_with_reason(): void
    {
        $user = $this->officer([...self::PEGAWAI, 'pro.lot.padam']);
        $id = $this->actingAs($user)->postJson('/api/v1/pro/lot', $this->lotData(['status_lot' => 'draf']))->assertCreated()->json('data.id');

        $this->assertAudited('cipta', ['nama_entiti' => 'lot_tanah', 'id_rekod' => $id, 'id_pengguna' => $user->id, 'modul' => 'pro']);
        $this->actingAs($user)->deleteJson("/api/v1/pro/lot/{$id}", ['sebab' => 'Rekod percubaan'])->assertOk();
        $this->assertAudited('padam', ['nama_entiti' => 'lot_tanah', 'id_rekod' => $id, 'sebab_perubahan' => 'Rekod percubaan']);

        $active = $this->makeLot(['no_lot' => 'Lot 2']);
        $this->actingAs($user)->deleteJson("/api/v1/pro/lot/{$active->id}", ['sebab' => 'x'])->assertStatus(422);
    }

    public function test_SRS_PRO_21_tags_are_searchable_metadata(): void
    {
        $tagged = $this->makeLot(['tag' => ['masjid', 'tepi_jalan']]);
        $this->makeLot(['no_lot' => 'Lot 2']);

        $rows = $this->actingAs($this->officer())->getJson('/api/v1/pro/lot?tag=masjid')->assertOk()->json('data');
        $this->assertSame([$tagged->id], array_column($rows, 'id'));
        $rows = $this->actingAs($this->officer())->getJson('/api/v1/pro/lot?q=L1234')->json('data');
        $this->assertSame([$tagged->id], array_column($rows, 'id'));
    }

    public function test_SRS_PRO_06_lot_registry_contract_applies_the_users_scope(): void
    {
        $lot = $this->makeLot();
        $registry = app(LotRegistry::class);
        $outsider = $this->officer(['pro.lot.lihat'], ['1001']);

        $this->assertSame('Lot 1234', $registry->find($lot->id)['no_lot']);
        $this->assertNull($registry->find($lot->id, $outsider));
        $this->assertFalse($registry->exists($lot->id, $outsider));
        $this->assertSame($lot->id, $registry->findByNumber('100803', 'Lot No. 1234'));
        $this->assertNull($registry->findByNumber('100803', '1234', $outsider));
        $this->assertSame('/admin/pro/lot/'.$lot->id, $registry->profileLink($lot->id));

        $klang = $this->makeLot(['no_lot' => 'PT 77', 'kod_mukim' => '100101']);
        $this->assertEqualsCanonicalizing([$lot->id, $klang->id], array_keys($registry->summaries([$lot->id, $klang->id, 'bukan-uuid'])));
        $this->assertSame([$klang->id], array_keys($registry->summaries([$lot->id, $klang->id], $outsider)));
        $this->assertSame(['PT 77'], array_column($registry->search('pt no. 77'), 'no_lot'));
        $this->assertSame([], $registry->search('1234', $outsider));
        $this->assertSame(['Lot 1234'], array_column($registry->search('', null, 10, ['kod_mukim' => '100803']), 'no_lot'));
    }

    public function test_SRS_PRO_10_list_summary_counts_verification_flags(): void
    {
        $a = $this->makeLot();
        $this->makeLot(['no_lot' => 'Lot 2', 'bahagian_pemilik' => 'baitulmal']);
        $this->addGeometry($a, $this->square());

        $this->actingAs($this->officer(['pro.lot.lihat']))->getJson('/api/v1/pro/lot/ringkasan')->assertOk()
            ->assertJsonPath('data.jumlah', 2)->assertJsonPath('data.tiada_geometri', 1)
            ->assertJsonPath('data.status_pengesahan.belum_disahkan', 1)->assertJsonPath('data.bahagian.baitulmal', 1);
    }
}
