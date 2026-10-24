<?php

namespace Tests\Modules\Adu;

use App\Modules\Adu\Models\EskalasiAduan;
use App\Modules\Adu\Models\JejakSla;
use App\Modules\Adu\Models\PeraturanPenugasan;
use App\Modules\Adu\Models\SlaKategori;
use App\Modules\Adu\Services\SlaAduan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SlaPenugasanTest extends AduTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_SRS_ADU_11_assignment_is_automatic_by_category_and_district_rules(): void
    {
        $petaling = $this->user(self::PEGAWAI, ['1008']);
        $sepang = $this->user(self::PEGAWAI, ['1009']);
        $specialist = $this->user(self::PEGAWAI, ['1008']);
        PeraturanPenugasan::query()->create(['kod_daerah' => '1008', 'id_pegawai' => $petaling->id, 'keutamaan' => 10]);
        PeraturanPenugasan::query()->create(['kod_daerah' => '1009', 'id_pegawai' => $sepang->id, 'keutamaan' => 10]);
        PeraturanPenugasan::query()->create(['kategori' => 'PERTIKAIAN_SEMPADAN', 'kod_daerah' => '1008', 'id_pegawai' => $specialist->id, 'keutamaan' => 50]);

        $a = $this->aduan(['lot_id' => $this->lot('100803')]);
        $b = $this->aduan(['lot_id' => $this->lot('100901')]);
        $c = $this->aduan(['lot_id' => $this->lot('100803', [3.2, 101.5]), 'kategori' => 'PERTIKAIAN_SEMPADAN', 'keterangan' => 'Batu sempadan dialih']);

        $this->assertSame([$petaling->id, 'ditugaskan'], [$a->id_pegawai, $a->status]);
        $this->assertSame($sepang->id, $b->id_pegawai);
        $this->assertSame($specialist->id, $c->id_pegawai, 'category + district rule is more specific');
        $this->assertSame(['adu.aduan_ditugaskan'], array_values(array_unique(array_column(array_filter($this->notifier->sent, fn ($s) => in_array($a->no_rujukan_aduan, $s['data'], true) && $s['type'] !== 'adu.aduan_baharu'), 'type'))));
    }

    public function test_SRS_ADU_11_role_rule_picks_least_loaded_in_scope_officer_and_manual_assignment_works(): void
    {
        $busy = $this->user(self::PEGAWAI, ['1008'], 'pegawai_daerah');
        $free = $this->user(self::PEGAWAI, ['1008'], 'pegawai_daerah');
        $outOfScope = $this->user(self::PEGAWAI, ['1009'], 'pegawai_daerah');
        PeraturanPenugasan::query()->create(['kod_peranan' => 'pegawai_daerah', 'keutamaan' => 10]);
        $first = $this->aduan(['lot_id' => $this->lot()]);
        $this->assertContains($first->id_pegawai, [$busy->id, $free->id]);
        $second = $this->aduan(['lot_id' => $this->lot('100803', [3.3, 101.3])]);
        $this->assertNotSame($first->id_pegawai, $second->id_pegawai, 'least loaded wins');

        $unit = $this->user(self::UNIT_ADUAN);
        $this->actingAs($unit)->postJson("/api/v1/adu/aduan/{$first->id}/tugaskan", ['id_pegawai' => $outOfScope->id])->assertUnprocessable();
        $other = $first->id_pegawai === $busy->id ? $free : $busy;
        $this->actingAs($unit)->postJson("/api/v1/adu/aduan/{$first->id}/tugaskan", ['id_pegawai' => $other->id, 'catatan' => 'Beban kerja'])->assertOk()
            ->assertJsonPath('data.pegawai.id', $other->id);
        $this->assertDatabaseHas('kronologi_aduan', ['aduan_id' => $first->id, 'jenis_tindakan' => 'tugas_semula']);

        // Assignment list only offers eligible officers.
        $ids = array_column($this->actingAs($unit)->getJson("/api/v1/adu/aduan/{$first->id}/pegawai-layak")->assertOk()->json('data'), 'id');
        $this->assertContains($busy->id, $ids);
        $this->assertNotContains($outOfScope->id, $ids);
    }

    public function test_SRS_ADU_12_sla_starts_by_category_and_remaining_time_is_shown_in_the_queue(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-11-02 09:00', 'Asia/Kuala_Lumpur')); // Monday
        SlaKategori::query()->where('kategori', 'KESELAMATAN')->update(['tempoh_respons' => 1, 'tempoh_penyelesaian' => 5]);

        $aduan = $this->aduan(['kategori' => 'KESELAMATAN', 'keterangan' => 'Bangunan usang berisiko']);
        $jejak = JejakSla::query()->where('aduan_id', $aduan->id)->firstOrFail();
        $this->assertSame('2026-11-03 09:00', $jejak->masa_sasaran_respons->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i'));
        $this->assertSame('2026-11-09 09:00', $jejak->masa_sasaran_selesai->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i'));

        Carbon::setTestNow(CarbonImmutable::parse('2026-11-04 09:00', 'Asia/Kuala_Lumpur'));
        $user = $this->user(['adu.aduan.lihat']);
        $row = $this->actingAs($user)->getJson('/api/v1/adu/aduan?susun=sla')->assertOk()->json('data.0');
        $this->assertEquals(3.0, $row['sla']['baki_hari_selesai']);
        $this->assertSame(3 * 9 * 60, $row['sla']['baki_minit_selesai']);
    }

    public function test_SRS_ADU_13_sla_does_not_run_on_weekends_or_configured_public_holidays(): void
    {
        DB::table('wf_cuti_umum')->insert(['tarikh' => '2026-12-11', 'nama' => 'Hari Keputeraan Sultan Selangor', 'negeri' => 'selangor', 'jenis' => 'negeri', 'created_at' => now(), 'updated_at' => now()]);
        Carbon::setTestNow(CarbonImmutable::parse('2026-12-10 16:00', 'Asia/Kuala_Lumpur')); // Thursday
        SlaKategori::query()->where('kategori', 'KESELAMATAN')->update(['tempoh_respons' => 1, 'tempoh_penyelesaian' => 2]);

        $aduan = $this->aduan(['kategori' => 'KESELAMATAN', 'keterangan' => 'Bahaya']);
        $jejak = JejakSla::query()->where('aduan_id', $aduan->id)->firstOrFail();
        // Fri 11 Dec holiday, Sat/Sun weekend → 1 working day lands on Monday 14 Dec 16:00, 2 on Tuesday.
        $this->assertSame('2026-12-14 16:00', $jejak->masa_sasaran_respons->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i'));
        $this->assertSame('2026-12-15 16:00', $jejak->masa_sasaran_selesai->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i'));

        // The clock does not move over the holiday weekend.
        $baki = app(SlaAduan::class)->baki($jejak, CarbonImmutable::parse('2026-12-13 12:00', 'Asia/Kuala_Lumpur'));
        $baki2 = app(SlaAduan::class)->baki($jejak, CarbonImmutable::parse('2026-12-10 17:00', 'Asia/Kuala_Lumpur'));
        $this->assertSame($baki2['baki_minit_selesai'], $baki['baki_minit_selesai']);
    }

    public function test_SRS_ADU_14_escalation_fires_per_tier_at_configured_thresholds(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-11-02 09:00', 'Asia/Kuala_Lumpur'));
        SlaKategori::query()->where('kategori', 'PENCEROBOHAN')->update(['tempoh_respons' => 1, 'tempoh_penyelesaian' => 10]);
        $officer = $this->user(self::PEGAWAI);
        $head = $this->user(['adu.aduan.lihat'], null, 'pelulus');
        PeraturanPenugasan::query()->create(['id_pegawai' => $officer->id]);
        $supervisor = $this->user(['adu.aduan.lihat']);
        $this->supervise($supervisor, $officer);
        $aduan = $this->aduan();
        $this->assertSame($supervisor->id, $aduan->id_penyelia);
        $sla = app(SlaAduan::class);

        $sla->semak(); // nothing yet
        $this->assertSame(0, EskalasiAduan::query()->count());

        Carbon::setTestNow(CarbonImmutable::parse('2026-11-03 10:00', 'Asia/Kuala_Lumpur')); // response target passed
        $this->assertSame(1, $sla->semak()['eskalasi']);
        $this->assertSame([1], EskalasiAduan::query()->pluck('peringkat')->all());
        $this->assertSame([$supervisor->id], $this->notifier->of('adu.sla_dilepasi')[0]['to']);

        Carbon::setTestNow(CarbonImmutable::parse('2026-11-12 10:00', 'Asia/Kuala_Lumpur')); // ~80%
        $sla->semak();
        $this->assertSame('hampir_tamat', JejakSla::query()->where('aduan_id', $aduan->id)->value('status_sla'));
        $this->assertEqualsCanonicalizing([$officer->id, $supervisor->id], $this->notifier->of('adu.sla_hampir_tamat')[0]['to']);

        Carbon::setTestNow(CarbonImmutable::parse('2026-11-25 10:00', 'Asia/Kuala_Lumpur')); // > 150%
        $sla->semak();
        $sla->semak(); // idempotent
        $this->assertSame([1, 2, 3, 4], EskalasiAduan::query()->orderBy('peringkat')->pluck('peringkat')->all());
        $this->assertSame('dilepasi', JejakSla::query()->where('aduan_id', $aduan->id)->value('status_sla'));
        $this->assertContains($head->id, collect($this->notifier->of('adu.sla_dilepasi'))->pluck('to')->flatten()->all(), 'tier 4 → ketua bahagian');
        $this->assertSame(4, JejakSla::query()->where('aduan_id', $aduan->id)->value('bilangan_eskalasi'));
    }

    public function test_SRS_ADU_23_officers_are_notified_on_new_assignment_and_resolution(): void
    {
        $officer = $this->user(self::PEGAWAI);
        PeraturanPenugasan::query()->create(['id_pegawai' => $officer->id]);
        $aduan = $this->aduan();

        $this->assertNotEmpty($this->notifier->of('adu.aduan_baharu'));
        $this->assertSame([$officer->id], $this->notifier->of('adu.aduan_ditugaskan')[0]['to']);
        $this->assertSame('/admin/adu/aduan/'.$aduan->id, $this->notifier->of('adu.aduan_ditugaskan')[0]['link']);
        $this->assertArrayNotHasKey('nama', $this->notifier->of('adu.aduan_ditugaskan')[0]['data'], 'no reporter identity in notifications');

        $this->actingAs($officer)->postJson("/api/v1/adu/aduan/{$aduan->id}/penutupan", ['kategori_resolusi' => 'selesai_tindakan', 'keterangan_resolusi' => 'Penghuni haram telah keluar.', 'justifikasi' => 'Disahkan semasa lawatan tapak.'])->assertOk();
        $this->completeWorkflow($aduan->refresh(), 'lulus', $this->user(['adu.aduan.lihat'])->id);
        $this->assertSame([$officer->id], $this->notifier->of('adu.aduan_selesai')[0]['to']);
    }
}
