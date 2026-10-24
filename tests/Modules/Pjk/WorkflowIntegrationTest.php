<?php

namespace Tests\Modules\Pjk;

use App\Modules\Pjk\Models\LejarTransaksi;
use App\Modules\Pjk\Models\PremisAset;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Models\Definisi;
use App\Modules\Wfe\Models\Kumpulan;
use App\Modules\Wfe\Models\Tugasan;
use App\Modules\Wfe\Services\Engine;
use Database\Seeders\PjkAliranSeeder;
use Illuminate\Support\Facades\DB;

/**
 * SRS-PJK-05 / SRS-PJK-28 end to end against the real MOD-WFE engine with the
 * seeded default definitions: layered approval by bahagian (and value), and a
 * second officer for ledger adjustments.
 */
class WorkflowIntegrationTest extends PjkTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->forgetInstance(WorkflowEngine::class);
        $this->app->bind(WorkflowEngine::class, Engine::class);
        $this->user(); // the seeder needs an author
        $this->seed(PjkAliranSeeder::class);
    }

    public function test_SRS_PJK_05_seeded_layered_flow_routes_wakaf_contract_to_wakaf_head_then_activates(): void
    {
        $this->assertSame(5, Definisi::query()->where('modul', 'pjk')->where('status', Definisi::AKTIF)->count());
        $reviewer = $this->user(['wfe.tugasan.lihat', 'wfe.tugasan.lulus']);
        $wakafHead = $this->user(['wfe.tugasan.lihat', 'wfe.tugasan.lulus']);
        Kumpulan::query()->where('kod', 'pjk_penyemak')->firstOrFail()->ahli()->sync([$reviewer->id]);
        Kumpulan::query()->where('kod', 'pjk_ketua_wakaf')->firstOrFail()->ahli()->sync([$wakafHead->id]);

        $registrar = $this->user();
        $k = $this->draft($registrar); // lot bahagian wakaf, value RM36,000
        $this->actingAs($registrar)->postJson("/api/v1/pjk/kontrak/{$k->id}/hantar")->assertOk();
        $instance = (string) $k->refresh()->id_instans_aliran;
        $engine = app(Engine::class);
        $task = fn () => Tugasan::query()->where('id_instans', $instance)->where('status', 'menunggu')->firstOrFail();

        $engine->decide($task(), $reviewer, 'lulus', 'Dokumen lengkap', ['mfa_at' => now()]);
        $this->assertSame('dalam_kelulusan', $k->refresh()->status);
        $engine->decide($task(), $wakafHead, 'lulus', 'Diluluskan', ['mfa_at' => now()]);

        $this->assertSame('aktif', $k->refresh()->status);
        $this->assertSame('disewa', PremisAset::query()->withoutGlobalScope('access')->find($k->premis_id)?->status_premis);
    }

    public function test_SRS_PJK_28_seeded_adjustment_flow_requires_a_second_officer(): void
    {
        $finance = $this->user(['wfe.tugasan.lihat', 'wfe.tugasan.lulus']);
        Kumpulan::query()->where('kod', 'pjk_kewangan')->firstOrFail()->ahli()->sync([$finance->id]);
        $k = $this->active($this->user(), null, ['tarikh_mula' => '2026-10-01', 'tarikh_tamat' => '2027-09-30']);

        $requester = $this->user();
        $id = $this->actingAs($requester)->postJson("/api/v1/pjk/kontrak/{$k->id}/pelarasan", ['jenis' => 'pelarasan_kredit', 'amaun' => '100.00', 'justifikasi' => 'Pelarasan ujian kerana kesilapan kadar'])->assertCreated()->json('data.id');
        $instance = (string) DB::table('pelarasan_lejar')->where('id', $id)->value('id_instans_aliran');
        $task = Tugasan::query()->where('id_instans', $instance)->where('status', 'menunggu')->firstOrFail();
        app(Engine::class)->decide($task, $finance, 'lulus', 'Disahkan', ['mfa_at' => now()]);

        $row = LejarTransaksi::query()->where('pelarasan_id', $id)->sole();
        $this->assertSame([$finance->id, '100.00'], [$row->id_pegawai, $row->kredit]);
    }
}
