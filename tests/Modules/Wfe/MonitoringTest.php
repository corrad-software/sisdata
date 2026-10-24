<?php

namespace Tests\Modules\Wfe;

use App\Models\User;
use App\Modules\Wfe\Contracts\SubjectLinks;
use App\Modules\Wfe\Contracts\WorkflowFlows;
use App\Modules\Wfe\Models\Tugasan;
use Illuminate\Support\Carbon;

/** SRS-WFE-13 (dashboard), 15 (drill-down), 23 (performance report). */
class MonitoringTest extends WfeTestCase
{
    private User $officer;

    private User $director;

    private User $applicant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->officer = $this->user();
        $this->director = $this->user();
        $this->applicant = $this->user();
        $this->activeFlow($this->structure('ujian.pantau', [
            $this->stage('semakan', [$this->officer], ['sla_nilai' => 1, 'eskalasi' => [
                ['aras' => 1, 'tempoh_nilai' => 0, 'tempoh_unit' => 'jam', 'jenis_penerima' => 'individu', 'nilai_penerima' => '1'],
            ]]),
            $this->stage('kelulusan', [$this->director], ['sla_nilai' => 1]),
        ]));
    }

    private function start(string $record, string $district): string
    {
        return $this->engine()->start('ujian.pantau', 'rekod_ujian', $record, $this->applicant, ['kod_daerah' => $district]);
    }

    private function approve(User $user, string $id): void
    {
        $task = Tugasan::where('id_instans', $id)->where('status', 'menunggu')->firstOrFail();
        $this->act($user, $task->id, 'lulus')->assertOk();
    }

    /** Acceptance criterion 6 (counts) + district scope. */
    public function test_SRS_WFE_13_dashboard_counts_match_test_transactions(): void
    {
        $done = $this->start('1', '10');
        Carbon::setTestNow(now()->addHours(2));
        $this->approve($this->officer, $done);
        Carbon::setTestNow(now()->addHours(2));
        $this->approve($this->director, $done); // 4 hours end to end
        $this->start('2', '10'); // pending at semakan
        $atDirector = $this->start('3', '11');
        $this->approve($this->officer, $atDirector); // pending at kelulusan

        $monitor = $this->user(['wfe.pemantauan.lihat']);
        $data = $this->actingAs($monitor)->getJson('/api/v1/wfe/pemantauan')->assertOk()->json('data');

        $this->assertSame(2, $data['aktif']);
        $this->assertSame(1, $data['ikut_status']['lulus']);
        $this->assertSame(2, $data['tugasan_tertangguh']);
        $byStage = collect($data['ikut_peringkat'])->pluck('jumlah', 'peringkat')->all();
        $this->assertEqualsCanonicalizing(['semakan' => 1, 'kelulusan' => 1], $byStage);
        $this->assertSame(4.0, (float) $data['purata_penyelesaian'][0]['purata_jam']);
        $this->assertCount(2, $data['ikut_pelulus']);
        $this->assertNotEmpty($data['tindakan_terkini']);

        // a district-10 officer sees only district 10
        $scoped = $this->user(['wfe.pemantauan.lihat'], [], ['10']);
        $scopedData = $this->actingAs($scoped)->getJson('/api/v1/wfe/pemantauan')->assertOk()->json('data');
        $this->assertSame(1, $scopedData['aktif']);
        $this->assertSame(1, $scopedData['tugasan_tertangguh']);
        $list = $this->actingAs($scoped)->getJson('/api/v1/wfe/instans')->assertOk()->json('data');
        $this->assertSame(['10', '10'], array_column($list, 'kod_daerah'));
        $this->actingAs($scoped)->getJson("/api/v1/wfe/instans/{$atDirector}")->assertForbidden();

        $this->actingAs($this->user([]))->getJson('/api/v1/wfe/pemantauan')->assertForbidden();
    }

    public function test_SRS_WFE_15_drill_down_link_to_source_record(): void
    {
        app(WorkflowFlows::class)->declare('ujian.pantau', 'Ujian', 'tst', 'rekod_ujian');
        app(SubjectLinks::class)->register('rekod_ujian', '/admin/tst/rekod/{id}');
        $viaPattern = $this->start('R-9', '10');
        $viaContext = $this->engine()->start('ujian.pantau', 'rekod_ujian', 'R-10', $this->applicant, ['pautan' => '/admin/pjk/kontrak/55']);
        $unsafe = $this->engine()->start('ujian.pantau', 'rekod_ujian', 'R-11', $this->applicant, ['pautan' => 'https://evil.example.com']);

        $monitor = $this->user(['wfe.pemantauan.lihat']);
        $this->actingAs($monitor)->getJson("/api/v1/wfe/instans/{$viaPattern}")->assertOk()
            ->assertJsonPath('data.pautan', '/admin/tst/rekod/R-9')->assertJsonPath('data.id_rekod', 'R-9');
        $this->actingAs($monitor)->getJson("/api/v1/wfe/instans/{$viaContext}")->assertJsonPath('data.pautan', '/admin/pjk/kontrak/55');
        $this->actingAs($monitor)->getJson("/api/v1/wfe/instans/{$unsafe}")->assertJsonPath('data.pautan', '/admin/tst/rekod/R-11');

        // the dashboard filter by stage drills into the list
        $this->actingAs($monitor)->getJson('/api/v1/wfe/instans?peringkat=semakan')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_SRS_WFE_23_performance_report_per_stage_bottleneck_escalation_backlog(): void
    {
        $a = $this->start('1', '10');
        Carbon::setTestNow(now()->addHours(3));
        $this->approve($this->officer, $a);           // semakan: 3h
        Carbon::setTestNow(now()->addHours(1));
        $this->approve($this->director, $a);          // kelulusan: 1h
        $this->start('2', '10');                      // pending semakan, will escalate
        Carbon::setTestNow(Carbon::parse('2026-11-04 09:00', 'Asia/Kuala_Lumpur'));
        $this->artisan('wfe:semak-sla');

        $viewer = $this->user(['wfe.laporan.lihat']);
        $report = $this->actingAs($viewer)->getJson('/api/v1/wfe/laporan?dari=2026-11-01&hingga=2026-11-30')->assertOk()->json('data');

        $stages = collect($report['ikut_peringkat'])->keyBy('peringkat');
        $this->assertSame(3.0, (float) $stages['semakan']['purata_jam']);
        $this->assertSame(1.0, (float) $stages['kelulusan']['purata_jam']);
        $this->assertSame(1, $stages['semakan']['tertunggak']);
        $this->assertSame('semakan', $report['kesesakan'][0]['peringkat']);
        $this->assertSame(3, $report['eskalasi']['jumlah_tugasan']);
        $this->assertSame(1, $report['eskalasi']['dieskalasi']);
        $this->assertEqualsWithDelta(33.3, $report['eskalasi']['kadar_peratus'], 0.1);
        $this->assertSame([1 => 1], $report['eskalasi']['ikut_aras']);
        $this->assertSame('individu:'.$this->officer->id, $report['tertunggak_ikut_pelulus'][0]['penerima']);
        $this->assertSame(1, $report['tertunggak_ikut_pelulus'][0]['lewat']);

        $this->actingAs($this->user(['wfe.pemantauan.lihat']))->getJson('/api/v1/wfe/laporan')->assertForbidden();
    }
}
