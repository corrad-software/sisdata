<?php

namespace Tests\Modules\Adu;

use App\Modules\Adu\Models\Aduan;
use App\Modules\Adu\Models\PeraturanPenugasan;
use App\Modules\Adu\Reminders\LawatanTapakAduan;
use App\Modules\Adu\Support\AliranPenutupan;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Models\Kumpulan;
use App\Modules\Wfe\Models\Tugasan;
use App\Modules\Wfe\Services\Engine;
use App\Modules\Wfe\Support\WfeException;
use Carbon\CarbonImmutable;
use Database\Seeders\AduAliranSeeder;
use Illuminate\Support\Facades\DB;

/**
 * SRS-ADU-19 end to end against the real MOD-WFE engine: the seeded
 * `adu.penutupan` flow; the investigating officer can never approve their
 * own closure (segregation of duties enforced by WFE); the supervisor can.
 */
class AliranPenutupanTest extends AduTestCase
{
    public function test_SRS_ADU_19_seeded_flow_enforces_segregation_between_investigator_and_approver(): void
    {
        $this->app->forgetInstance(WorkflowEngine::class);
        $this->app->bind(WorkflowEngine::class, Engine::class);

        $officer = $this->user([...self::PEGAWAI, 'wfe.tugasan.lihat', 'wfe.tugasan.lulus']);
        $supervisor = $this->user(['wfe.tugasan.lihat', 'wfe.tugasan.lulus']);
        PeraturanPenugasan::query()->create(['id_pegawai' => $officer->id]);

        $this->seed(AduAliranSeeder::class);
        Kumpulan::query()->where('kod', AliranPenutupan::KUMPULAN_PENYELIA)->firstOrFail()->ahli()->sync([$officer->id, $supervisor->id]);

        $aduan = $this->aduan(['lot_id' => $this->lot()]);
        $this->actingAs($officer)->postJson("/api/v1/adu/aduan/{$aduan->id}/penutupan", [
            'kategori_resolusi' => 'selesai_tindakan', 'keterangan_resolusi' => 'Struktur haram telah dirobohkan.', 'justifikasi' => 'Disahkan melalui lawatan tapak.',
        ])->assertOk();
        $instance = Aduan::query()->findOrFail($aduan->id)->id_instans_aliran;
        $this->assertNotNull($instance);

        $engine = app(Engine::class);
        $task = fn () => Tugasan::query()->where('id_instans', $instance)->where('status', 'menunggu')->firstOrFail();
        try {
            $engine->decide($task(), $officer, 'lulus', 'Saya luluskan sendiri');
            $this->fail('Investigator approved own closure');
        } catch (WfeException $e) {
            $this->assertSame('pengasingan_tugas', $e->errorCode);
        }
        $this->assertSame('menunggu_kelulusan', Aduan::query()->findOrFail($aduan->id)->status);

        $engine->decide($task(), $supervisor, 'lulus', 'Resolusi mencukupi');
        $closed = Aduan::query()->findOrFail($aduan->id);
        $this->assertSame('ditutup', $closed->status);
        $this->assertSame($supervisor->id, DB::table('resolusi_aduan')->where('aduan_id', $aduan->id)->value('id_penyelia_pelulus'));
    }

    public function test_SRS_ADU_17_site_visit_reminder_goes_to_the_officer_the_day_before(): void
    {
        $officer = $this->user(self::PEGAWAI);
        PeraturanPenugasan::query()->create(['id_pegawai' => $officer->id]);
        $aduan = $this->aduan();
        $this->actingAs($officer)->postJson("/api/v1/adu/aduan/{$aduan->id}/lawatan", ['tarikh_jadual' => now()->addDay()->toDateString()])->assertCreated();

        $due = iterator_to_array(app(LawatanTapakAduan::class)->due(1, CarbonImmutable::today()), false);
        $this->assertCount(1, $due);
        $this->assertSame($officer->id, $due[0]->recipients->id);
        $this->assertSame($aduan->no_rujukan_aduan, $due[0]->data['no_rujukan']);
    }

    public function test_sample_command_generates_flagged_synthetic_complaints(): void
    {
        $this->lot();
        $this->lot('100901');
        $this->user(self::PEGAWAI);
        $this->artisan('adu:sample', ['--count' => 12, '--seed' => 7])->assertSuccessful();

        $this->assertSame(12, Aduan::query()->count());
        $this->assertSame(12, Aduan::query()->where('sintetik', true)->count());
        $this->assertSame(0, DB::table('pengadu')->where('sintetik', false)->count());
        $this->assertGreaterThan(1, Aduan::query()->distinct()->count('status'));
        $this->assertGreaterThan(0, Aduan::query()->where('status', 'ditutup')->count(), 'some complaints are closed');
    }
}
