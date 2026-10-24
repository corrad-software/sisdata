<?php

namespace Tests\Modules\Rbc;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Rbc\Events\UserRolesChanged;
use App\Modules\Rbc\Models\RecertKeputusan;
use App\Modules\Rbc\Models\RecertKitaran;
use App\Modules\Rbc\Services\RbcAccessControl;
use App\Modules\Rbc\Services\Recertification;
use App\Modules\Rbc\Support\BaseRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Modules\Rbc\Concerns\InteractsWithRbc;
use Tests\TestCase;

/** SRS-RBC-27: periodic access recertification by divisional data owners. */
class RecertificationTest extends TestCase
{
    use InteractsWithRbc, RefreshDatabase;

    private User $owner;

    private User $wakafOfficer;

    private User $ictOfficer;

    protected function setUp(): void
    {
        parent::setUp();

        // Data owner of Bahagian Wakaf: may decide, but only within that division.
        $this->owner = User::factory()->withPermissions(['rbc.recert.lihat', 'rbc.recert.lulus'])->withScope('bahagian', ['Bahagian Wakaf'])->create(['bahagian' => 'Bahagian Wakaf']);
        $this->wakafOfficer = User::factory()->withRole(BaseRoles::PEGAWAI_OPERASI)->create(['bahagian' => 'Bahagian Wakaf']);
        $this->ictOfficer = User::factory()->withRole(BaseRoles::PEGAWAI_OPERASI)->create(['bahagian' => 'Bahagian ICT']);
    }

    public function test_SRS_RBC_27_cycle_snapshots_the_divisions_role_assignments(): void
    {
        $cycle = $this->actingAs($this->admin())->postJson('/api/v1/rbc/recert', ['nama' => 'Semakan S4 2026', 'bahagian' => 'Bahagian Wakaf'])
            ->assertCreated()
            ->json('data');

        // owner's own test role + the officer's Pegawai Operasi
        $this->assertSame(2, $cycle['jumlah']);
        $this->assertSame([$this->owner->id, $this->wakafOfficer->id], RecertKeputusan::query()->orderBy('user_id')->pluck('user_id')->all());

        $this->actingAs($this->owner)->getJson("/api/v1/rbc/recert/{$cycle['id']}")
            ->assertOk()
            ->assertJsonPath('data.boleh_semak', true)
            ->assertJsonPath('data.keputusan.1.peranan.kod', BaseRoles::PEGAWAI_OPERASI);
    }

    public function test_SRS_RBC_27_owner_confirms_or_withdraws_and_withdrawal_takes_effect(): void
    {
        Event::fake([UserRolesChanged::class]);
        $cycle = app(Recertification::class)->open('Semakan', 'Bahagian Wakaf');
        $decision = $cycle->keputusan()->where('user_id', $this->wakafOfficer->id)->sole();

        $this->actingAs($this->owner)->putJson("/api/v1/rbc/recert/{$cycle->id}/keputusan/{$decision->id}", ['keputusan' => 'ditarik'])
            ->assertUnprocessable()->assertJsonValidationErrors('catatan');

        $this->actingAs($this->owner)->putJson("/api/v1/rbc/recert/{$cycle->id}/keputusan/{$decision->id}", ['keputusan' => 'ditarik', 'catatan' => 'Telah bertukar unit'])
            ->assertOk()->assertJsonPath('data.keputusan', 'ditarik');

        RbcAccessControl::flushAll();
        $this->assertSame([], app(AccessControl::class)->roles($this->wakafOfficer));
        $this->assertSame([$this->owner->id, 'Telah bertukar unit'], [$decision->fresh()->disemak_oleh, $decision->fresh()->catatan]);
        Event::assertDispatched(UserRolesChanged::class, fn ($e) => $e->source === 'recert' && $e->removed === [BaseRoles::PEGAWAI_OPERASI]);
    }

    public function test_SRS_RBC_27_owner_cannot_review_another_division_or_themself(): void
    {
        $recert = app(Recertification::class);
        $ict = $recert->open('ICT', 'Bahagian ICT');
        $ictDecision = $ict->keputusan()->sole();

        $this->actingAs($this->owner)->putJson("/api/v1/rbc/recert/{$ict->id}/keputusan/{$ictDecision->id}", ['keputusan' => 'disahkan'])->assertForbidden();

        $wakaf = $recert->open('Wakaf', 'Bahagian Wakaf');
        $own = $wakaf->keputusan()->where('user_id', $this->owner->id)->sole();
        $this->actingAs($this->owner)->putJson("/api/v1/rbc/recert/{$wakaf->id}/keputusan/{$own->id}", ['keputusan' => 'disahkan'])->assertForbidden();

        // Decision ids are checked against the cycle in the URL.
        $this->actingAs($this->owner)->putJson("/api/v1/rbc/recert/{$wakaf->id}/keputusan/{$ictDecision->id}", ['keputusan' => 'disahkan'])->assertNotFound();

        $this->actingAs($this->wakafOfficer)->putJson("/api/v1/rbc/recert/{$wakaf->id}/keputusan/{$own->id}", ['keputusan' => 'disahkan'])->assertForbidden();
    }

    public function test_SRS_RBC_27_closing_withdraws_unconfirmed_access_per_policy(): void
    {
        $cycle = app(Recertification::class)->open('Semakan', null);
        $confirmed = $cycle->keputusan()->where('user_id', $this->wakafOfficer->id)->sole();
        $this->actingAs($this->owner)->putJson("/api/v1/rbc/recert/{$cycle->id}/keputusan/{$confirmed->id}", ['keputusan' => 'disahkan'])
            ->assertForbidden(); // cycle covers all divisions → needs an unrestricted reviewer

        $admin = $this->admin();
        $this->actingAs($admin)->putJson("/api/v1/rbc/recert/{$cycle->id}/keputusan/{$confirmed->id}", ['keputusan' => 'disahkan'])->assertOk();
        $this->actingAs($admin)->postJson("/api/v1/rbc/recert/{$cycle->id}/tutup")->assertOk()->assertJsonPath('data.status', 'ditutup');

        RbcAccessControl::flushAll();
        $this->assertSame([BaseRoles::PEGAWAI_OPERASI], app(AccessControl::class)->roles($this->wakafOfficer->fresh()));
        $this->assertSame([], app(AccessControl::class)->roles($this->ictOfficer));
        $this->assertSame(RecertKeputusan::DITARIK, $cycle->keputusan()->where('user_id', $this->ictOfficer->id)->sole()->keputusan);

        $this->actingAs($admin)->putJson("/api/v1/rbc/recert/{$cycle->id}/keputusan/{$confirmed->id}", ['keputusan' => 'ditarik', 'catatan' => 'x'])->assertUnprocessable();
    }

    public function test_SRS_RBC_27_closing_keeps_unconfirmed_access_when_policy_says_so(): void
    {
        $this->setPolicy(['recert_tarik_belum_disemak' => false]);
        $cycle = app(Recertification::class)->open('Semakan', 'Bahagian ICT');

        app(Recertification::class)->close($cycle);

        $this->assertSame([BaseRoles::PEGAWAI_OPERASI], app(AccessControl::class)->roles($this->ictOfficer));
    }

    public function test_SRS_RBC_27_schedule_opens_cycles_per_division_and_closes_overdue_ones(): void
    {
        $this->artisan('rbc:recert')->assertSuccessful();
        $divisions = RecertKitaran::query()->pluck('bahagian')->all();
        $this->assertContains('Bahagian Wakaf', $divisions);
        $this->assertContains('Bahagian ICT', $divisions);

        $count = RecertKitaran::count();
        $this->artisan('rbc:recert');
        $this->assertSame($count, RecertKitaran::count(), 'not reopened within the interval');

        $this->travel(15)->days();
        $this->artisan('rbc:recert');
        $this->assertSame(0, RecertKitaran::query()->where('status', 'dibuka')->whereDate('tarikh_akhir', '<', now())->count());
        $this->assertSame($count, RecertKitaran::query()->where('status', 'ditutup')->count());
    }

    public function test_SRS_RBC_27_recertification_requires_permission(): void
    {
        $this->actingAs($this->wakafOfficer)->getJson('/api/v1/rbc/recert')->assertForbidden();
        $this->actingAs($this->owner)->postJson('/api/v1/rbc/recert', ['nama' => 'X'])->assertForbidden();
        $this->actingAs($this->owner)->getJson('/api/v1/rbc/recert')->assertOk();
    }
}
