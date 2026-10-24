<?php

namespace Tests\Modules\Kes;

use App\Modules\Kes\Models\KesMahkamah;
use App\Modules\Kes\Services\KesService;
use App\Modules\Kes\Support\Aliran;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Services\Engine;
use Database\Seeders\KesAliranSeeder;
use Illuminate\Support\Facades\DB;

class SampelAliranTest extends KesTestCase
{
    public function test_SRS_GEN_03_kes_flows_seeded_active_in_the_real_workflow_engine(): void
    {
        $author = $this->user(KesTestCase::KETUA);
        $this->seed(KesAliranSeeder::class);
        $this->seed(KesAliranSeeder::class); // idempotent
        foreach (array_keys(Aliran::FLOWS) as $flow) {
            $this->assertSame(1, DB::table('wf_definisi')->where('kod_aliran', $flow)->where('status', 'aktif')->count(), $flow);
        }
        $this->app->instance(WorkflowEngine::class, app(Engine::class));
        $k = $this->kes([$this->lot()], $author);
        $id = app(KesService::class)->mohonPemfailan($k->refresh(), $author);
        $this->assertSame('berjalan', app(WorkflowEngine::class)->status($id));
        $this->assertSame($id, app(WorkflowEngine::class)->activeInstanceFor('kes_mahkamah', $k->id));
    }

    public function test_kes_sample_generates_synthetic_cases_on_existing_lots(): void
    {
        $this->user(KesTestCase::KETUA);
        foreach (range(1, 6) as $i) {
            $this->lot($i % 2 ? '100803' : '100901');
        }
        $this->artisan('kes:sample', ['--count' => 8, '--seed' => 42])->assertSuccessful();
        $cases = KesMahkamah::query()->withoutGlobalScope('access')->get();
        $this->assertCount(8, $cases);
        $this->assertTrue($cases->every(fn (KesMahkamah $k) => $k->sintetik));
        $this->assertSame(0, DB::table('kes_pihak')->where('sintetik', false)->count());
        $this->assertGreaterThan(0, DB::table('kes_mahkamah_lot')->count());
        $this->assertGreaterThan(0, DB::table('kos_guaman')->where('sintetik', true)->count());
        $this->assertSame([], $this->workflow->started, 'no workflow started for synthetic data');
    }
}
