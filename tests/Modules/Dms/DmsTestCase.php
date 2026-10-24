<?php

namespace Tests\Modules\Dms;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Dms\Jobs\IndeksKandunganDokumen;
use App\Modules\Dms\Models\Dokumen;
use App\Modules\Dms\Services\Crypto\EnvelopeEncryptor;
use App\Modules\Dms\Services\Scanning\FakeVirusScanner;
use App\Modules\Dms\Services\Scanning\VirusScanner;
use App\Modules\Dms\Services\Storage\DocumentVault;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Modules\Dms\Support\ArrayParameters;
use Tests\Modules\Dms\Support\FakeAccessControl;
use Tests\Modules\Dms\Support\FakeWorkflowEngine;
use Tests\Modules\Dms\Support\SpyAuditLogger;
use Tests\Modules\Dms\Support\SpyNotifier;
use Tests\TestCase;

abstract class DmsTestCase extends TestCase
{
    use RefreshDatabase;

    protected FakeAccessControl $acl;

    protected SpyAuditLogger $audit;

    protected SpyNotifier $notifier;

    protected FakeVirusScanner $scanner;

    protected FakeWorkflowEngine $workflow;

    protected ArrayParameters $params;

    /** Everyday officer: view + upload + edit. */
    public const PEGAWAI = ['dms.dokumen.lihat', 'dms.dokumen.cipta', 'dms.dokumen.kemas_kini'];

    protected function setUp(): void
    {
        parent::setUp();

        config(['dms.encryption_key' => 'base64:'.base64_encode(str_repeat('k', 32))]);
        Storage::fake('dokumen');
        Queue::fake([IndeksKandunganDokumen::class]);

        $this->app->instance(AccessControl::class, $this->acl = new FakeAccessControl);
        $this->app->instance(AuditLogger::class, $this->audit = new SpyAuditLogger);
        $this->app->instance(Notifier::class, $this->notifier = new SpyNotifier);
        $this->app->instance(VirusScanner::class, $this->scanner = new FakeVirusScanner);
        $this->app->instance(WorkflowEngine::class, $this->workflow = new FakeWorkflowEngine);
        $this->app->instance(Parameters::class, $this->params = new ArrayParameters);
        // Singletons built on the real encryptor key must see the test key.
        $this->app->forgetInstance(EnvelopeEncryptor::class);
        $this->app->forgetInstance(DocumentVault::class);
    }

    /** @param list<string> $permissions */
    protected function userWith(array $permissions = self::PEGAWAI): User
    {
        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $this->acl->grant($user, ...$permissions);

        return $user;
    }

    /**
     * @param  UploadedFile|list<UploadedFile>  $files
     * @param  array<string, mixed>  $fields
     */
    protected function upload(User $user, UploadedFile|array $files, array $fields = []): TestResponse
    {
        return $this->actingAs($user)->post('/api/v1/dms/dokumen', [
            'fail' => is_array($files) ? $files : [$files],
            'kategori' => 'laporan',
            ...$fields,
        ], ['Accept' => 'application/json']);
    }

    /** Upload one file and return the new document. */
    protected function uploadDoc(User $user, ?UploadedFile $file = null, array $fields = []): Dokumen
    {
        $response = $this->upload($user, $file ?? Support\TestFiles::pdf(), $fields);
        $response->assertCreated();

        return Dokumen::query()->findOrFail($response->json('keputusan.0.dokumen.id'));
    }

    /** Run the (faked) indexing job for a document now. */
    protected function index(Dokumen $doc): Dokumen
    {
        $this->app->call([new IndeksKandunganDokumen($doc->id, $doc->versi_semasa), 'handle']);

        return $doc->refresh();
    }
}
