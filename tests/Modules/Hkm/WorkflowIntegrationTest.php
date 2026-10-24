<?php

namespace Tests\Modules\Hkm;

use App\Modules\Hkm\Models\PerubahanHakMilik;
use App\Modules\Hkm\Support\AliranKelulusan;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Models\Definisi;
use App\Modules\Wfe\Models\Kumpulan;
use App\Modules\Wfe\Models\Tugasan;
use App\Modules\Wfe\Services\Engine;
use App\Modules\Wfe\Support\WfeException;
use Database\Seeders\HkmAliranSeeder;

/**
 * SRS-HKM-19 end to end against the real MOD-WFE engine: the seeded
 * `hkm.perubahan_hak_milik` flow has two stages, both perlu_mfa; the change is
 * applied only after both approvals, each with a fresh MFA step-up.
 */
class WorkflowIntegrationTest extends HkmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->forgetInstance(WorkflowEngine::class); // use the real engine
        $this->app->bind(WorkflowEngine::class, Engine::class);
    }

    public function test_SRS_HKM_19_seeded_flow_has_two_mfa_stages_and_applies_only_after_both(): void
    {
        $requester = $this->user();
        $reviewer = $this->user(['wfe.tugasan.lihat', 'wfe.tugasan.lulus']);
        $head = $this->user(['wfe.tugasan.lihat', 'wfe.tugasan.lulus']);

        $this->seed(HkmAliranSeeder::class);
        Kumpulan::query()->where('kod', AliranKelulusan::KUMPULAN_PENYEMAK)->firstOrFail()->ahli()->sync([$reviewer->id]);
        Kumpulan::query()->where('kod', AliranKelulusan::KUMPULAN_PELULUS)->firstOrFail()->ahli()->sync([$head->id]);

        $definition = Definisi::query()->where('kod_aliran', 'hkm.perubahan_hak_milik')->where('status', Definisi::AKTIF)->firstOrFail();
        $stages = $definition->peringkat()->orderBy('no_urutan')->get();
        $this->assertCount(2, $stages);
        $this->assertTrue($stages->every(fn ($s) => (bool) $s->perlu_mfa));

        $title = $this->title(['pemilik_berdaftar' => 'Pemilik Lama']);
        $changeId = $this->actingAs($requester)->postJson("/api/v1/hkm/hak-milik/{$title->id}/perubahan", [
            'jenis_perubahan' => 'pemilik', 'data' => ['pemilik_baharu' => 'Majlis Agama Islam Selangor', 'tarikh_pertukaran' => '2026-10-01', 'sebab_pertukaran' => 'Hibah'],
            'justifikasi' => 'Surat hibah bertarikh 1 Okt 2026',
        ])->assertCreated()->json('data.id');
        $instanceId = PerubahanHakMilik::query()->findOrFail($changeId)->id_instans_aliran;
        $this->assertNotNull($instanceId);

        $engine = app(Engine::class);
        $task = fn () => Tugasan::query()->where('id_instans', $instanceId)->where('status', 'menunggu')->firstOrFail();

        // Stage 1 without MFA is refused.
        try {
            $engine->decide($task(), $reviewer, 'lulus', 'Semakan lengkap', ['mfa_at' => null]);
            $this->fail('Approved without MFA');
        } catch (WfeException $e) {
            $this->assertSame('mfa_diperlukan', $e->errorCode);
        }

        $engine->decide($task(), $reviewer, 'lulus', 'Semakan lengkap', ['mfa_at' => now()]);
        $this->assertSame('Pemilik Lama', $title->refresh()->pemilik_berdaftar); // one stage is not enough

        $engine->decide($task(), $head, 'lulus', 'Diluluskan', ['mfa_at' => now()]);
        $this->assertSame('Majlis Agama Islam Selangor', $title->refresh()->pemilik_berdaftar);
        $this->assertSame('diluluskan', PerubahanHakMilik::query()->findOrFail($changeId)->status);
        $this->assertSame($head->id, PerubahanHakMilik::query()->findOrFail($changeId)->diputuskan_oleh);
    }
}
