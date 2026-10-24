<?php

namespace Tests\Modules\Wfe;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Wfe\Contracts\ApproverDirectory;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Models\Definisi;
use App\Modules\Wfe\Models\Kumpulan;
use App\Modules\Wfe\Services\DefaultApproverDirectory;
use App\Modules\Wfe\Services\DefinitionService;
use App\Modules\Wfe\Services\StepUp;
use App\Modules\Wfe\WfeServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Shared set-up for MOD-WFE tests: in-memory fakes for RBC AccessControl
 * (permissions/roles/district scope per user), NTF Notifier and AUD
 * AuditLogger, and helpers to build active definitions.
 */
abstract class WfeTestCase extends TestCase
{
    use RefreshDatabase;

    protected FakeAccess $access;

    /** @var list<array{type: string, to: list<int>, data: array<string, mixed>, link: string|null}> */
    public array $sent = [];

    /** @var list<array{action: string, entity: string|null, id: string|int|null, reason: string|null, meta: array<string, mixed>}> */
    public array $audited = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Monday 2 Nov 2026, 09:00 Malaysia time — a Selangor working day.
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00', 'Asia/Kuala_Lumpur'));

        $this->withHeader('Referer', 'http://localhost:3000');

        $this->access = new FakeAccess;
        $this->app->instance(AccessControl::class, $this->access);
        // MOD-RBC binds a query-based ApproverDirectory over its own tables; with
        // RBC faked here, use WFE's default (legacy users.role / users.jawatan).
        $this->app->bind(ApproverDirectory::class, DefaultApproverDirectory::class);

        $test = $this;
        $this->app->instance(Notifier::class, new class($test) implements Notifier
        {
            public function __construct(private WfeTestCase $test) {}

            public function send(string $type, User|iterable $recipients, array $data = [], ?string $link = null, ?string $dedupeKey = null): void
            {
                $ids = [];
                foreach ($recipients instanceof User ? [$recipients] : $recipients as $u) {
                    $ids[] = $u->id;
                }
                $this->test->sent[] = ['type' => $type, 'to' => $ids, 'data' => $data, 'link' => $link];
            }
        });
        $this->app->instance(AuditLogger::class, new class($test) implements AuditLogger
        {
            public function __construct(private WfeTestCase $test) {}

            public function record(string $action, string $module, ?string $entityType = null, string|int|null $entityId = null, string $status = 'berjaya', ?string $reason = null, array $changes = [], array $meta = []): void
            {
                $this->test->audited[] = ['action' => $action, 'entity' => $entityType, 'id' => $entityId, 'reason' => $reason, 'meta' => $meta, 'status' => $status];
            }
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @param  list<string>|null  $permissions  null = an ordinary approver/applicant (task board + own requests)
     * @param  list<string>  $roles
     * @param  list<string>|null  $districts
     */
    protected function user(?array $permissions = null, array $roles = [], ?array $districts = null, array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['role' => 'user']);
        $permissions ??= ['wfe.tugasan.lihat', 'wfe.tugasan.lulus', 'wfe.permohonan.lihat', 'wfe.permohonan.kemas_kini', 'wfe.pemangku.cipta'];
        $this->access->grant($user, $permissions, $roles, $districts);

        return $user;
    }

    /** A user holding every WFE permission. */
    protected function admin(array $roles = ['pentadbir_aplikasi']): User
    {
        return $this->user(array_keys(WfeServiceProvider::PERMISSIONS), $roles);
    }

    /** @param list<User> $members */
    protected function group(string $code, array $members): Kumpulan
    {
        $group = Kumpulan::create(['kod' => $code, 'nama' => 'Kumpulan '.$code]);
        $group->ahli()->sync(array_map(fn (User $u) => $u->id, $members));

        return $group;
    }

    /** @return array<string, mixed> */
    protected function stage(string $kod, array $approvers, array $extra = []): array
    {
        return array_merge([
            'kod' => $kod,
            'nama' => 'Peringkat '.$kod,
            'mod_kelulusan' => 'mana_mana',
            'sla_nilai' => 2,
            'sla_unit' => 'hari_bekerja',
            'pelulus' => array_map(fn ($a) => is_array($a) ? $a : ['jenis' => 'individu', 'nilai' => (string) $a->id], $approvers),
        ], $extra);
    }

    /**
     * @param  list<array<string, mixed>>  $stages
     * @param  list<array<string, mixed>>  $transitions
     * @return array<string, mixed>
     */
    protected function structure(string $code, array $stages, array $transitions = [], array $extra = []): array
    {
        return array_merge([
            'kod_aliran' => $code,
            'nama' => 'Aliran '.$code,
            'modul' => 'tst',
            'jenis_transaksi' => 'rekod_ujian',
            'peringkat' => $stages,
            'peralihan' => $transitions,
        ], $extra);
    }

    /** Create and activate a definition directly (bypassing the two-admin flow). */
    protected function activeFlow(array $structure): Definisi
    {
        $author = User::factory()->create();
        $definition = app(DefinitionService::class)->create($structure, $author);
        $definition->forceFill(['status' => Definisi::AKTIF, 'tarikh_berkuat_kuasa' => now()])->save();

        return $definition;
    }

    protected function engine(): WorkflowEngine
    {
        return app(WorkflowEngine::class);
    }

    /** Session with a fresh MFA step-up. */
    protected function withMfa(?Carbon $at = null): static
    {
        return $this->withSession([StepUp::SESSION_AT => ($at ?? now())->timestamp]);
    }

    /** @return list<array{type: string, to: list<int>, data: array<string, mixed>, link: string|null}> */
    protected function sentOfType(string $type): array
    {
        return array_values(array_filter($this->sent, fn ($s) => $s['type'] === $type));
    }

    protected function act(User $user, int $taskId, string $action, ?string $justification = null, array $extra = []): TestResponse
    {
        // One test session is shared by every actor; drop Sanctum's
        // per-session password hash so switching users isn't a "logout".
        $this->app['session']->forget('password_hash_web');

        return $this->actingAs($user)->postJson("/api/v1/wfe/tugasan/{$taskId}/tindakan", array_filter([
            'tindakan' => $action,
            'justifikasi' => $justification,
        ] + $extra, fn ($v) => $v !== null));
    }
}
