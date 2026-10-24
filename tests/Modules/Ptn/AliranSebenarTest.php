<?php

namespace Tests\Modules\Ptn;

use App\Modules\Ptn\Models\IsuPemantauan;
use App\Modules\Ptn\Models\JadualPemantauan;
use App\Modules\Ptn\Models\LaporanLawatan;
use App\Modules\Ptn\Models\RekodLawatan;
use App\Modules\Ptn\Models\RiskRatingLot;
use App\Modules\Ptn\Support\AliranPtn;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Models\Kumpulan;
use App\Modules\Wfe\Models\Tugasan;
use App\Modules\Wfe\Services\Engine;
use App\Modules\Wfe\Support\WfeException;
use Database\Seeders\PtnAliranSeeder;
use Illuminate\Support\Facades\DB;

/** End to end against the real MOD-WFE engine + the synthetic data command. */
class AliranSebenarTest extends PtnTestCase
{
    public function test_SRS_PTN_10_seeded_flow_validates_report_and_officer_cannot_validate_own_visit(): void
    {
        $this->app->forgetInstance(WorkflowEngine::class);
        $this->app->bind(WorkflowEngine::class, Engine::class);
        $me = $this->user([...self::LAPANGAN, 'wfe.tugasan.lihat', 'wfe.tugasan.lulus'], ['1008']);
        $do = $this->user(['wfe.tugasan.lihat', 'wfe.tugasan.lulus', 'ptn.lawatan.lihat'], ['1008']);

        $this->seed(PtnAliranSeeder::class);
        $this->seed(PtnAliranSeeder::class); // idempotent
        $this->assertSame(3, DB::table('wf_definisi')->where('modul', 'ptn')->count());
        Kumpulan::query()->where('kod', AliranPtn::KUMPULAN_PENGESAH)->firstOrFail()->ahli()->sync([$me->id, $do->id]);

        $v = $this->lawatanLengkap($me, $this->jadual($this->lot(), $me));
        $r = LaporanLawatan::query()->withoutGlobalScope('access')->where('id_lawatan', $v->id)->sole();
        $this->assertNotNull($r->id_aliran);

        $engine = app(Engine::class);
        $task = fn () => Tugasan::query()->where('id_instans', $r->id_aliran)->where('status', 'menunggu')->firstOrFail();
        try {
            $engine->decide($task(), $me, 'lulus', 'Saya sahkan sendiri');
            $this->fail('Officer validated own report');
        } catch (WfeException $e) {
            $this->assertSame('pengasingan_tugas', $e->errorCode);
        }
        $engine->decide($task(), $do, 'lulus', 'Laporan lengkap');
        $this->assertTrue((bool) $r->refresh()->status_kunci);
        $this->assertSame('disahkan', RekodLawatan::query()->withoutGlobalScope('access')->findOrFail($v->id)->status_pengesahan);
    }

    public function test_sample_command_generates_flagged_synthetic_monitoring_data(): void
    {
        foreach (range(1, 6) as $i) {
            $this->lot($i % 2 ? '100803' : '100901');
        }
        $this->user(self::LAPANGAN);
        $this->user(self::PENYELIA);
        $this->artisan('ptn:sample', ['--count' => 8, '--seed' => 3])->assertSuccessful();

        $this->assertGreaterThan(0, JadualPemantauan::query()->withoutGlobalScope('access')->where('sintetik', true)->count());
        $this->assertSame(8, RekodLawatan::query()->withoutGlobalScope('access')->where('sintetik', true)->count());
        $this->assertSame(0, RekodLawatan::query()->withoutGlobalScope('access')->where('sintetik', false)->count());
        $this->assertSame(0, IsuPemantauan::query()->withoutGlobalScope('access')->where('sintetik', false)->count());
        $this->assertGreaterThan(6, RiskRatingLot::query()->withoutGlobalScope('access')->count(), 'sejarah risiko untuk trend');
    }
}
