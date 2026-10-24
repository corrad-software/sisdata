<?php

namespace Tests\Modules\Pjk;

use App\Modules\Pjk\Models\DepositCagaran;
use App\Modules\Pjk\Models\JadualCaj;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\PindaanKontrak;
use App\Modules\Pjk\Models\PremisAset;
use App\Modules\Pjk\Models\VersiKontrak;
use App\Modules\Pjk\Reminders\BayaranHampirTamatTempoh;
use App\Modules\Pjk\Reminders\KontrakTamatTempoh;
use App\Modules\Pjk\Support\Aliran;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PindaanTest extends PjkTestCase
{
    public function test_SRS_PJK_07_rate_review_compares_with_comparable_contracts_and_gates_rahsia_valuation(): void
    {
        $user = $this->user();
        $k = $this->active($user);
        foreach (['1200.00', '1800.00', '2400.00'] as $rate) {
            $this->active($user, null, ['kadar' => $rate]); // same category & district, 120 m²
        }
        DB::table('penilaian')->insert(['id' => (string) Str::uuid(), 'lot_id' => $k->lot_id, 'tarikh_penilaian' => '2026-06-01', 'nilai_pasaran' => '600000.00', 'klasifikasi' => 'rahsia', 'created_at' => now(), 'updated_at' => now()]);

        $res = $this->actingAs($user)->getJson("/api/v1/pjk/kontrak/{$k->id}/semakan-kadar")->assertOk();
        $res->assertJsonPath('data.kadar_semasa', '1500.00')->assertJsonPath('data.bil_perbandingan', 3)
            ->assertJsonPath('data.kadar_per_m2', '12.50')->assertJsonPath('data.median_kadar_setara_per_m2', '15.00')
            ->assertJsonPath('data.kadar_cadangan_setara', '1800.00')
            ->assertJsonPath('data.nilai_pasaran', null)->assertJsonPath('data.penilaian_terhad', true);

        $valuer = $this->user([...self::PENDAFTAR, 'pjk.rahsia.lihat']);
        $res = $this->actingAs($valuer)->getJson("/api/v1/pjk/kontrak/{$k->id}/semakan-kadar")->assertOk();
        $res->assertJsonPath('data.nilai_pasaran', '600000.00')->assertJsonPath('data.hasil_tahunan_peratus', '3.00')
            ->assertJsonPath('data.kadar_cadangan_pasaran', '2500.00'); // 5% × 600k / 12
        $this->assertAudited('lihat', ['modul' => 'pjk', 'nama_entiti' => 'penilaian', 'id_pengguna' => $valuer->id]);
    }

    public function test_SRS_PJK_10_renewal_creates_new_agreement_version_with_documents_and_carries_deposit(): void
    {
        $user = $this->user();
        $k = $this->active($user, null, ['tarikh_mula' => '2025-01-01', 'tarikh_tamat' => '2026-12-31']);
        $res = $this->actingAs($user)->post("/api/v1/pjk/kontrak/{$k->id}/pindaan", [
            'jenis_pindaan' => 'pembaharuan', 'justifikasi' => 'Penyewa baik, sewa dinaikkan selaras pasaran',
            'data' => json_encode(['tarikh_tamat_baharu' => '2029-12-31', 'kadar_baharu' => '1650.00']),
            'dokumen' => UploadedFile::fake()->create('perjanjian-baharu.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame(Aliran::PINDAAN, $this->workflow->last()['flow']);
        $this->assertSame(10.0, $this->workflow->last()['context']['peratus_perubahan_kadar']);
        $this->assertNotNull($res->json('data.semakan_kadar'));
        $this->actingAs($user)->postJson("/api/v1/pjk/kontrak/{$k->id}/pindaan", ['jenis_pindaan' => 'pindaan_terma', 'justifikasi' => 'Lagi satu', 'data' => ['terma' => 'x']])->assertStatus(422);

        $this->complete(Aliran::PINDAAN, 'pindaan_kontrak', $res->json('data.id'), 'lulus', $this->user(['pjk.kontrak.lihat'])->id);

        $p = PindaanKontrak::query()->findOrFail($res->json('data.id'));
        $new = Kontrak::query()->withoutGlobalScope('access')->findOrFail($p->id_kontrak_baharu);
        $this->assertSame('diperbaharui', $k->refresh()->status);
        $this->assertSame(['aktif', '2027-01-01', '2029-12-31', '1650.00', $k->id], [$new->status, $new->tarikh_mula?->format('Y-m-d'), $new->tarikh_tamat?->format('Y-m-d'), $new->kadar, $new->id_kontrak_asal]);
        $this->assertSame($new->id, DepositCagaran::query()->value('kontrak_id'));
        $this->assertSame(0, JadualCaj::query()->where('kontrak_id', $new->id)->where('kod_caj', 'deposit')->count());
        $v = VersiKontrak::query()->where('kontrak_id', $k->id)->where('jenis_pindaan', 'pembaharuan')->sole();
        $this->assertSame([2, $p->id_dokumen_selepas], [$v->no_versi, $v->getAttribute('id_dokumen_selepas')]);
        $this->assertSame('2026-12-31', $v->data_sebelum['tarikh_tamat']);
        $this->assertSame('perjanjian_sewa', $this->dms->docs[$p->id_dokumen_selepas]['category']);
        $this->assertSame('disewa', PremisAset::query()->withoutGlobalScope('access')->find($k->premis_id)?->status_premis);
    }

    public function test_SRS_PJK_10_rate_change_and_term_amendment_versions_keep_before_and_after(): void
    {
        $user = $this->user();
        $k = $this->active($user);
        $id = $this->actingAs($user)->postJson("/api/v1/pjk/kontrak/{$k->id}/pindaan", ['jenis_pindaan' => 'semakan_kadar', 'justifikasi' => 'Semakan kadar tahunan', 'data' => ['kadar_baharu' => '1600', 'tarikh_berkuat_kuasa' => '2027-01-01']])->assertCreated()->json('data.id');
        $this->complete(Aliran::PINDAAN, 'pindaan_kontrak', $id, 'lulus', $this->user(['pjk.kontrak.lihat'])->id);
        $this->assertSame('1600.00', $k->refresh()->kadar);
        $this->assertSame('1500.00', JadualCaj::query()->where('kontrak_id', $k->id)->where('tempoh', '2026-12')->value('amaun'));
        $this->assertSame('1600.00', JadualCaj::query()->where('kontrak_id', $k->id)->where('tempoh', '2027-01')->value('amaun'));
        $v = VersiKontrak::query()->where('kontrak_id', $k->id)->where('jenis_pindaan', 'semakan_kadar')->sole();
        $this->assertSame(['1500.00', '1600.00'], [$v->data_sebelum['kadar'], $v->data_selepas['kadar']]);

        $id2 = $this->actingAs($user)->postJson("/api/v1/pjk/kontrak/{$k->id}/pindaan", ['jenis_pindaan' => 'pindaan_terma', 'justifikasi' => 'Tambah syarat waktu operasi', 'data' => ['terma' => 'Waktu operasi 8 pagi – 10 malam']])->json('data.id');
        $this->complete(Aliran::PINDAAN, 'pindaan_kontrak', $id2, 'tolak', $this->user(['pjk.kontrak.lihat'])->id);
        $this->assertSame('Terma ujian', $k->refresh()->terma);
        $this->assertSame('tolak', PindaanKontrak::query()->find($id2)?->status);
    }

    public function test_SRS_PJK_02_termination_releases_premise_and_cancels_future_charges(): void
    {
        $user = $this->user();
        $k = $this->active($user);
        $id = $this->actingAs($user)->postJson("/api/v1/pjk/kontrak/{$k->id}/pindaan", ['jenis_pindaan' => 'penamatan', 'justifikasi' => 'Pelanggaran terma', 'data' => ['tarikh_penamatan' => '2026-10-31', 'sebab_penamatan' => 'Melanggar syarat kegunaan premis']])->json('data.id');
        $this->complete(Aliran::PINDAAN, 'pindaan_kontrak', $id, 'lulus', $this->user(['pjk.kontrak.lihat'])->id);

        $this->assertSame('ditamatkan', $k->refresh()->status);
        $this->assertSame('kosong', PremisAset::query()->withoutGlobalScope('access')->find($k->premis_id)?->status_premis);
        $this->assertSame(0, JadualCaj::query()->where('kontrak_id', $k->id)->whereDate('tarikh_caj', '>', '2026-10-31')->where('status_caj', '!=', 'dibatalkan')->count());
    }

    public function test_SRS_PJK_08_acceptance_5_expiry_reminders_at_6_3_1_months_and_configurable_offsets(): void
    {
        $user = $this->user();
        $today = CarbonImmutable::parse('2026-10-15');
        $ends = ['2027-04-13' => 180, '2027-01-13' => 90, '2026-11-14' => 30, '2026-11-29' => 45];
        $ids = [];
        foreach ($ends as $end => $days) {
            $ids[$days] = $this->active($user, null, ['tarikh_mula' => '2025-01-01', 'tarikh_tamat' => $end])->id;
        }
        $source = new KontrakTamatTempoh;
        $this->assertSame([180, 90, 30], $source->defaultDays());
        foreach ([180, 90, 30] as $d) {
            $due = iterator_to_array($source->due($d, $today), false);
            $this->assertCount(1, $due, "offset {$d}");
            $this->assertSame('kontrak_pajakan:'.$ids[$d], $due[0]->key);
            $this->assertSame($d, $due[0]->data['baki_hari']);
            $this->assertArrayNotHasKey('penyewa', $due[0]->data); // no Sulit data in notifications
        }
        // An admin-configured offset (NTF hari_peringatan) works without code changes.
        $this->assertCount(1, iterator_to_array($source->due(45, $today), false));
        $this->assertCount(0, iterator_to_array($source->due(31, $today), false));
    }

    public function test_SRS_PJK_08_payment_due_reminders(): void
    {
        $k = $this->active($this->user(), null, ['tarikh_mula' => '2026-10-08', 'tarikh_tamat' => '2027-10-07']);
        // Oct rent due 2026-10-22 (7 days ahead)
        $due = iterator_to_array((new BayaranHampirTamatTempoh)->due(7, CarbonImmutable::parse('2026-10-15')), false);
        $this->assertCount(1, $due);
        $this->assertSame('RM 1,500.00', $due[0]->data['amaun']);
        $this->assertStringContainsString($k->id, (string) $due[0]->link);
    }
}
