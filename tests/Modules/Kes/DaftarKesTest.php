<?php

namespace Tests\Modules\Kes;

use App\Modules\Kes\Contracts\LegalCases;
use App\Modules\Kes\Models\KesMahkamah;
use App\Modules\Kes\Models\Pihak;
use App\Modules\Kes\Services\KesService;
use App\Modules\Pro\Models\LotTanah;
use App\Modules\Pro\Services\Profil360;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class DaftarKesTest extends KesTestCase
{
    public function test_SRS_KES_01_default_case_types_and_a_new_type_without_code_change(): void
    {
        $codes = DB::table('kes_jenis')->pluck('kod')->all();
        foreach (['sivil', 'jenayah', 'faraid', 'wakaf', 'sengketa_pemilikan', 'tuntutan_milikan', 'pencerobohan', 'kaveat', 'rayuan'] as $kod) {
            $this->assertContains($kod, $codes);
        }
        $admin = $this->user(KesTestCase::KETUA);
        $this->actingAs($admin)->postJson('/api/v1/kes/konfigurasi/jenis', ['kod' => 'tanah_rizab', 'nama' => 'Rizab tanah'])->assertCreated();
        $this->actingAs($admin)->postJson('/api/v1/kes/kes', $this->dataKes([$this->lot()], ['jenis' => 'tanah_rizab']))->assertCreated()->assertJsonPath('data.jenis_nama', 'Rizab tanah');

        // Without the configuration permission the type list cannot be changed.
        $this->actingAs($this->user())->postJson('/api/v1/kes/konfigurasi/jenis', ['kod' => 'x', 'nama' => 'X'])->assertForbidden();
    }

    public function test_SRS_KES_02_register_with_every_A_12_02_field(): void
    {
        $user = $this->user();
        $res = $this->actingAs($user)->postJson('/api/v1/kes/kes', $this->dataKes([$this->lot()]))->assertCreated();
        $id = $res->json('data.id');
        $k = KesMahkamah::query()->withoutGlobalScope('access')->findOrFail($id);
        $this->assertMatchesRegularExpression('#^KES/\d{4}/\d{5}$#', (string) $k->no_rujukan);
        $this->assertSame('Mahkamah Tinggi Shah Alam', $k->mahkamah);
        $this->assertSame('tinggi', $k->peringkat_mahkamah);
        $this->assertSame('250000.50', $k->nilai_tuntutan);
        $this->assertSame('pencerobohan', $k->jenis);
        $this->assertNotNull($k->tarikh_pemfailan);
        $this->assertSame('rahsia', $k->klasifikasi);
        $this->assertSame('didaftar', $k->status);

        $this->actingAs($user)->postJson('/api/v1/kes/kes', ['tajuk' => 'x'])->assertUnprocessable()->assertJsonValidationErrors(['jenis', 'mahkamah', 'peringkat_mahkamah', 'ringkasan_tuntutan', 'peranan_mais']);
    }

    public function test_SRS_KES_03_parties_by_role_encrypted_and_masked(): void
    {
        $k = $this->kes([$this->lot()]);
        $raw = DB::table('kes_pihak')->where('kes_mahkamah_id', $k->id)->pluck('nama_pihak')->implode(' ');
        $this->assertStringNotContainsString('Ali bin Abu', $raw, 'party names are encrypted at rest');
        $this->assertStringNotContainsString('800101', (string) DB::table('kes_pihak')->where('kes_mahkamah_id', $k->id)->pluck('no_pengenalan')->implode(' '));

        $officer = $this->user();
        $pihak = collect($this->actingAs($officer)->getJson('/api/v1/kes/kes/'.$k->id)->assertOk()->json('data.pihak'))->keyBy('peranan');
        $this->assertSame('Majlis Agama Islam Selangor', $pihak['plaintif']['nama_pihak']);
        $this->assertStringNotContainsString('Ali bin Abu', $pihak['defendan']['nama_pihak']);
        $this->assertTrue($pihak['defendan']['bertopeng']);
        $this->assertStringEndsWith('1234', $pihak['defendan']['no_pengenalan']);
        $this->assertSame('diwakili', $pihak['defendan']['status_wakilan']);

        // Full reveal needs kes.pihak.lihat and is logged with IP.
        $this->actingAs($officer)->getJson('/api/v1/kes/kes/'.$k->id.'/pihak/'.$pihak['defendan']['id'].'/dedah')->assertForbidden();
        $ketua = $this->user(KesTestCase::KETUA);
        $this->actingAs($ketua)->getJson('/api/v1/kes/kes/'.$k->id.'/pihak/'.$pihak['defendan']['id'].'/dedah')->assertOk()
            ->assertJsonPath('data.nama_pihak', 'Ali bin Abu')->assertJsonPath('data.no_pengenalan', '800101-10-1234');
        $this->assertDatabaseHas('kes_log_capaian', ['kes_mahkamah_id' => $k->id, 'id_pengguna' => $ketua->id, 'jenis_tindakan' => 'lihat_pihak']);
        $this->assertSame(Pihak::cincang('ali  BIN abu'), Pihak::query()->where('kes_mahkamah_id', $k->id)->where('peranan', 'defendan')->value('cincangan_nama'));
    }

    public function test_SRS_KES_04_counsel_per_party_with_firm_and_contact(): void
    {
        $k = $this->kes([$this->lot()]);
        $peguam = $k->peguam()->first();
        $this->assertNotNull($peguam);
        $this->assertSame('Tetuan Lawan & Co', $peguam->nama_firma);
        $this->assertSame($k->pihak()->where('peranan', 'defendan')->value('id'), $peguam->kes_pihak_id);
        $this->assertFalse($peguam->indikator_panel_mais);
        $this->assertStringNotContainsString('lawan@contoh.test', (string) DB::table('kes_peguam')->where('id', $peguam->id)->value('emel'));

        $this->actingAs($this->user())->postJson('/api/v1/kes/kes/'.$k->id.'/peguam', ['nama_peguam' => 'Puan Peguam MAIS', 'nama_firma' => 'Unit Guaman MAIS', 'pegawai_penghubung' => 'En. Hubung', 'no_telefon' => '03-5555 1111', 'emel' => 'guaman@mais.test', 'kes_pihak_id' => $k->pihak()->where('adalah_mais', true)->value('id')])
            ->assertCreated()->assertJsonPath('data.pegawai_penghubung', 'En. Hubung');
    }

    public function test_SRS_KES_05_one_case_three_lots_shown_on_each_profile(): void
    {
        $lots = [$this->lot(), $this->lot(), $this->lot('100901')];
        $k = $this->kes($lots);
        $this->assertSame(3, DB::table('kes_mahkamah_lot')->where('kes_mahkamah_id', $k->id)->count());
        $k2 = $this->kes([$lots[0]]);
        $this->assertSame(2, DB::table('kes_mahkamah_lot')->where('lot_id', $lots[0])->count(), 'one lot, many cases');

        $viewer = $this->user([...KesTestCase::PEGAWAI, 'pro.lot.lihat']);
        foreach ($lots as $lotId) {
            $profile = app(Profil360::class)->build(LotTanah::query()->withoutGlobalScope('access')->findOrFail($lotId), $viewer);
            $section = collect(collect($profile['tab'])->firstWhere('kod', 'risiko')['seksyen'])->firstWhere('key', 'kes_mahkamah');
            $this->assertSame('ada', $section['status']);
            $this->assertContains($k->no_rujukan, collect($section['items'])->map(fn ($i) => explode(' · ', $i['label'])[0])->all());
        }
        $this->assertCount(2, app(LegalCases::class)->forLot($lots[0], $viewer));
        $this->assertNotNull($k2->no_rujukan);
    }

    public function test_SRS_KES_05_14_profile_section_hidden_for_users_without_the_legal_grant(): void
    {
        $lot = $this->lot();
        $this->kes([$lot]);
        $plain = $this->user(['pro.lot.lihat', 'kes.kes.lihat']);
        $profile = app(Profil360::class)->build(LotTanah::query()->withoutGlobalScope('access')->findOrFail($lot), $plain);
        $keys = collect(collect($profile['tab'])->firstWhere('kod', 'risiko')['seksyen'])->pluck('key')->all();
        $this->assertNotContains('kes_mahkamah', $keys, 'Rahsia section absent — not even a placeholder');
    }

    public function test_SRS_KES_06_lots_flagged_while_active_and_restored_when_all_cases_finish(): void
    {
        $lots = [$this->lot(), $this->lot(), $this->lot()];
        $k = $this->kes($lots);
        $before = DB::table('lot_tanah')->whereIn('id', $lots)->pluck('status_lot', 'id')->all();
        $cases = app(LegalCases::class);
        $this->assertSame(array_fill_keys($lots, true), $cases->underLegalAction($lots));

        $other = $this->kes([$lots[0]]);
        $service = app(KesService::class);
        $by = $this->user(KesTestCase::KETUA);
        $service->tukarStatus($k, 'selesai', $by, 'Kes tamat');
        // The first lot is still under the second active case.
        $this->assertEquals([$lots[0] => true, $lots[1] => false, $lots[2] => false], $cases->underLegalAction($lots));
        $service->tukarStatus($other, 'selesai', $by);
        $this->assertSame(array_fill_keys($lots, false), $cases->underLegalAction($lots));
        $this->assertSame($before, DB::table('lot_tanah')->whereIn('id', $lots)->pluck('status_lot', 'id')->all(), 'MOD-PRO status_lot (status asal) never overwritten');
        $this->assertNotNull(DB::table('kes_status_lot')->where('lot_id', $lots[1])->value('tarikh_tamat'));
    }

    public function test_SRS_KES_07_staged_status_with_dated_history(): void
    {
        $k = $this->kes([$this->lot()]);
        $u = $this->user();
        foreach (['aktif', 'sebutan', 'dalam_bicara', 'tangguh', 'keputusan', 'rayuan', 'selesai'] as $s) {
            $this->actingAs($u)->postJson('/api/v1/kes/kes/'.$k->id.'/status', ['status' => $s, 'catatan' => 'Ujian '.$s])->assertOk()->assertJsonPath('data.status', $s);
        }
        $hist = DB::table('kes_status_sejarah')->where('kes_mahkamah_id', $k->id)->orderBy('id')->get();
        $this->assertSame(['didaftar', 'aktif', 'sebutan', 'dalam_bicara', 'tangguh', 'keputusan', 'rayuan', 'selesai'], $hist->pluck('status_baharu')->all());
        $this->assertNotNull($hist->last()->tarikh_perubahan);
        $this->actingAs($u)->postJson('/api/v1/kes/kes/'.$k->id.'/status', ['status' => 'tiada'])->assertUnprocessable();
        $this->assertAudited('kemas_kini', ['modul' => 'kes', 'id_rekod' => $k->id]);
    }

    public function test_SRS_KES_20_assign_officer_with_history_and_caseload(): void
    {
        $k = $this->kes([$this->lot()]);
        $ketua = $this->user(KesTestCase::KETUA);
        $a = $this->user();
        $b = $this->user();
        $this->actingAs($ketua)->postJson('/api/v1/kes/kes/'.$k->id.'/tugaskan', ['id_pegawai' => $a->id, 'justifikasi' => 'Penugasan awal'])->assertOk();
        $this->actingAs($ketua)->postJson('/api/v1/kes/kes/'.$k->id.'/tugaskan', ['id_pegawai' => $b->id, 'justifikasi' => 'Pegawai A bercuti panjang'])->assertOk();
        $hist = DB::table('kes_pegawai_sejarah')->where('kes_mahkamah_id', $k->id)->orderBy('id')->get();
        $this->assertCount(2, $hist);
        $this->assertSame($a->id, $hist[1]->id_pegawai_lama);
        $this->assertSame('Pegawai A bercuti panjang', $hist[1]->justifikasi);
        $this->assertCount(2, $this->notifier->of('kes.kes_ditugaskan'));

        // Only the Ketua Bahagian assigns; the target must be a legal officer.
        $this->actingAs($a)->postJson('/api/v1/kes/kes/'.$k->id.'/tugaskan', ['id_pegawai' => $a->id, 'justifikasi' => 'Ambil sendiri'])->assertForbidden();
        $this->actingAs($ketua)->postJson('/api/v1/kes/kes/'.$k->id.'/tugaskan', ['id_pegawai' => $this->user(['kes.kes.lihat'])->id, 'justifikasi' => 'Tidak layak'])->assertUnprocessable();

        $beban = collect($this->actingAs($ketua)->getJson('/api/v1/kes/laporan/beban')->assertOk()->json('data'))->keyBy('id');
        $this->assertSame(1, $beban[$b->id]['aktif']);
    }

    public function test_archive_only_finished_cases_and_case_records_are_never_deleted(): void
    {
        $k = $this->kes([$this->lot()]);
        $ketua = $this->user(KesTestCase::KETUA);
        $this->actingAs($ketua)->postJson('/api/v1/kes/kes/'.$k->id.'/arkib', ['sebab' => 'Cubaan awal'])->assertUnprocessable();
        app(KesService::class)->tukarStatus($k, 'selesai', $ketua);
        $this->actingAs($ketua)->postJson('/api/v1/kes/kes/'.$k->id.'/arkib', ['sebab' => 'Kes selesai, fail ditutup'])->assertOk()->assertJsonPath('data.status_arkib', true);
        $this->actingAs($ketua)->postJson('/api/v1/kes/kes/'.$k->id.'/prosiding', ['tarikh_prosiding' => now()->toDateTimeString(), 'jenis_prosiding' => 'sebutan'])->assertForbidden();

        $this->expectException(QueryException::class);
        DB::table('kes_mahkamah')->where('id', $k->id)->delete();
    }
}
