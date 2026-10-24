<?php

namespace Tests\Modules\Tsu;

use App\Models\User;
use App\Modules\Hkm\Contracts\TitleChangeProposals;
use App\Modules\Hkm\Contracts\TitleRegistry;
use App\Modules\Tsu\Models\UrusanKeputusan;
use App\Modules\Tsu\Models\UrusanTanah;
use App\Modules\Tsu\Support\AliranTsu;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Modules\Tsu\Support\FakeTitles;

class PelupusanTest extends TsuTestCase
{
    private FakeTitles $titles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->titles = new FakeTitles;
        $this->app->instance(TitleRegistry::class, $this->titles);
        $this->app->instance(TitleChangeProposals::class, $this->titles);
    }

    private function sediaPelupusan(User $officer, string $bahagian = 'hartanah'): UrusanTanah
    {
        $lot = $this->lot(bahagian: $bahagian);
        $this->titles->titles[$lot] = [['id' => 't-1', 'no_hak_milik' => 'GRN 1234', 'jenis_hak_milik' => 'GRN', 'status_semasa' => 'aktif', 'tarikh_tamat_tempoh' => null]];

        return $this->daftar($officer, 'pelupusan', [$lot], ['kaedah_pelupusan' => 'tukaran', 'justifikasi' => 'Tanah terpencil, tidak ekonomik; ditukar dengan tanah lebih strategik.', 'butiran' => ['pihak_penerima' => 'Agensi negeri']]);
    }

    public function test_SRS_TSU_04_full_disposal_process_from_application_to_archived_lot(): void
    {
        $officer = $this->user(self::PEGAWAI);
        $top = $this->user(['wfe.tugasan.lulus']);
        $u = $this->sediaPelupusan($officer);
        $this->assertSame('sulit', $u->klasifikasi, 'pelupusan Sulit');

        $kods = [];
        for ($i = 0; $i < 10 && $u->refresh()->terbuka(); $i++) {
            $kods[] = $this->semasa($u)->kod;
            if ($this->semasa($u)->kod === 'invois_bayaran') {
                $this->actingAs($this->user(self::KEWANGAN))->postJson('/api/v1/tsu/urusan/'.$u->id.'/kos', ['kategori_kos' => 'fi_pendaftaran', 'amaun' => '1500.50', 'no_invois' => 'INV-9', 'tarikh_akhir_bayaran' => today()->addDays(14)->toDateString()])->assertCreated();
            }
            $this->selesaikan($u, $officer, $top);
        }
        $this->assertSame(['permohonan_dokumen', 'lawatan_tapak', 'laporan_jpph', 'kelulusan_pelupusan', 'permohonan_ptd', 'invois_bayaran', 'pendaftaran'], $kods);
        $this->assertSame('selesai', $u->refresh()->status);
        $lot = DB::table('urusan_lot')->where('urusan_id', $u->id)->value('lot_id');
        $this->assertSame('arkib', DB::table('lot_tanah')->where('id', $lot)->value('status_lot'), 'SRS-TSU-06');
        $this->assertSame([['jenis_perubahan' => 'status', 'status_selepas' => 'dilupuskan', 'no_hak_milik' => 'GRN 1234']], array_map(fn ($p) => ['jenis_perubahan' => $p['jenis_perubahan'], 'status_selepas' => $p['data']['status_selepas'], 'no_hak_milik' => $p['no_hak_milik']], $this->titles->proposals));
        $this->assertSame('850000.00', (string) $u->nilai_penilaian);
        $this->assertDatabaseHas('urusan_keputusan', ['urusan_id' => $u->id, 'jenis_keputusan' => 'lulus', 'konteks' => 'urusan', 'kod_peringkat_wfe' => null]);
        // Documents of each stage are in DMS with the mapped category (SRS-TSU-25).
        $cats = collect($this->documents->docs)->where('owner_type', 'urusan_tanah')->pluck('category')->unique()->values()->all();
        $this->assertContains('laporan', $cats);
        $this->assertContains('gambar_tapak', $cats);
        $this->assertSame('rahsia', collect($this->documents->docs)->firstWhere('metadata.kategori_tsu', 'laporan_penilaian')['classification']);
    }

    public function test_SRS_TSU_05_disposal_approval_needs_justification_and_current_valuation_before_it_starts(): void
    {
        $officer = $this->user(self::PEGAWAI);
        $u = $this->sediaPelupusan($officer);
        $this->sampaiKe($u, 'laporan_jpph', $officer, $officer);
        // Valuation stage cannot close without the report + value.
        $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/peringkat/selesai')->assertUnprocessable();
        $this->dokumen($officer, $u, 'laporan_penilaian', ['tarikh_dokumen' => today()->subMonths(14)->toDateString(), 'nilai_penilaian' => '500000'])->assertCreated();
        $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/peringkat/selesai')->assertUnprocessable()->assertJsonValidationErrors('peringkat'); // expired (12 months)
        $this->dokumen($officer, $u, 'laporan_penilaian', ['tarikh_dokumen' => today()->subMonth()->toDateString(), 'nilai_penilaian' => '520000'])->assertCreated();
        $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/peringkat/selesai')->assertOk();
        $this->assertSame('kelulusan_pelupusan', $this->semasa($u)->kod);

        $u->forceFill(['justifikasi' => null])->save();
        $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/peringkat/selesai')->assertUnprocessable()->assertJsonValidationErrors('justifikasi');
        $this->assertSame([], $this->workflow->of(AliranTsu::PELUPUSAN));
        $this->actingAs($officer)->putJson('/api/v1/tsu/urusan/'.$u->id, ['justifikasi' => 'Pertukaran (istibdal) dengan tanah yang lebih bermanfaat.'])->assertOk();
        $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/peringkat/selesai')->assertOk()->assertJsonPath('data.status', 'menunggu_kelulusan');
        $this->assertCount(1, $this->workflow->of(AliranTsu::PELUPUSAN));
    }

    public function test_SRS_TSU_05_disposal_approval_is_not_recorded_without_mfa_stage_and_written_justification(): void
    {
        $officer = $this->user(self::PEGAWAI);
        $top = $this->user(['wfe.tugasan.lulus']);
        $u = $this->sediaPelupusan($officer, 'wakaf');
        $this->sampaiKe($u, 'kelulusan_pelupusan', $officer, $officer);
        $stage = $this->semasa($u);
        $instance = $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/peringkat/selesai')->assertOk()->json('data.id_aliran_kerja');
        $ctx = $this->workflow->of(AliranTsu::PELUPUSAN)[0]['context'];
        $this->assertSame('wakaf', $ctx['bahagian_pemilik'], 'tanah wakaf melalui semakan Bahagian Wakaf');

        // (a) approved only at a non-MFA stage → not recorded.
        $this->step($instance, AliranTsu::PELUPUSAN, 'semakan_ketua_bahagian', 'lulus', $top->id, 'OK');
        $this->complete(AliranTsu::PELUPUSAN, AliranTsu::SUBJEK_PERINGKAT, (string) $stage->id, 'lulus', $top->id, 'OK', $instance);
        $this->assertSame('aktif', $stage->refresh()->status);
        $this->assertSame('tidak_sah', UrusanKeputusan::query()->where('urusan_id', $u->id)->latest('id')->value('jenis_keputusan'));

        // (b) highest stage without written justification → not recorded.
        $instance = $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/peringkat/selesai')->assertOk()->json('data.id_aliran_kerja');
        $this->step($instance, AliranTsu::PELUPUSAN, AliranTsu::PERINGKAT_TERTINGGI, 'lulus', $top->id, null);
        $this->complete(AliranTsu::PELUPUSAN, AliranTsu::SUBJEK_PERINGKAT, (string) $stage->id, 'lulus', $top->id, null, $instance);
        $this->assertSame('aktif', $stage->refresh()->status);

        // (c) highest stage (MFA in WFE) + justification + current valuation → recorded.
        $instance = $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/peringkat/selesai')->assertOk()->json('data.id_aliran_kerja');
        $this->step($instance, AliranTsu::PELUPUSAN, AliranTsu::PERINGKAT_TERTINGGI, 'lulus', $top->id, 'Diluluskan Majlis — istibdal memenuhi syarat.');
        $this->complete(AliranTsu::PELUPUSAN, AliranTsu::SUBJEK_PERINGKAT, (string) $stage->id, 'lulus', $top->id, 'Diluluskan Majlis.', $instance);
        $this->assertSame('selesai', $stage->refresh()->status);
        $rec = UrusanKeputusan::query()->where('urusan_id', $u->id)->where('jenis_keputusan', 'lulus')->whereNull('kod_peringkat_wfe')->sole();
        $this->assertTrue($rec->indikator_mfa);
        $this->assertNotNull($rec->id_dokumen_kelulusan, 'pautan laporan penilaian');
    }

    public function test_SRS_TSU_06_disposed_lot_is_archived_and_cannot_be_deleted(): void
    {
        $officer = $this->user(self::PEGAWAI);
        $u = $this->sediaPelupusan($officer);
        for ($i = 0; $i < 10 && $u->refresh()->terbuka(); $i++) {
            $this->selesaikan($u, $officer, $this->user([]));
        }
        $lot = DB::table('urusan_lot')->where('urusan_id', $u->id)->value('lot_id');
        $this->assertSame('arkib', DB::table('lot_tanah')->where('id', $lot)->value('status_lot'));
        $this->assertNotNull(DB::table('lot_tanah')->where('id', $lot)->value('diarkib_pada'));
        try {
            DB::transaction(fn () => DB::table('lot_tanah')->where('id', $lot)->update(['deleted_at' => now()]));
            $this->fail('Lot dilupuskan dipadam');
        } catch (QueryException $e) {
            $this->assertStringContainsString('tidak boleh dipadam', $e->getMessage());
        }
        try {
            DB::transaction(fn () => DB::table('urusan_tanah')->where('id', $u->id)->delete());
            $this->fail('Urusan dipadam');
        } catch (QueryException) {
        }
        $this->assertNull(DB::table('lot_tanah')->where('id', $lot)->value('deleted_at'));
        $this->assertAudited('arkib', ['nama_entiti' => 'lot_tanah', 'id_rekod' => $lot, 'modul' => 'tsu']);
    }

    public function test_SRS_TSU_24_closed_urusan_and_its_decision_history_are_immutable(): void
    {
        $officer = $this->user(self::PEGAWAI);
        $u = $this->sediaPelupusan($officer);
        $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/tutup')->assertUnprocessable();
        for ($i = 0; $i < 10 && $u->refresh()->terbuka(); $i++) {
            $this->selesaikan($u, $officer, $this->user([]));
        }
        $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/tutup', ['catatan' => 'Selesai'])->assertOk()->assertJsonPath('data.dikunci', true);

        $this->actingAs($officer)->putJson('/api/v1/tsu/urusan/'.$u->id, ['tajuk' => 'Ubah'])->assertStatus(409);
        $this->dokumen($officer, $u, 'lain')->assertStatus(409);
        $this->actingAs($this->user(self::KEWANGAN))->postJson('/api/v1/tsu/urusan/'.$u->id.'/kos', ['kategori_kos' => 'kos_lain', 'amaun' => '1'])->assertStatus(409);
        foreach ([
            fn () => DB::table('urusan_tanah')->where('id', $u->id)->update(['tajuk' => 'x']),
            fn () => DB::table('urusan_keputusan')->where('urusan_id', $u->id)->update(['justifikasi' => 'x']),
            fn () => DB::table('urusan_keputusan')->where('urusan_id', $u->id)->delete(),
            fn () => DB::table('urusan_peringkat')->where('urusan_id', $u->id)->update(['catatan' => 'x']),
            fn () => DB::table('kos_urusan')->insert(['urusan_id' => $u->id, 'kategori_kos' => 'kos_lain', 'amaun' => 1, 'created_at' => now(), 'updated_at' => now()]),
        ] as $i => $write) {
            try {
                DB::transaction($write);
                $this->fail("Penulisan #{$i} dibenarkan selepas urusan ditutup");
            } catch (QueryException $e) {
                $this->assertStringContainsString('P0001', $e->getMessage());
            }
        }
        // Full decision history kept and readable.
        $res = $this->actingAs($officer)->getJson('/api/v1/tsu/urusan/'.$u->id)->assertOk();
        $this->assertContains('tutup', collect($res->json('data.keputusan'))->pluck('jenis_keputusan')->all());
        $this->assertGreaterThanOrEqual(7, count($res->json('data.dokumen')));
    }

    public function test_SRS_TSU_25_documents_go_through_dms_and_valuation_report_is_rahsia(): void
    {
        $officer = $this->user(['tsu.urusan.lihat', 'tsu.urusan.cipta', 'tsu.urusan.kemas_kini']);
        $u = $this->sediaPelupusan($officer);
        $this->dokumen($officer, $u, 'gambar_lawatan', ['tarikh_dokumen' => today()->toDateString()])->assertCreated()->assertJsonPath('data.kategori_nama', 'Gambar lawatan tapak');
        $this->dokumen($officer, $u, 'laporan_penilaian', ['nilai_penilaian' => '1', 'tarikh_dokumen' => today()->toDateString()])->assertForbidden();
        $doc = collect($this->documents->docs)->first();
        $this->assertSame(['urusan_tanah', $u->id, 'gambar_tapak', 'sulit'], [$doc['owner_type'], $doc['owner_id'], $doc['category'], $doc['classification']]);
        $id = DB::table('urusan_dokumen')->where('urusan_id', $u->id)->value('id');
        $this->actingAs($officer)->getJson('/api/v1/tsu/urusan/'.$u->id.'/dokumen/'.$id.'/url')->assertOk()->assertJsonStructure(['data' => ['url']]);
        // Valuation value is hidden from users without the Rahsia permission.
        $u->forceFill(['nilai_penilaian' => '700000'])->save();
        $this->actingAs($officer)->getJson('/api/v1/tsu/urusan/'.$u->id)->assertOk()->assertJsonPath('data.nilai_penilaian', null)->assertJsonPath('data.nilai_penilaian_tersembunyi', true);
        $this->actingAs($this->user([...self::PEGAWAI]))->getJson('/api/v1/tsu/urusan/'.$u->id)->assertOk()->assertJsonPath('data.nilai_penilaian', '700000.00');
        $this->assertAudited('lihat', ['nama_entiti' => 'urusan_tanah', 'id_rekod' => $u->id]);
    }
}
