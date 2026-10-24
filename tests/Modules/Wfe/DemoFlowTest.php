<?php

namespace Tests\Modules\Wfe;

use App\Models\User;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Contracts\WorkflowFlows;
use App\Modules\Wfe\Database\Seeders\WfeSeeder;
use App\Modules\Wfe\Models\Instans;
use App\Modules\Wfe\Models\Tugasan;
use App\Modules\Wfe\Services\Engine;
use App\Modules\Wfe\Services\FlowRegistry;

/** The seeded `contoh.kelulusan` demo, end to end through the API. */
class DemoFlowTest extends WfeTestCase
{
    public function test_SRS_WFE_03_contracts_bound_and_catalogue_declared(): void
    {
        $this->assertInstanceOf(Engine::class, app(WorkflowEngine::class));
        $this->assertInstanceOf(FlowRegistry::class, app(WorkflowFlows::class));
        $this->assertSame(app(WorkflowFlows::class), app(WorkflowFlows::class));
        $this->assertSame('contoh_permohonan', app(FlowRegistry::class)->get('contoh.kelulusan')['jenis_subjek']);
        /** @var PermissionRegistry $registry */
        $registry = app(PermissionRegistry::class);
        $this->assertArrayHasKey('wfe.tugasan.lulus', $registry->all()['wfe']['permissions']);
    }

    public function test_SRS_WFE_20_seeded_demo_flow_runs_end_to_end(): void
    {
        // Run the seeder in-process (an Artisan db:seed call swaps the auth guard under actingAs).
        $this->app->make(WfeSeeder::class)->setContainer($this->app)->__invoke();
        $reviewer = User::where('email', 'siti.aminah@mais.test')->firstOrFail();
        $approver = User::where('email', 'nurul.huda@mais.test')->firstOrFail();
        foreach ([$reviewer, $approver] as $u) {
            $this->access->grant($u, ['wfe.tugasan.lihat', 'wfe.tugasan.lulus']);
        }
        $applicant = $this->user(['wfe.contoh.cipta', 'wfe.permohonan.lihat', 'wfe.tugasan.lihat']);

        $id = $this->actingAs($applicant)->postJson('/api/v1/wfe/contoh', ['tajuk' => 'Permohonan sewa tapak', 'nilai' => 25000, 'kod_daerah' => '10'])
            ->assertCreated()->json('data.id_instans');

        $this->act($reviewer, Tugasan::where('id_instans', $id)->where('status', 'menunggu')->value('id'), 'lulus')->assertOk();
        $critical = Tugasan::where('id_instans', $id)->where('status', 'menunggu')->value('id');
        $this->act($approver, $critical, 'lulus')->assertForbidden()->assertJsonPath('code', 'MFA_DIPERLUKAN');
        $this->withMfa()->act($approver, $critical, 'lulus')->assertOk();

        $this->assertSame(Instans::LULUS, Instans::find($id)->status);
        $this->app['session']->forget('password_hash_web');
        $this->actingAs($applicant)->getJson('/api/v1/wfe/permohonan')->assertJsonPath('data.0.status', 'lulus')
            ->assertJsonPath('data.0.pautan', '/admin/wfe/permohonan?rekod='.Instans::find($id)->id_rekod);
        $this->assertSame(1, Tugasan::where('id_instans', $id)->where('jenis', 'susulan')->count());
    }
}
