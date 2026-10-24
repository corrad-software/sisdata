<?php

namespace Tests\Modules\Ntf;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Ntf\Contracts\NotificationTypes;
use App\Modules\Ntf\Contracts\RecipientDirectory;
use App\Modules\Ntf\Models\Jenis;
use App\Modules\Ntf\NtfServiceProvider;
use App\Modules\Ntf\Services\DefaultRecipientDirectory;
use App\Modules\Ntf\Services\TypeRegistry;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Modules\Ntf\Support\FakeAccessControl;
use Tests\Modules\Ntf\Support\SpyAuditLogger;
use Tests\TestCase;

abstract class NtfTestCase extends TestCase
{
    use RefreshDatabase;

    protected FakeAccessControl $access;

    protected SpyAuditLogger $audit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->access = new FakeAccessControl;
        $this->app->instance(AccessControl::class, $this->access);
        // MOD-RBC binds a query-based RecipientDirectory over its own tables; with
        // RBC faked here, use NTF's contract-only default (asks the fake).
        $this->app->bind(RecipientDirectory::class, DefaultRecipientDirectory::class);
        $this->audit = new SpyAuditLogger;
        $this->app->instance(AuditLogger::class, $this->audit);

        Mail::fake();
    }

    protected function types(): TypeRegistry
    {
        return $this->app->make(TypeRegistry::class);
    }

    /** A user holding every MOD-NTF permission (and role "admin" for supervisor fallback). */
    protected function admin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->access->grant($user, array_keys(NtfServiceProvider::PERMISSIONS))->assignRoles($user, ['admin']);

        return $user;
    }

    protected function officer(): User
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->access->assignRoles($user, ['pegawai']);

        return $user;
    }

    /**
     * Register a module type the way another module would.
     *
     * @param  list<string>  $fields
     */
    protected function registerType(string $code = 'pjk.pajakan_tamat', string $priority = NotificationTypes::TINGGI, array $fields = ['no_lot', 'nama_penyewa', 'tarikh_tamat', 'baki_hari'], bool $mandatory = false): Jenis
    {
        $this->types()->register($code, 'Pajakan hampir tamat', $priority,
            'Pajakan lot {{no_lot}} tamat dalam {{baki_hari}} hari',
            'Pajakan oleh {{nama_penyewa}} bagi lot {{no_lot}} tamat pada {{tarikh_tamat}}.',
            $fields, $mandatory);

        return $this->types()->find($code);
    }
}
