<?php

namespace Tests\Modules\Tsu;

use App\Modules\Tsu\Models\TemplatProses;
use App\Modules\Tsu\Models\UrusanPeringkat;
use App\Modules\Tsu\Models\UrusanTanah;
use App\Modules\Tsu\Support\Lalai;
use Illuminate\Support\Facades\DB;

class UrusanTemplatTest extends TsuTestCase
{
    public function test_SRS_TSU_01_seven_urusan_types_each_have_their_own_staged_template(): void
    {
        $admin = $this->user(['tsu.templat.lihat']);
        $res = $this->actingAs($admin)->getJson('/api/v1/tsu/templat')->assertOk();
        $data = collect($res->json('data'));
        $this->assertEqualsCanonicalizing(array_keys(Lalai::JENIS_URUSAN), $data->pluck('jenis_urusan')->all());
        $this->assertCount(7, $data);
        // Distinct stages, mandatory documents and cost categories per template (acceptance #1).
        $signatures = $data->map(fn ($t) => json_encode(array_map(fn ($p) => [$p['kod'], $p['senarai_dokumen_wajib'], $p['senarai_kategori_kos']], $t['peringkat'])))->unique();
        $this->assertCount(7, $signatures);
        $pelupusan = $data->firstWhere('jenis_urusan', 'pelupusan');
        $this->assertContains('laporan_penilaian', collect($pelupusan['peringkat'])->flatMap(fn ($p) => $p['senarai_dokumen_wajib'])->all());
        $this->assertSame('tsu.kelulusan_pelupusan', $pelupusan['aliran_kelulusan']);
    }

    public function test_SRS_TSU_02_admin_versions_a_template_without_code_and_running_urusan_keep_their_stages(): void
    {
        $officer = $this->user(self::PEGAWAI);
        $running = $this->daftar($officer, 'tukar_syarat', [$this->lot()]);
        $before = UrusanPeringkat::query()->where('urusan_id', $running->id)->count();

        $admin = $this->user(['tsu.templat.lihat', 'tsu.templat.kemas_kini']);
        $this->actingAs($this->user(['tsu.templat.lihat']))->postJson('/api/v1/tsu/templat/tukar_syarat', [])->assertForbidden();
        $this->actingAs($admin)->postJson('/api/v1/tsu/templat/tukar_syarat', [
            'nama' => 'Tukar syarat (dipermudah)', 'peringkat' => [
                ['kod' => 'dokumen', 'nama_peringkat' => 'Dokumen', 'pihak_bertanggungjawab' => 'mais', 'tempoh_sasaran_hari' => 10, 'senarai_dokumen_wajib' => ['borang_permohonan'], 'senarai_kategori_kos' => ['fi_permohonan']],
                ['kod' => 'ptd', 'nama_peringkat' => 'Keputusan PTD', 'pihak_bertanggungjawab' => 'pejabat_tanah', 'tempoh_sasaran_hari' => 60, 'perlu_kelulusan' => false, 'kesan' => 'kemas_kini_syarat'],
            ],
        ])->assertCreated()->assertJsonPath('data.versi', 2);
        $this->actingAs($admin)->postJson('/api/v1/tsu/templat/tukar_syarat', ['nama' => 'X', 'peringkat' => [['kod' => 'a', 'nama_peringkat' => 'A', 'pihak_bertanggungjawab' => 'entah', 'tempoh_sasaran_hari' => 1]]])->assertUnprocessable();

        $this->assertSame(1, TemplatProses::query()->where('jenis_urusan', 'tukar_syarat')->where('status_aktif', true)->count());
        $this->assertSame($before, UrusanPeringkat::query()->where('urusan_id', $running->id)->count());
        $new = $this->daftar($officer, 'tukar_syarat', [$this->lot()]);
        $this->assertSame(['dokumen', 'ptd'], UrusanPeringkat::query()->where('urusan_id', $new->id)->orderBy('no_urutan')->pluck('kod')->all());
        $this->assertSame(70, (int) $new->tarikh_mula->diffInDays($new->tarikh_sasaran_siap));
    }

    public function test_SRS_TSU_03_registration_generates_a_stage_timeline_with_party_target_and_current_stage(): void
    {
        $officer = $this->user(self::PEGAWAI, ['1008']);
        $u = $this->daftar($officer, 'pecah_sempadan', [$lot = $this->lot()], ['tarikh_mula' => '2026-01-05']);
        $this->assertMatchesRegularExpression('#^TSU/PS/\d{4}/\d{5}$#', $u->no_rujukan_mais);
        $this->assertSame(['1008', '100803'], [$u->kod_daerah, $u->kod_mukim]);
        $this->assertSame('asal', DB::table('urusan_lot')->where('urusan_id', $u->id)->value('peranan_lot'));

        $res = $this->actingAs($officer)->getJson('/api/v1/tsu/urusan/'.$u->id)->assertOk();
        $stages = collect($res->json('data.peringkat'));
        $this->assertGreaterThan(5, $stages->count());
        $first = $stages->first();
        $this->assertTrue($first['semasa']);
        $this->assertSame('aktif', $first['status']);
        $this->assertSame('2026-01-19', $first['tarikh_sasaran']);
        $this->assertTrue($first['lewat'], 'melepasi tarikh sasaran');
        $this->assertSame(['MAIS'], $stages->where('kod', 'penyediaan_dokumen')->pluck('pihak_nama')->all());
        $this->assertContains('Jurukur berlesen', $stages->pluck('pihak_nama')->all());
        $this->assertSame(1, $stages->where('semasa', true)->count());
        $this->assertSame('belum_mula', $stages[1]['status']);
        $this->assertNotEmpty($res->json('data.dokumen_belum_lengkap'));
        $this->assertSame($lot, $res->json('data.lot.0.id'));
    }

    public function test_SRS_TSU_03_stage_cannot_close_until_mandatory_documents_are_attached(): void
    {
        $officer = $this->user(self::PEGAWAI);
        $u = $this->daftar($officer, 'tukar_syarat', [$this->lot()]);
        $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/peringkat/selesai')->assertUnprocessable()->assertJsonValidationErrors('dokumen');
        $this->dokumen($officer, $u, 'borang_permohonan')->assertCreated();
        $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/peringkat/selesai')->assertUnprocessable();
        $this->dokumen($officer, $u, 'pelan_permulaan')->assertCreated();
        $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/peringkat/selesai')->assertOk();
        $this->assertSame('kelulusan_mais', $this->semasa($u)->kod);
        $this->assertSame('aktif', $this->semasa($u)->status);
        $this->assertNotNull($this->semasa($u)->tarikh_sasaran);
    }

    public function test_SRS_TSU_03_internal_approval_stage_goes_to_wfe_with_mfa_flow_and_advances_on_approval(): void
    {
        $officer = $this->user(self::PEGAWAI);
        $kb = $this->user(['wfe.tugasan.lulus']);
        $u = $this->daftar($officer, 'tukar_syarat', [$this->lot()]);
        $this->selesaikan($u, $officer, $kb);
        $stage = $this->semasa($u);
        $res = $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/peringkat/selesai')->assertOk()->assertJsonPath('data.status', 'menunggu_kelulusan');
        $started = $this->workflow->of('tsu.kelulusan_urusan');
        $this->assertCount(1, $started);
        $this->assertSame(['urusan_peringkat', (string) $stage->id], [$started[0]['subjectType'], $started[0]['subjectId']]);
        $this->assertSame('menunggu_kelulusan', $u->refresh()->status);

        $instance = $res->json('data.id_aliran_kerja');
        $this->step($instance, 'tsu.kelulusan_urusan', 'kelulusan_ketua_bahagian', 'lulus', $kb->id, 'Sokong');
        $this->complete('tsu.kelulusan_urusan', 'urusan_peringkat', (string) $stage->id, 'lulus', $kb->id, 'Sokong', $instance);
        $this->assertSame('selesai', $stage->refresh()->status);
        $this->assertSame('permohonan_etanah', $this->semasa($u)->kod);
        $k = DB::table('urusan_keputusan')->where('urusan_id', $u->id)->where('kod_peringkat_wfe', 'kelulusan_ketua_bahagian')->first();
        $this->assertTrue((bool) $k->indikator_mfa);
        $this->assertSame('Sokong', $k->justifikasi);
        $this->assertNotEmpty($this->notifier->of('tsu.keputusan_kelulusan'));
    }

    public function test_SRS_TSU_03_rejection_stops_the_urusan(): void
    {
        $officer = $this->user(self::PEGAWAI);
        $u = $this->daftar($officer, 'pecah_bahagian', [$this->lot()]);
        $this->selesaikan($u, $officer, $officer);
        $stage = $this->semasa($u);
        $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/peringkat/selesai')->assertOk();
        $this->complete('tsu.kelulusan_urusan', 'urusan_peringkat', (string) $stage->id, 'tolak', $this->user([])->id, 'Tidak lengkap');
        $this->assertSame('ditolak', $stage->refresh()->status);
        $this->assertSame('ditolak', $u->refresh()->status);
        $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/peringkat/selesai')->assertStatus(409);
    }

    public function test_SRS_TSU_03_overdue_stage_escalates_to_officer_then_head_of_division(): void
    {
        $officer = $this->user(self::PEGAWAI);
        $ketua = $this->user([]);
        $this->directory->supervisors[$officer->id] = [$ketua->id];
        $u = $this->daftar($officer, 'tukar_syarat', [$this->lot()], ['tarikh_mula' => today()->subDays(16)->toDateString()]);

        $this->artisan('tsu:semak-tempoh', ['--tanpa-ptd' => true])->assertSuccessful();
        $this->assertCount(1, $this->notifier->of('tsu.peringkat_lewat'));
        $esk = $this->notifier->of('tsu.peringkat_eskalasi');
        $this->assertCount(0, $esk, '2 hari lewat sahaja');

        UrusanPeringkat::query()->where('urusan_id', $u->id)->where('no_urutan', 1)->update(['tarikh_sasaran' => today()->subDays(10)]);
        $this->artisan('tsu:semak-tempoh', ['--tanpa-ptd' => true])->assertSuccessful();
        $esk = $this->notifier->of('tsu.peringkat_eskalasi');
        $this->assertCount(1, $esk);
        $this->assertEqualsCanonicalizing([$officer->id, $ketua->id], $esk[0]['to']);
        $this->artisan('tsu:semak-tempoh', ['--tanpa-ptd' => true])->assertSuccessful();
        $this->assertCount(1, $this->notifier->of('tsu.peringkat_eskalasi'), 'tidak berulang');
    }

    public function test_SRS_GEN_01_endpoints_require_permission_and_respect_district_scope(): void
    {
        $officer = $this->user(self::PEGAWAI, ['1008']);
        $u = $this->daftar($officer, 'tukar_syarat', [$this->lot()]);
        $sepang = $this->user(self::PEGAWAI, ['1009']);
        $none = $this->user([]);

        $this->actingAs($none)->getJson('/api/v1/tsu/urusan')->assertForbidden();
        $this->actingAs($none)->postJson('/api/v1/tsu/urusan', ['jenis_urusan' => 'tukar_syarat', 'lot_ids' => [$this->lot()]])->assertForbidden();
        $this->actingAs($sepang)->getJson('/api/v1/tsu/urusan/'.$u->id)->assertNotFound();
        $this->assertSame(0, $this->actingAs($sepang)->getJson('/api/v1/tsu/urusan')->assertOk()->json('meta.total'));
        $this->actingAs($sepang)->postJson('/api/v1/tsu/urusan/'.$u->id.'/peringkat/selesai')->assertNotFound();
        // Out-of-scope lot cannot be used to register an urusan.
        $this->actingAs($sepang)->postJson('/api/v1/tsu/urusan', ['jenis_urusan' => 'tukar_syarat', 'lot_ids' => [$this->lot()]])->assertUnprocessable()->assertJsonValidationErrors('lot_ids');
        $this->assertSame(1, UrusanTanah::query()->withoutGlobalScope('access')->count());
    }

    public function test_SRS_TSU_01_register_validation_rules(): void
    {
        $officer = $this->user(self::PEGAWAI);
        $lot = $this->lot();
        $this->actingAs($officer)->postJson('/api/v1/tsu/urusan', ['jenis_urusan' => 'penyatuan', 'lot_ids' => [$lot]])->assertUnprocessable()->assertJsonValidationErrors('lot_ids');
        $this->actingAs($officer)->postJson('/api/v1/tsu/urusan', ['jenis_urusan' => 'pelupusan', 'lot_ids' => [$lot]])->assertUnprocessable()->assertJsonValidationErrors('kaedah_pelupusan');
        $this->daftar($officer, 'tukar_syarat', [$lot]);
        $this->actingAs($officer)->postJson('/api/v1/tsu/urusan', ['jenis_urusan' => 'tukar_syarat', 'lot_ids' => [$lot]])->assertUnprocessable();
        $this->daftar($officer, 'penyatuan', [$lot, $this->lot()]);
    }
}
