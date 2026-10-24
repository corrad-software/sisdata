<?php

namespace Tests\Modules\Tsu;

use App\Modules\Hkm\Contracts\TitleChangeProposals;
use App\Modules\Hkm\Contracts\TitleRegistry;
use App\Modules\Tsu\Integrasi\PejabatTanahMock;
use App\Modules\Tsu\Models\KosUrusan;
use App\Modules\Tsu\Models\UrusanDokumen;
use App\Modules\Tsu\Models\UrusanPeringkat;
use App\Modules\Tsu\Profile\UrusanTanahSection;
use App\Modules\Tsu\Reminders\BayaranHampirTempoh;
use App\Modules\Tsu\Reminders\DokumenHampirLuput;
use App\Modules\Tsu\Reminders\PeringkatHampirSasaran;
use App\Modules\Tsu\Reminders\SusulanPtd;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Modules\Tsu\Support\FakeTitles;

class TukarSyaratKosPtdTest extends TsuTestCase
{
    public function test_SRS_TSU_07_change_of_conditions_end_to_end_updates_lot_and_proposes_new_title(): void
    {
        $titles = new FakeTitles;
        $this->app->instance(TitleRegistry::class, $titles);
        $this->app->instance(TitleChangeProposals::class, $titles);
        $officer = $this->user(self::PEGAWAI, ['1008']);
        $kb = $this->user(['wfe.tugasan.lulus']);
        $lot = $this->lot();
        $titles->titles[$lot] = [['id' => 't-9', 'no_hak_milik' => 'PM 77', 'jenis_hak_milik' => 'PM', 'status_semasa' => 'aktif', 'tarikh_tamat_tempoh' => null]];
        $u = $this->daftar($officer, 'tukar_syarat', [$lot], ['butiran' => ['kategori_guna_tanah_baharu' => 'BANGUNAN', 'syarat_nyata_baharu' => 'Bangunan institusi agama', 'no_hak_milik_baharu' => 'PM 1077']]);

        $this->sampaiKe($u, 'permohonan_etanah', $officer, $kb);
        $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/peringkat/selesai')->assertUnprocessable(); // e-Tanah ref missing
        $this->selesaikan($u, $officer, $kb);
        $this->assertNotNull($u->refresh()->no_rujukan_etanah);
        $this->assertSame('penghantaran_ptd', $this->semasa($u)->kod);
        // Daerah 1008 is covered by the central API: submit through it.
        $ref = $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/ptd/hantar')->assertOk()->json('data.no_rujukan_ptd');
        $this->assertStringStartsWith('PTD/1008/', $ref);
        $this->assertSame('diterima', $u->refresh()->status_ptd);
        $this->sampaiKe($u, 'bayaran_premium', $officer, $kb);

        $fin = $this->user(self::KEWANGAN);
        $kos = $this->actingAs($fin)->postJson('/api/v1/tsu/urusan/'.$u->id.'/kos', ['kategori_kos' => 'premium', 'amaun' => '45210.35', 'no_invois' => 'NB-1', 'tarikh_akhir_bayaran' => today()->addDays(30)->toDateString()])->assertCreated()->json('data.id');
        $this->assertSame(UrusanPeringkat::query()->where('urusan_id', $u->id)->where('kod', 'bayaran_premium')->value('id'), KosUrusan::query()->find($kos)->peringkat_id);
        $this->dokumen($officer, $u, 'notis_bayaran')->assertCreated();
        $this->dokumen($officer, $u, 'resit')->assertCreated();
        $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/peringkat/selesai')->assertUnprocessable(); // premium unpaid
        $this->actingAs($fin)->putJson('/api/v1/tsu/kos/'.$kos, ['status_bayaran' => 'dibayar', 'tarikh_bayaran' => today()->toDateString(), 'no_resit' => 'RS-88'])->assertOk();
        $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/peringkat/selesai')->assertOk();

        $this->assertSame('pendaftaran_hak_milik', $this->semasa($u)->kod);
        $this->selesaikan($u, $officer, $kb);
        $this->assertSame('selesai', $u->refresh()->status);
        $row = DB::table('lot_tanah')->where('id', $lot)->first();
        $this->assertSame(['BANGUNAN', 'Bangunan institusi agama'], [$row->kategori_guna_tanah, $row->syarat_nyata]);
        $this->assertSame(2, (int) $row->versi_rekod);
        $this->assertCount(1, $titles->proposals);
        $this->assertSame(['butiran', ['kategori_tanah' => 'BANGUNAN', 'syarat_nyata' => 'Bangunan institusi agama', 'no_hak_milik' => 'PM 1077']], [$titles->proposals[0]['jenis_perubahan'], $titles->proposals[0]['data']]);
    }

    public function test_SRS_TSU_14_costs_by_category_with_totals_payment_status_and_receipt_only_by_finance(): void
    {
        $officer = $this->user(self::PEGAWAI);
        $fin = $this->user(self::KEWANGAN);
        $u = $this->daftar($officer, 'pecah_sempadan', [$this->lot()]);

        $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/kos', ['kategori_kos' => 'premium', 'amaun' => '10'])->assertForbidden();
        $this->actingAs($fin)->postJson('/api/v1/tsu/urusan/'.$u->id.'/kos', ['kategori_kos' => 'bukan', 'amaun' => '10'])->assertUnprocessable();
        $this->actingAs($fin)->postJson('/api/v1/tsu/urusan/'.$u->id.'/kos', ['kategori_kos' => 'premium', 'amaun' => '10.999'])->assertUnprocessable();
        $ids = [];
        foreach ([['fi_permohonan', '0.10'], ['premium', '0.20'], ['upah_ukur', '12000.35'], ['fi_pelan', '150.00'], ['fi_pendaftaran', '99.99'], ['cukai_tambahan', '1.01'], ['kos_lain', '5']] as [$k, $a]) {
            $ids[$k] = $this->actingAs($fin)->postJson('/api/v1/tsu/urusan/'.$u->id.'/kos', ['kategori_kos' => $k, 'amaun' => $a])->assertCreated()->json('data.id');
        }
        $this->actingAs($fin)->putJson('/api/v1/tsu/kos/'.$ids['premium'], ['status_bayaran' => 'dibayar'])->assertUnprocessable()->assertJsonValidationErrors('no_resit');
        $this->dokumen($officer, $u, 'resit')->assertCreated();
        $doc = UrusanDokumen::query()->where('urusan_id', $u->id)->value('id_dokumen_dms');
        $this->actingAs($fin)->putJson('/api/v1/tsu/kos/'.$ids['premium'], ['status_bayaran' => 'dibayar', 'no_resit' => 'R-1', 'tarikh_bayaran' => today()->toDateString(), 'id_dokumen' => $doc])->assertOk()->assertJsonPath('data.id_dokumen', $doc);
        $this->actingAs($fin)->putJson('/api/v1/tsu/kos/'.$ids['premium'], ['amaun' => '1.00'])->assertUnprocessable();
        $this->actingAs($officer)->putJson('/api/v1/tsu/kos/'.$ids['fi_pelan'], ['amaun' => '1.00'])->assertForbidden();

        $sum = $this->actingAs($officer)->getJson('/api/v1/tsu/urusan/'.$u->id.'/kos')->assertOk()->json('data.ringkasan');
        $this->assertSame('12256.65', $sum['jumlah']);
        $this->assertSame('0.20', $sum['dibayar']);
        $this->assertSame('12256.45', $sum['belum_dibayar']);
        $this->assertCount(7, $sum['mengikut_kategori']);
        $this->assertAudited('kemas_kini', ['nama_entiti' => 'kos_urusan', 'id_rekod' => $ids['premium']]);
        $this->assertSame(UrusanPeringkat::query()->where('urusan_id', $u->id)->where('kod', 'kerja_ukur')->value('id'), KosUrusan::query()->find($ids['upah_ukur'])->peringkat_id, 'kos dipaut ke peringkat mengikut kategori');
    }

    public function test_SRS_TSU_15_urusan_costs_feed_the_lots_cumulative_cost_series(): void
    {
        $officer = $this->user(self::PEGAWAI);
        $fin = $this->user(self::KEWANGAN);
        [$a, $b] = [$this->lot(), $this->lot()];
        $u = $this->daftar($officer, 'penyatuan', [$a, $b]);
        $this->actingAs($fin)->postJson('/api/v1/tsu/urusan/'.$u->id.'/kos', ['kategori_kos' => 'premium', 'amaun' => '1000.00'])->assertCreated();
        $this->actingAs($fin)->postJson('/api/v1/tsu/urusan/'.$u->id.'/kos', ['kategori_kos' => 'fi_pelan', 'amaun' => '201.00'])->assertCreated();
        $series = app(UrusanTanahSection::class)->financialSeries($a, $officer);
        $this->assertCount(2, $series);
        $this->assertSame([UrusanTanahSection::JENIS_KOS], array_values(array_unique(array_column($series, 'jenis'))));
        $this->assertEqualsWithDelta(600.50, array_sum(array_column($series, 'amaun')), 0.001, 'dikongsi sama rata antara dua lot asal');
        $this->assertSame([], app(UrusanTanahSection::class)->financialSeries($a, $this->user(['tsu.urusan.lihat'])));
        // Also visible through the Profil 360° API of MOD-PRO.
        $p = $this->actingAs($this->user([...self::PEGAWAI, 'pro.lot.lihat']))->getJson('/api/v1/pro/lot/'.$a.'/profil')->assertOk();
        $this->assertTrue($p->json('data.kewangan.tersedia'));
    }

    public function test_SRS_TSU_22_land_office_status_by_central_api_or_structured_manual_update_with_follow_up(): void
    {
        $officer = $this->user(self::PEGAWAI);
        // Covered district (1008): submit + scheduled sync.
        $u = $this->daftar($officer, 'hak_milik_kekal', [$this->lot()]);
        $ref = $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/ptd/hantar')->assertOk()->json('data.no_rujukan_ptd');
        $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/ptd/manual', ['status_ptd' => 'lulus', 'tarikh_status' => today()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('status_ptd');
        $this->travel(32)->days();
        $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/ptd/segerak')->assertOk()->assertJsonPath('data.berubah', true)->assertJsonPath('data.status_ptd', 'lawatan_tapak');
        $this->assertSame(['api', 'api'], DB::table('kemaskini_status_ptd')->where('urusan_id', $u->id)->where('rujukan_ptd', $ref)->orderBy('id')->limit(2)->pluck('sumber')->all());
        $this->assertNotEmpty($this->notifier->of('tsu.status_ptd_berubah'));
        $this->assertDatabaseHas('adm_log_integrasi', ['kod_integrasi' => 'tsu_pejabat_tanah', 'berjaya' => true]);
        // Provider outage → manual mode allowed.
        app(PejabatTanahMock::class)->setDown(true);
        $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/ptd/manual', ['status_ptd' => 'ulasan_jabatan', 'tarikh_status' => today()->toDateString()])->assertCreated();
        app(PejabatTanahMock::class)->setDown(false);

        // District outside coverage (1009): manual only, with automatic follow-up reminder.
        $s = $this->daftar($officer, 'hak_milik_kekal', [$this->lot('100901')]);
        $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$s->id.'/ptd/hantar')->assertUnprocessable();
        $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$s->id.'/ptd/segerak')->assertUnprocessable();
        $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$s->id.'/ptd/manual', ['status_ptd' => 'bogus', 'tarikh_status' => today()->toDateString()])->assertUnprocessable();
        $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$s->id.'/ptd/manual', ['status_ptd' => 'dalam_semakan', 'tarikh_status' => today()->toDateString(), 'rujukan_ptd' => 'PTDSPG/12/2026', 'catatan' => 'Disahkan melalui telefon'])->assertCreated()->assertJsonPath('data.tarikh_susulan', today()->addDays(14)->toDateString());
        $this->assertSame('PTDSPG/12/2026', $s->refresh()->no_rujukan_ptd);
        $due = iterator_to_array(app(SusulanPtd::class)->due(1, CarbonImmutable::today()->addDays(13)));
        $this->assertCount(2, $due, 'urusan manual semasa gangguan API + urusan di luar liputan');
        $this->assertContains('urusan_tanah:susulan:'.$s->id.':'.today()->addDays(14)->format('Ymd'), array_map(fn ($r) => $r->key, $due));
        $this->assertSame($officer->id, $due[0]->recipients[0]->id);
        $show = $this->actingAs($officer)->getJson('/api/v1/tsu/urusan/'.$s->id)->assertOk();
        $this->assertFalse($show->json('data.ptd.liputan_api'));
        $this->assertSame('manual', $show->json('data.ptd.sejarah.0.sumber'));
    }

    public function test_SRS_TSU_23_follow_up_reminders_for_payment_document_expiry_and_stage_target(): void
    {
        $officer = $this->user(self::PEGAWAI);
        $fin = $this->user(self::KEWANGAN);
        $u = $this->daftar($officer, 'tukar_syarat', [$this->lot()]);
        $this->actingAs($fin)->postJson('/api/v1/tsu/urusan/'.$u->id.'/kos', ['kategori_kos' => 'premium', 'amaun' => '5000', 'tarikh_akhir_bayaran' => today()->addDays(7)->toDateString()])->assertCreated();
        $this->dokumen($officer, $u, 'surat_kelulusan', ['tarikh_dokumen' => today()->toDateString(), 'tarikh_luput' => today()->addDays(30)->toDateString()])->assertCreated();
        $today = CarbonImmutable::today();

        $pay = iterator_to_array(app(BayaranHampirTempoh::class)->due(7, $today));
        $this->assertCount(1, $pay);
        $this->assertSame('Premium', $pay[0]->data['kategori']);
        $this->assertSame([], iterator_to_array(app(BayaranHampirTempoh::class)->due(3, $today)));
        $doc = iterator_to_array(app(DokumenHampirLuput::class)->due(30, $today));
        $this->assertCount(1, $doc);
        $this->assertSame('Surat kelulusan', $doc[0]->data['dokumen']);
        $stage = iterator_to_array(app(PeringkatHampirSasaran::class)->due(7, $today->addDays(7)));
        $this->assertCount(1, $stage);
        $this->assertSame('Penyediaan pelan dan dokumen', $stage[0]->data['peringkat']);
        $this->assertStringContainsString('/admin/tsu/urusan/'.$u->id, (string) $stage[0]->link);
    }
}
