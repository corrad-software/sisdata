<?php

namespace Tests\Modules\Adm;

use App\Models\User;
use App\Modules\Adm\Services\Logging\DatabaseLogHandler;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Modules\Adm\Fakes\FakeAccessControl;
use Tests\Modules\Adm\Fakes\SpyAuditLogger;
use Tests\Modules\Adm\Fakes\SpyNotifier;
use Tests\TestCase;

abstract class AdmTestCase extends TestCase
{
    use RefreshDatabase;

    protected SpyNotifier $notifier;

    protected SpyAuditLogger $audit;

    protected FakeAccessControl $access;

    protected function setUp(): void
    {
        parent::setUp();

        DatabaseLogHandler::resetState();
        $this->notifier = new SpyNotifier;
        $this->audit = new SpyAuditLogger;
        $this->access = new FakeAccessControl;
        $this->app->instance(Notifier::class, $this->notifier);
        $this->app->instance(AuditLogger::class, $this->audit);
        $this->app->instance(AccessControl::class, $this->access);
    }

    /** @param list<string> $permissions */
    protected function userWith(array $permissions, array $attributes = []): User
    {
        $user = User::factory()->create(['role' => 'user', 'is_active' => true, ...$attributes]);
        $this->access->permissions[$user->id] = $permissions;

        return $user;
    }

    /** Pentadbir Sistem: every permission. */
    protected function admin(array $attributes = []): User
    {
        return $this->userWith(['*'], $attributes);
    }
}
