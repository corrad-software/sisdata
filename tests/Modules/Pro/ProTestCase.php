<?php

namespace Tests\Modules\Pro;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Pro\Models\LotTanah;
use App\Modules\Pro\Services\LotService;
use App\Modules\Pro\Services\SpatialService;
use App\Modules\Ref\Database\seeders\RefSeeder;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Modules\Aud\Concerns\AssertsAuditTrail;
use Tests\Modules\Pro\Support\ArrayParameters;
use Tests\Modules\Pro\Support\FakeDocumentStore;
use Tests\Modules\Pro\Support\FakeWorkflowEngine;
use Tests\Modules\Pro\Support\SpyNotifier;
use Tests\TestCase;

/**
 * MOD-PRO tests run against the real RBC (roles, permissions, pengguna_skop)
 * and the real AUD trail; WFE, NTF, DMS and ADM parameters are fakes so a test
 * can play the second officer, read notifications and change tunables.
 */
abstract class ProTestCase extends TestCase
{
    use AssertsAuditTrail, RefreshDatabase;

    public const PEGAWAI = ['pro.lot.lihat', 'pro.lot.cipta', 'pro.lot.kemas_kini', 'pro.lot.eksport', 'pro.spatial.cipta', 'pro.import.cipta'];

    protected FakeWorkflowEngine $workflow;

    protected SpyNotifier $notifier;

    protected ArrayParameters $params;

    protected FakeDocumentStore $documents;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(WorkflowEngine::class, $this->workflow = new FakeWorkflowEngine);
        $this->app->instance(Notifier::class, $this->notifier = new SpyNotifier);
        $this->app->instance(Parameters::class, $this->params = new ArrayParameters);
        $this->app->instance(DocumentStore::class, $this->documents = new FakeDocumentStore);

        $seeder = $this->app->make(RefSeeder::class);
        $seeder->withSpatial = false;
        $seeder->setContainer($this->app)->__invoke();
    }

    /**
     * @param  list<string>  $permissions
     * @param  list<string>|null  $daerah
     */
    protected function officer(array $permissions = self::PEGAWAI, ?array $daerah = null): User
    {
        $factory = User::factory()->withPermissions($permissions);
        if ($daerah !== null) {
            $factory = $factory->withScope('daerah', $daerah);
        }

        return $factory->create();
    }

    /** @return array<string, mixed> */
    protected function lotData(array $overrides = []): array
    {
        return [
            'no_lot' => 'Lot 1234',
            'kod_mukim' => '100803',
            'keluasan' => 1.25,
            'unit_keluasan' => 'hektar',
            'kategori_guna_tanah' => 'PERTANIAN',
            'bahagian_pemilik' => 'wakaf',
            ...$overrides,
        ];
    }

    protected function makeLot(array $overrides = [], ?User $by = null): LotTanah
    {
        return app(LotService::class)->create($this->lotData($overrides), $by);
    }

    /** Square polygon WKT (WGS84) of $sizeDeg degrees at lng/lat. */
    protected function square(float $lng = 101.60, float $lat = 3.10, float $size = 0.001): string
    {
        $x2 = $lng + $size;
        $y2 = $lat + $size;

        return "POLYGON(({$lng} {$lat}, {$x2} {$lat}, {$x2} {$y2}, {$lng} {$y2}, {$lng} {$lat}))";
    }

    protected function addGeometry(LotTanah $lot, string $wkt, ?User $by = null, string $source = 'juruukur'): int
    {
        $service = app(SpatialService::class);

        return (int) $service->store($lot, $service->parse('wkt', $wkt, 4326), ['sumber_data' => $source], $by)['spatial']->id;
    }

    protected function completeWorkflow(string $flow, string $subjectType, string $subjectId, string $outcome, ?int $decidedBy): void
    {
        event(new WorkflowCompleted('wf-x', $flow, $subjectType, $subjectId, $outcome, $decidedBy, 'Catatan ujian'));
    }
}
