<?php

namespace Tests\Modules\Lap;

use App\Modules\Lap\Models\JadualPenjanaan;
use App\Modules\Lap\Models\LogPenjanaan;
use App\Modules\Lap\Services\Eksport;
use App\Modules\Lap\Services\Penjana;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

/** Exports, generation log + audit, background and scheduled generation. */
class EksportJadualTest extends LapTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function arrears(): void
    {
        $k = $this->contract($this->lot(['kod_mukim' => '100803']));
        $this->charge($k, now()->subDays(40)->toDateString(), '1234.56');
        $this->charge($k, now()->subDays(400)->toDateString(), '0.10', '0', ['kod_caj' => 'servis']);
        $k2 = $this->contract($this->lot(['kod_mukim' => '100901']));
        $this->charge($k2, now()->subDays(100)->toDateString(), '2000.00', '1000.01');
    }

    /** @return list<list<string>> */
    private function csv(string $body): array
    {
        $lines = array_map(fn ($l) => str_getcsv($l, ',', '"', ''), preg_split('/\r?\n/', trim(substr($body, 3))));

        return array_values(array_filter($lines, fn ($l) => $l !== [null]));
    }

    public function test_SRS_LAP_04_pdf_excel_and_csv_carry_identical_values(): void
    {
        $this->arrears();
        $user = $this->user();
        $req = ['sumber' => ['katalog' => 'KEW-02']];

        $csv = $this->actingAs($user)->post('/api/v1/lap/eksport', $req + ['format' => 'csv'])->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent();
        $rows = $this->csv((string) $csv);
        $header = array_search('Kumpulan umur tunggakan', array_column($rows, 0), true);
        $this->assertNotFalse($header);
        $table = array_slice($rows, $header + 1);
        $byAge = collect($table)->keyBy(fn ($r) => (string) $r[0]);
        $this->assertSame('1234.56', $byAge['31-90'][2]);
        $this->assertSame('999.99', $byAge['91-180'][2]);
        $this->assertSame('2234.65', $byAge['Jumlah'][2]);

        $xlsx = $this->actingAs($user)->post('/api/v1/lap/eksport', $req + ['format' => 'xlsx'])->assertOk()->getContent();
        $path = tempnam(sys_get_temp_dir(), 'lapx');
        file_put_contents($path, $xlsx);
        $sheet = IOFactory::load($path)->getSheet(0)->toArray(null, false, false);
        unlink($path);
        $cells = collect($sheet)->keyBy(fn ($r) => (string) $r[0]);
        $this->assertEqualsWithDelta(1234.56, (float) $cells['31-90'][2], 0.0001);
        $this->assertEqualsWithDelta(2234.65, (float) $cells['Jumlah'][2], 0.0001);

        $pdf = $this->actingAs($user)->post('/api/v1/lap/eksport', $req + ['format' => 'pdf'])->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent();
        $this->assertStringStartsWith('%PDF', (string) $pdf);
        // The PDF is rendered from the same section cells.
        $penjana = app(Penjana::class);
        $this->actingAs($user);
        $r = $penjana->papar($user, ['katalog' => 'KEW-02']);
        $e = app(Eksport::class);
        $html = $e->html('x', [], [$e->section('KEW-02', $r['bahagian'][0]['hasil'])], 'wm', null);
        $this->assertStringContainsString('1,234.56', $html);
        $this->assertStringContainsString('2,234.65', $html);
    }

    public function test_SRS_LAP_10_generation_and_export_are_logged_with_parameters_user_time_and_count(): void
    {
        $this->arrears();
        $user = $this->user();
        $this->actingAs($user)->post('/api/v1/lap/eksport', ['sumber' => ['katalog' => 'KEW-02'], 'format' => 'csv', 'parameter' => ['kod_daerah' => '1008']])->assertOk();
        $log = LogPenjanaan::query()->where('format_eksport', 'csv')->sole();
        $this->assertSame($user->id, $log->id_pengguna);
        $this->assertSame(2, $log->bilangan_rekod);
        $this->assertSame('KEW-02', $log->kod_laporan);
        $this->assertSame('1008', $log->parameter_digunakan['parameter']['kod_daerah']);
        $this->assertNotNull($log->checksum);
        $this->assertAudited('eksport', ['modul' => 'lap', 'id_rekod' => $log->id, 'id_pengguna' => $user->id])
            ->assertMeta(['format' => 'csv', 'bilangan_rekod' => 2, 'kod_laporan' => 'KEW-02']);

        $this->jana($user, ['katalog' => 'KEW-02']);
        $this->assertSame(1, LogPenjanaan::query()->where('format_eksport', 'paparan')->count());

        // Auditors see everyone's log; others only their own.
        $auditor = $this->user(['lap.laporan.lihat', 'lap.log.lihat']);
        $this->assertSame(2, $this->actingAs($auditor)->getJson('/api/v1/lap/log?semua=1')->json('meta.jumlah'));
        $this->assertSame(0, $this->actingAs($this->user())->getJson('/api/v1/lap/log?semua=1')->json('meta.jumlah'));
    }

    public function test_SRS_LAP_04_exports_carry_watermark_and_sulit_marking_and_need_permission(): void
    {
        $k = $this->contract($this->lot(), ['klasifikasi' => 'sulit']);
        $this->charge($k, now()->subDays(40)->toDateString(), '10.00');
        $user = $this->user([...self::PEMBACA, 'pjk.sulit.lihat']);
        $csv = (string) $this->actingAs($user)->post('/api/v1/lap/eksport', ['sumber' => ['katalog' => 'KEW-02'], 'format' => 'csv'])->assertOk()->getContent();
        $rows = $this->csv($csv);
        $this->assertSame('SULIT', $rows[0][0]);
        $this->assertStringContainsString($user->email, $csv);
        $this->assertStringContainsString('Data dikemas kini terakhir', $csv);
        $this->assertStringContainsString('Penapis: Tertunggak : Ya', $csv);

        $this->actingAs($this->user(array_values(array_diff(self::PEMBACA, ['lap.laporan.eksport']))))
            ->post('/api/v1/lap/eksport', ['sumber' => ['katalog' => 'KEW-02'], 'format' => 'csv'])->assertForbidden();
    }

    public function test_SRS_LAP_04_csv_text_cells_are_protected_from_formula_injection(): void
    {
        $this->complaint('100803', ['kategori' => '=HYPERLINK("http://x")']);
        $csv = (string) $this->actingAs($this->user())->post('/api/v1/lap/eksport', ['sumber' => ['adhoc' => ['set' => 'aduan', 'medan' => ['kategori']]], 'format' => 'csv'])->assertOk()->getContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_SRS_LAP_05_chart_image_export_is_logged(): void
    {
        $user = $this->user();
        $this->actingAs($user)->postJson('/api/v1/lap/eksport/imej', ['tajuk' => 'Trend aduan', 'kod_laporan' => 'OPS-03', 'bilangan_rekod' => 12])->assertCreated();
        $this->assertAudited('eksport', ['modul' => 'lap'])->assertMeta(['format' => 'png', 'kod_laporan' => 'OPS-03']);
    }

    public function test_SRS_LAP_07_large_export_runs_in_background_and_notifies_the_requester(): void
    {
        $this->params->values['lap.had_segerak'] = 1;
        $this->complaint('100803');
        $this->complaint('100803');
        $user = $this->user();

        $id = $this->actingAs($user)->postJson('/api/v1/lap/eksport', ['sumber' => ['adhoc' => ['set' => 'aduan', 'medan' => ['no_rujukan_aduan', 'kategori']]], 'format' => 'xlsx'])
            ->assertStatus(202)->json('data.id_log');
        $log = LogPenjanaan::query()->findOrFail($id);
        $this->assertSame('berjaya', $log->status, 'queue=sync in tests');
        $this->assertSame('latar', $log->mod);
        $this->assertSame(2, $log->bilangan_rekod);
        Storage::disk('local')->assertExists((string) $log->laluan_fail);

        $this->assertSame('lap.laporan_siap', $this->notifier->sent[0]['type']);
        $this->assertSame([$user->id], $this->notifier->sent[0]['recipients']);
        $this->assertSame('/admin/lap/log?id='.$id, $this->notifier->sent[0]['link']);

        $this->actingAs($user)->get("/api/v1/lap/log/{$id}/muat-turun")->assertOk()->assertHeader('Content-Type', Eksport::MIME['xlsx']);
        $this->assertAudited('muat_turun', ['modul' => 'lap', 'id_rekod' => $id]);
        $this->actingAs($this->user([...self::PEMBACA, 'lap.log.lihat']))->get("/api/v1/lap/log/{$id}/muat-turun")->assertNotFound();
    }

    public function test_SRS_LAP_06_scheduled_report_is_generated_per_recipient_scope_and_sent_through_ntf(): void
    {
        Carbon::setTestNow('2026-05-04 06:00:00'); // Monday
        $this->complaint('100803');
        $this->complaint('100901');
        $owner = $this->user();
        $a = $this->user(districts: ['1008']);
        $b = $this->user(districts: ['1009']);

        $id = $this->actingAs($owner)->postJson('/api/v1/lap/jadual', [
            'nama' => 'Aduan mingguan', 'kod_laporan' => 'OPS-02', 'kekerapan' => 'mingguan', 'masa_jalan' => '07:00', 'hari_jalan' => 1,
            'format' => 'pdf', 'senarai_penerima' => [$a->id, $b->id],
        ])->assertCreated()->json('data.id');
        $this->assertSame('2026-05-04 07:00:00', JadualPenjanaan::query()->findOrFail($id)->jalan_berikutnya?->format('Y-m-d H:i:s'));

        $this->artisan('lap:jadual')->assertSuccessful();
        $this->assertCount(0, $this->notifier->sent, 'not due yet');

        Carbon::setTestNow('2026-05-04 07:01:00');
        $this->artisan('lap:jadual')->assertSuccessful();
        $this->assertCount(2, $this->notifier->sent);
        $this->assertSame(['lap.laporan_berjadual', 'lap.laporan_berjadual'], array_column($this->notifier->sent, 'type'));
        $logs = LogPenjanaan::query()->where('id_jadual', $id)->get()->keyBy('id_pengguna');
        $this->assertSame(1, $logs[$a->id]->bilangan_rekod);
        $this->assertSame(1, $logs[$b->id]->bilangan_rekod);
        $this->assertSame('berjadual', $logs[$a->id]->mod);
        Storage::disk('local')->assertExists((string) $logs[$b->id]->laluan_fail);
        $j = JadualPenjanaan::query()->findOrFail($id);
        $this->assertSame('2026-05-11 07:00:00', $j->jalan_berikutnya?->format('Y-m-d H:i:s'));
        $this->assertSame('berjaya', $j->status_terakhir);

        // A recipient whose access was later removed is skipped, not sent another scope.
        $b->forceFill(['is_active' => false])->save();
        Carbon::setTestNow('2026-05-11 07:05:00');
        $this->artisan('lap:jadual')->assertSuccessful();
        $this->assertCount(3, $this->notifier->sent);
        $this->assertSame('separa', $j->refresh()->status_terakhir);
        Carbon::setTestNow();
    }

    public function test_SRS_LAP_06_recipients_without_access_are_refused_at_save(): void
    {
        $owner = $this->user();
        $noFinance = $this->user(['lap.laporan.lihat', 'pro.lot.lihat']);
        $this->actingAs($owner)->postJson('/api/v1/lap/jadual', [
            'nama' => 'Tunggakan', 'kod_laporan' => 'KEW-02', 'kekerapan' => 'bulanan', 'masa_jalan' => '08:00', 'hari_jalan' => 1, 'format' => 'xlsx',
            'senarai_penerima' => [$noFinance->id],
        ])->assertStatus(422)->assertJsonValidationErrors('senarai_penerima');

        $this->actingAs($this->user(['lap.laporan.lihat', 'pro.lot.lihat']))->getJson('/api/v1/lap/jadual')->assertForbidden();
    }

    public function test_SRS_LAP_06_next_run_for_every_frequency(): void
    {
        $at = Carbon::parse('2026-05-20 10:00:00'); // Wednesday
        $j = fn (string $k, ?int $d) => new JadualPenjanaan(['kekerapan' => $k, 'masa_jalan' => '07:30', 'hari_jalan' => $d]);
        $this->assertSame('2026-05-21 07:30', $j('harian', null)->nextRun($at)->format('Y-m-d H:i'));
        $this->assertSame('2026-05-25 07:30', $j('mingguan', 1)->nextRun($at)->format('Y-m-d H:i'));
        $this->assertSame('2026-06-01 07:30', $j('bulanan', 1)->nextRun($at)->format('Y-m-d H:i'));
        $this->assertSame('2026-05-28 07:30', $j('bulanan', 28)->nextRun($at)->format('Y-m-d H:i'));
        $this->assertSame('2026-07-01 07:30', $j('suku_tahun', 1)->nextRun($at)->format('Y-m-d H:i'));
    }
}
