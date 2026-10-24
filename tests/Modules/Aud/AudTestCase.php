<?php

namespace Tests\Modules\Aud;

use App\Models\User;
use App\Modules\Aud\AudServiceProvider;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Aud\Services\AuditWriter;
use App\Modules\Aud\Services\Siem\SiemSink;
use App\Modules\Aud\Services\TrailAuditLogger;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Modules\Aud\Concerns\AssertsAuditTrail;
use Tests\Modules\Aud\Concerns\FakeAccessControl;
use Tests\TestCase;

abstract class AudTestCase extends TestCase
{
    use AssertsAuditTrail, RefreshDatabase;

    protected FakeAccessControl $access;

    protected string $scratch;

    /**
     * Last no_urutan before the test started. Other modules write audit rows
     * outside the test transaction (e.g. MOD-RBC's permission-catalogue sync
     * after `migrate`), so tests look only at rows past this point.
     */
    protected int $baseline = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scratch = storage_path('framework/testing/aud-'.getmypid().'-'.uniqid());
        File::ensureDirectoryExists($this->scratch);
        config([
            'aud.siem.pemacu' => 'jsonl',
            'aud.siem.laluan' => $this->scratch.'/siem.jsonl',
            'aud.siem.laluan_cef' => $this->scratch.'/siem.cef',
            'aud.laluan_spool' => $this->scratch.'/spool.jsonl',
            'aud.laluan_insiden' => $this->scratch.'/insiden.log',
        ]);
        $this->app->forgetInstance(SiemSink::class);
        $this->app->forgetInstance(AuditWriter::class);
        $this->app->forgetInstance(TrailAuditLogger::class);
        $this->app->forgetInstance(AuditLogger::class);

        $this->access = new FakeAccessControl;
        $this->app->instance(AccessControl::class, $this->access);

        $this->baseline = (int) DB::table('audit_rekod')->max('no_urutan');
    }

    /** audit_rekod rows written during this test. */
    protected function newAuditRows(): Builder
    {
        return DB::table('audit_rekod')->where('no_urutan', '>', $this->baseline);
    }

    /** no_urutan of the n-th audit record written during this test (1-based). */
    protected function seq(int $n): int
    {
        return $this->baseline + $n;
    }

    protected function tearDown(): void
    {
        AuditWriter::$simulateFailure = false;
        File::deleteDirectory($this->scratch);

        parent::tearDown();
    }

    /** A Juruaudit with every audit permission (or the given ones). */
    protected function auditor(?array $permissions = null, array $roles = ['juruaudit']): User
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->access->grant($user, $permissions ?? AudServiceProvider::PERMISSIONS, $roles);

        return $user;
    }
}
