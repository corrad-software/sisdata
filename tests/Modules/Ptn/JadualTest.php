<?php

namespace Tests\Modules\Ptn;

use App\Modules\Ptn\Models\JadualPemantauan;
use App\Modules\Ptn\Services\Penjadualan;
use App\Modules\Ptn\Services\RisikoService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** SRS-PTN-01/02: planning, risk-based annual schedule (acceptance #1), assignment. */
class JadualTest extends PtnTestCase
{
    private function kesAktif(string $lot, int $n = 1): void
    {
        for ($i = 0; $i < $n; $i++) {
            $id = (string) Str::uuid();
            DB::table('kes_mahkamah')->insert(['id' => $id, 'no_kes' => 'KES/'.$i, 'status' => 'aktif', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('kes_mahkamah_lot')->insert(['kes_mahkamah_id' => $id, 'lot_id' => $lot]);
        }
    }

    public function test_SRS_PTN_02_annual_schedule_generated_with_frequency_by_risk_level(): void
    {
        $admin = $this->admin();
        $rendah = $this->lot();
        $bangunan = $this->lot(kategori: 'BANGUNAN');
        $tinggi = $this->lot();
        DB::table('cukai')->insert(['id' => (string) Str::uuid(), 'lot_id' => $tinggi, 'jenis' => 'tanah', 'jumlah' => 10000, 'tarikh_akhir' => now()->subMonths(3)->toDateString(), 'status_bayaran' => 'tertunggak', 'created_at' => now(), 'updated_at' => now()]);
        $this->kesAktif($tinggi, 2);
        $kritikal = $this->lot();
        DB::table('isu_pemantauan')->insert(['id' => (string) Str::uuid(), 'no_rujukan' => 'ISU-T-1', 'lot_id' => $kritikal, 'kod_daerah' => '1008', 'kod_mukim' => '100803', 'kategori_isu' => 'pencerobohan', 'tahap_keterukan' => 'kritikal', 'keterangan' => 'x', 'tarikh_dibuka' => now()->toDateString(), 'status_isu' => 'terbuka', 'created_at' => now(), 'updated_at' => now()]);

        $year = now()->year + 1;
        $res = $this->actingAs($admin)->postJson('/api/v1/ptn/jadual/jana', ['tahun' => $year])->assertCreated();
        $this->assertSame(4, $res->json('data.lot'));

        $count = fn (string $lot) => JadualPemantauan::query()->withoutGlobalScope('access')->where('lot_id', $lot)->where('tahun', $year)->count();
        $level = fn (string $lot) => JadualPemantauan::query()->withoutGlobalScope('access')->where('lot_id', $lot)->value('keutamaan_risiko');
        $this->assertSame([1, 'rendah'], [$count($rendah), $level($rendah)]);
        $this->assertSame([2, 'rendah'], [$count($bangunan), $level($bangunan)], 'kategori BANGUNAN rendah = 2 lawatan setahun');
        $this->assertSame([4, 'tinggi'], [$count($tinggi), $level($tinggi)]);
        $this->assertSame([12, 'kritikal'], [$count($kritikal), $level($kritikal)]);

        $dates = JadualPemantauan::query()->withoutGlobalScope('access')->where('lot_id', $tinggi)->orderBy('tarikh_sasaran')->pluck('tarikh_sasaran')->map(fn ($d) => $d->toDateString())->all();
        $this->assertSame(["{$year}-01-01", "{$year}-04-02", "{$year}-07-02", "{$year}-10-01"], $dates);
        $this->assertTrue(JadualPemantauan::query()->withoutGlobalScope('access')->where('lot_id', $tinggi)->firstOrFail()->faktor_keutamaan['bil_isu_24_bulan'] === 0);

        // Idempotent: running again adds nothing.
        $this->actingAs($admin)->postJson('/api/v1/ptn/jadual/jana', ['tahun' => $year])->assertCreated()->assertJsonPath('data.dijana', 0);
        $this->assertAudited('jana_jadual', ['modul' => 'ptn']);
    }

    public function test_SRS_PTN_02_frequency_is_configurable_per_lot_category_and_shifted_after_last_visit(): void
    {
        $admin = $this->admin();
        $lot = $this->lot(kategori: 'PERUSAHAAN');
        $this->actingAs($admin)->putJson('/api/v1/ptn/konfigurasi/kekerapan', ['baris' => [['kategori_lot' => 'PERUSAHAAN', 'tahap_risiko' => 'rendah', 'kekerapan_setahun' => 3]]])->assertOk();
        $year = now()->year + 1;
        app(Penjadualan::class)->janaTahunan($year);
        $this->assertSame(3, JadualPemantauan::query()->withoutGlobalScope('access')->where('lot_id', $lot)->count());

        $p = app(Penjadualan::class);
        $dates = $p->tarikhSasaran(2027, 2, Carbon::parse('2026-12-01'));
        $this->assertSame(['2027-06-02', '2027-12-01'], array_map(fn ($d) => $d->toDateString(), $dates));
    }

    public function test_SRS_PTN_01_plan_records_schedule_officer_lot_objective_and_status(): void
    {
        $sup = $this->user(self::PENYELIA, ['1008']);
        $officer = $this->user(self::LAPANGAN, ['1008']);
        $sepangOfficer = $this->user(self::LAPANGAN, ['1009']);
        $lot = $this->lot();

        $id = $this->actingAs($sup)->postJson('/api/v1/ptn/jadual', ['lot_id' => $lot, 'tarikh_sasaran' => now()->addWeek()->toDateString(), 'objektif' => 'Semak aduan pencerobohan', 'jenis_pemeriksaan' => 'aduan'])
            ->assertCreated()->assertJsonPath('data.status_pelaksanaan', 'dirancang')->json('data.id');
        $this->actingAs($sup)->getJson('/api/v1/ptn/pegawai-layak?kod_daerah=1008')->assertOk()->assertJsonFragment(['id' => $officer->id])->assertJsonMissing(['id' => $sepangOfficer->id]);
        $this->actingAs($sup)->postJson("/api/v1/ptn/jadual/{$id}/tugaskan", ['id_pegawai' => $sepangOfficer->id])->assertUnprocessable();
        $this->actingAs($sup)->postJson("/api/v1/ptn/jadual/{$id}/tugaskan", ['id_pegawai' => $officer->id])->assertOk()
            ->assertJsonPath('data.status_pelaksanaan', 'ditugaskan')->assertJsonPath('data.pegawai.id', $officer->id)->assertJsonPath('data.objektif', 'Semak aduan pencerobohan');
        $this->assertCount(1, $this->notifier->of('ptn.tugasan_baharu'));

        $row = $this->actingAs($sup)->getJson('/api/v1/ptn/jadual?kod_daerah=1008')->assertOk()->json('data.0');
        $this->assertSame([$lot, 'aduan', 2], [$row['lot_id'], $row['jenis_pemeriksaan'], $row['versi']]);

        $this->actingAs($sup)->postJson("/api/v1/ptn/jadual/{$id}/batal", ['sebab' => 'Lot dilupuskan'])->assertOk()->assertJsonPath('data.status_pelaksanaan', 'dibatalkan');
    }

    public function test_SRS_PTN_01_auto_assignment_round_robin_within_district(): void
    {
        $sup = $this->user(self::PENYELIA);
        $a = $this->user(self::LAPANGAN, ['1008']);
        $b = $this->user(self::LAPANGAN, ['1008']);
        foreach (range(1, 4) as $i) {
            $this->jadual($this->lot());
        }
        $this->actingAs($sup)->postJson('/api/v1/ptn/jadual-agih', ['kod_daerah' => '1008'])->assertOk()->assertJsonPath('data.ditugaskan', 4);
        $load = JadualPemantauan::query()->withoutGlobalScope('access')->toBase()->groupBy('id_pegawai_bertugas')->selectRaw('id_pegawai_bertugas, COUNT(*) AS n')->get()->mapWithKeys(fn ($r) => [$r->id_pegawai_bertugas => (int) $r->n])->all();
        ksort($load);
        $this->assertSame([$a->id => 2, $b->id => 2], $load);
    }

    public function test_SRS_PTN_01_access_is_by_permission_and_district_scope(): void
    {
        $this->jadual($this->lot());
        $this->jadual($this->lot('100901'));
        $this->actingAs($this->user(['ptn.lapangan.lihat']))->getJson('/api/v1/ptn/jadual')->assertForbidden();
        $this->actingAs($this->user(['ptn.jadual.lihat']))->postJson('/api/v1/ptn/jadual/jana', ['tahun' => 2030])->assertForbidden();
        $sepang = $this->user(self::PENYELIA, ['1009']);
        $rows = $this->actingAs($sepang)->getJson('/api/v1/ptn/jadual')->assertOk()->json('data');
        $this->assertSame(['1009'], array_values(array_unique(array_column($rows, 'kod_daerah'))));
        $petaling = JadualPemantauan::query()->withoutGlobalScope('access')->where('kod_daerah', '1008')->firstOrFail();
        $this->actingAs($sepang)->getJson('/api/v1/ptn/jadual/'.$petaling->id)->assertNotFound();
        $this->actingAs($sepang)->postJson('/api/v1/ptn/jadual', ['lot_id' => $petaling->lot_id, 'tarikh_sasaran' => '2030-01-01', 'objektif' => 'x', 'jenis_pemeriksaan' => 'rutin'])->assertUnprocessable();
        // Generation by a district officer covers only that district's lots.
        $r = app(Penjadualan::class)->janaTahunan(2031, $sepang);
        $this->assertSame(1, $r['lot']);
        app(RisikoService::class);
    }
}
