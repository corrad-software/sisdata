<?php

namespace Tests\Modules\Pws;

use App\Modules\Adu\Models\Aduan;
use Illuminate\Support\Facades\DB;

/** Public read feeds (SRS-PWS-06/17/18/19), complaints, support and FAQ — Awam data only. */
class MaklumatAwamTest extends PwsTestCase
{
    public function test_SRS_PWS_06_catalogue_shows_only_vacant_portal_premises_with_whitelisted_fields(): void
    {
        $shown = $this->premis(['nama_premis' => 'Kedai Wakaf Seksyen 7']);
        $this->premis(['papar_portal' => false]);
        $this->premis(['status_premis' => 'disewa']);
        $token = $this->token(['baca']);

        $res = $this->awam('GET', 'hartanah', [], $token)->assertOk();
        $this->assertSame(1, $res->json('meta.total'));
        $row = $res->json('data.0');
        $this->assertSame($shown->kod_premis, $row['kod_premis']);
        $this->assertSame('1500.00', $row['kadar_tawaran_sebulan']);
        foreach (['lot_id', 'id', 'penyewa', 'bahagian', 'klasifikasi', 'catatan'] as $internal) {
            $this->assertArrayNotHasKey($internal, $row);
        }
        $this->assertSame([], array_diff(array_keys($row), ['kod_premis', 'nama_premis', 'jenis_aset', 'kategori_hartanah', 'kategori_hartanah_nama', 'keluasan_m2', 'kod_daerah', 'kod_mukim', 'kadar_tawaran_sebulan', 'keterangan']));
        $this->awam('GET', 'hartanah', ['kod_daerah' => 'abc'], $token)->assertUnprocessable();
    }

    /** Confirmed + draft distribution rows for one wakaf and one purchased premises. */
    private function agihan(): void
    {
        $policy = DB::table('dasar_agihan_manfaat')->insertGetId(['versi_dasar' => 1, 'kategori_hartanah' => 'premis_perniagaan', 'status_perolehan' => 'wakaf', 'kadar_agihan' => 90,
            'penerima_manfaat' => json_encode([['kod' => 'asnaf', 'nama' => 'Asnaf', 'peratus' => 100]]), 'tarikh_mula_kuasa' => '2026-01-01', 'status' => 'aktif', 'created_at' => now(), 'updated_at' => now()]);
        $row = fn (string $status, string $perolehan, string $agihan, int $premisId) => [
            'premis_id' => $premisId, 'tempoh_perakaunan' => '2026-09', 'hasil_kutipan' => '1000.00', 'id_dasar_digunakan' => $policy, 'versi_dasar' => 1,
            'kategori_hartanah' => 'premis_perniagaan', 'status_perolehan' => $perolehan, 'kadar_agihan' => 90, 'amaun_agihan' => $agihan,
            'pecahan_penerima' => json_encode([['nama' => 'Asnaf', 'amaun' => $agihan]]), 'kod_daerah' => '1008', 'status_pengesahan' => $status, 'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('kiraan_agihan_manfaat')->insert([
            $row('disahkan', 'wakaf', '900.00', $this->premis()->id),
            $row('disahkan', 'belian', '500.00', $this->premis()->id),
            $row('draf', 'wakaf', '777.00', $this->premis()->id),
        ]);
    }

    /** Kriteria penerimaan 5. */
    public function test_SRS_PWS_17_benefit_distribution_shows_confirmed_figures_from_sisdata_only(): void
    {
        $this->agihan();
        $res = $this->awam('GET', 'agihan', [], $this->token(['baca']))->assertOk();
        $this->assertCount(2, $res->json('data'));
        $this->assertEqualsCanonicalizing(['900.00', '500.00'], array_column($res->json('data'), 'agihan'));
        $this->assertStringNotContainsString('777.00', $res->getContent());
        $this->assertStringNotContainsString('premis_id', $res->getContent());
        $this->assertStringNotContainsString('kontrak_id', $res->getContent());
    }

    public function test_SRS_PWS_18_wakaf_and_purchased_property_are_shown_separately(): void
    {
        $this->agihan();
        $rows = collect($this->awam('GET', 'agihan', [], $this->token(['baca']))->assertOk()->json('data'));
        $this->assertCount(2, $rows->pluck('status_perolehan')->unique());
        $this->assertNotEquals($rows[0]['agihan'], $rows[1]['agihan']);
    }

    public function test_SRS_PWS_19_update_time_and_disclaimer_in_both_languages(): void
    {
        $this->agihan();
        $token = $this->token(['baca']);
        $res = $this->awam('GET', 'agihan', [], $token)->assertOk();
        $this->assertNotEmpty($res->json('meta.dikemas_kini'));
        $this->assertStringContainsString('tertakluk kepada Dasar Agihan Manfaat', $res->json('meta.penafian'));
        $this->assertStringContainsString('subject to the Benefit Distribution Policy', $this->awam('GET', 'agihan', [], $token, ['Accept-Language' => 'en'])->json('meta.penafian'));
    }

    public function test_SRS_PWS_24_public_complaint_goes_to_ADU_with_the_end_users_ip_and_captcha(): void
    {
        DB::table('ref_kategori_aduan')->insert(['kod' => 'PEMBUANGAN_SAMPAH', 'nama' => 'Pembuangan sampah haram', 'susunan' => 0, 'aktif' => true, 'versi' => 1, 'sah_dari' => '2000-01-01', 'created_at' => now(), 'updated_at' => now()]);
        $token = $this->token(['aduan']);
        $form = ['kategori' => 'PEMBUANGAN_SAMPAH', 'keterangan' => 'Longgokan sampah dibuang di tepi tanah wakaf berhampiran surau.', 'lokasi_teks' => 'Jalan Wakaf 1, Petaling',
            'nama' => 'Aminah Sintetik', 'emel' => 'aminah@contoh.invalid', 'captcha' => 'salah'];
        $this->awam('POST', 'aduan', $form, $token, $this->kunci('a') + ['X-Pengguna-IP' => '198.51.100.7'])->assertUnprocessable()->assertJsonValidationErrors('captcha');

        $res = $this->awam('POST', 'aduan', ['captcha' => 'lulus-captcha'] + $form, $token, $this->kunci('b') + ['X-Pengguna-IP' => '198.51.100.7'])->assertCreated();
        $no = $res->json('data.no_rujukan_aduan');
        $this->assertSame('portal', Aduan::query()->withoutGlobalScope('access')->where('no_rujukan_aduan', $no)->value('saluran_penerimaan'));
        $this->awam('POST', 'aduan/semak', ['no_rujukan_aduan' => $no, 'pengesahan' => 'aminah@contoh.invalid'], $token)->assertOk()->assertJsonPath('data.no_rujukan_aduan', $no);
        $this->awam('POST', 'aduan/semak', ['no_rujukan_aduan' => $no, 'pengesahan' => 'lain@contoh.invalid'], $token)->assertNotFound();
        $this->assertNotEmpty($this->awam('GET', 'aduan/rujukan', [], $token)->assertOk()->json('data.kategori'));
    }

    public function test_SRS_PWS_24_support_request_opens_a_TKT_ticket_from_the_portal_channel(): void
    {
        $token = $this->token(['sokongan']);
        $form = ['tajuk' => 'Tidak boleh muat naik dokumen', 'keterangan' => 'Borang permohonan sewaan gagal menerima fail PDF saya.', 'kategori' => 'teknikal', 'emel' => 'awam@contoh.invalid', 'captcha' => 'lulus-captcha'];
        $res = $this->awam('POST', 'sokongan', $form, $token, $this->kunci())->assertCreated();
        $this->assertSame('TKT-2026-00001', $res->json('data.no_tiket'));
        $this->assertSame('portal', $this->tickets->opened[0]['channel']);
        $this->assertNull($this->tickets->opened[0]['reporter']);
        $this->assertSame('Portal Hartanah Wakaf Selangor', $this->tickets->opened[0]['data']['konteks']['sumber']);

        // MOD-TKT not merged yet → explicit 503, nothing silently lost.
        $this->tickets->enabled = false;
        $this->awam('POST', 'sokongan', $form, $token, $this->kunci('2'))->assertStatus(503);
        $this->awam('POST', 'sokongan', ['captcha' => 'x'] + $form, $token, $this->kunci('3'))->assertUnprocessable()->assertJsonValidationErrors('captcha');
    }

    public function test_SRS_PWS_06_faq_uses_the_knowledge_base_as_anonymous_awam_only_and_hides_internal_links(): void
    {
        $token = $this->token(['baca']);
        $res = $this->awam('GET', 'soalan-lazim', ['q' => 'daftar wakaf'], $token)->assertOk();
        $this->assertSame('Panduan Wakaf Awam', $res->json('data.pangkalan_pengetahuan.0.dokumen'));
        $this->assertStringNotContainsString('/admin/', $res->getContent());
        $ans = $this->awam('POST', 'soalan-lazim/tanya', ['soalan' => 'Bagaimana mendaftar wakaf?'], $token)->assertOk();
        $this->assertTrue($ans->json('data.cukup_sumber'));
        $this->assertStringNotContainsString('/admin/', $ans->getContent());
        $this->assertSame([null, null], $this->kb->users, 'KBA dipanggil tanpa pengguna — sumber Awam sahaja.');
    }

    public function test_SRS_PWS_27_portal_pushes_daily_page_statistics_idempotently(): void
    {
        $token = $this->token(['analitik']);
        $rows = ['rekod' => [
            ['tarikh' => '2026-10-14', 'halaman' => '/hartanah', 'bilangan_lawatan' => 120, 'tempoh_purata' => 45, 'borang_dimulakan' => 0, 'borang_dihantar' => 0],
            ['tarikh' => '2026-10-14', 'halaman' => '/permohonan-wakaf', 'sumber_rujukan' => 'google', 'bilangan_lawatan' => 40, 'borang_dimulakan' => 20, 'borang_dihantar' => 5],
        ]];
        $this->awam('POST', 'analitik', $rows, $token)->assertOk()->assertJsonPath('data.diterima', 2);
        $rows['rekod'][0]['bilangan_lawatan'] = 130;
        $this->awam('POST', 'analitik', $rows, $token)->assertOk();
        $this->assertSame(2, DB::table('pws_analitik_lawatan')->count());
        $this->awam('POST', 'analitik', ['rekod' => [['tarikh' => '2026-10-14', 'halaman' => 'https://jejak.example/?u=1', 'bilangan_lawatan' => 1]]], $token)->assertUnprocessable();

        $a = $this->actingAs($this->user(['pws.pemantauan.lihat']))->getJson('/api/v1/pws/analitik?dari=2026-10-01&hingga=2026-10-15')->assertOk();
        $this->assertSame(170, $a->json('data.jumlah_lawatan'));
        $this->assertEquals(25.0, $a->json('data.kadar_penyempurnaan_borang'));
        $this->assertSame('/hartanah', $a->json('data.halaman_popular.0.halaman'));
    }
}
