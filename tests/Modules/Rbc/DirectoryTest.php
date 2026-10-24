<?php

namespace Tests\Modules\Rbc;

use App\Models\User;
use App\Modules\Ntf\Contracts\RecipientDirectory;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Rbc\Contracts\AccountStatus;
use App\Modules\Rbc\Models\Peranan;
use App\Modules\Rbc\Models\PerananSkop;
use App\Modules\Rbc\Services\UserDirectory;
use App\Modules\Rbc\Support\BaseRoles;
use App\Modules\Wfe\Contracts\ApproverDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Modules\Rbc\Concerns\InteractsWithRbc;
use Tests\TestCase;

/** MOD-RBC's query-based answers for NTF's RecipientDirectory and WFE's ApproverDirectory. */
class DirectoryTest extends TestCase
{
    use InteractsWithRbc, RefreshDatabase;

    /** @param list<User> $users @return list<int> */
    private function ids(array $users): array
    {
        return array_map(fn (User $u) => $u->id, $users);
    }

    public function test_SRS_RBC_04_rbc_binds_the_directories_other_modules_defined(): void
    {
        $this->assertInstanceOf(UserDirectory::class, app(RecipientDirectory::class));
        $this->assertInstanceOf(UserDirectory::class, app(ApproverDirectory::class));
    }

    public function test_SRS_RBC_04_recipients_by_role_permission_scope_and_user(): void
    {
        $approver = User::factory()->withRole(BaseRoles::PELULUS)->create();
        $suspended = User::factory()->withRole(BaseRoles::PELULUS)->create(['status_akaun' => AccountStatus::GANTUNG]);
        $viewer = User::factory()->withRole(BaseRoles::PENGGUNA_PAPARAN)->withScope(AccessControl::SCOPE_DAERAH, ['klang'])->create();
        $unrestricted = User::factory()->withRole(BaseRoles::PENGGUNA_PAPARAN)->create();
        $directory = app(RecipientDirectory::class);

        $this->assertSame([$approver->id], $this->ids($directory->resolve(RecipientDirectory::PERANAN, BaseRoles::PELULUS)));
        $this->assertNotContains($suspended->id, $this->ids($directory->resolve(RecipientDirectory::KEBENARAN, 'wfe.tugasan.lulus')));
        $this->assertContains($approver->id, $this->ids($directory->resolve(RecipientDirectory::KEBENARAN, 'wfe.tugasan.lulus')));
        $this->assertNotContains($viewer->id, $this->ids($directory->resolve(RecipientDirectory::KEBENARAN, 'wfe.tugasan.lulus')));

        // Explicitly scoped users only — an unrestricted user is not "in" every daerah.
        $this->assertSame([$viewer->id], $this->ids($directory->resolve(RecipientDirectory::DAERAH, 'klang')));
        $this->assertNotContains($unrestricted->id, $this->ids($directory->resolve(RecipientDirectory::DAERAH, 'klang')));
        $this->assertSame([$viewer->id], $this->ids($directory->resolve(RecipientDirectory::PENGGUNA, (string) $viewer->id)));
        $this->assertSame([], $directory->resolve(RecipientDirectory::PENGGUNA, (string) $suspended->id));

        // Role scope ∩ user scope (AccessControl::scope semantics).
        $role = Peranan::query()->create(['kod' => 'pegawai_petaling', 'nama' => 'Pegawai Petaling']);
        PerananSkop::query()->create(['peranan_id' => $role->id, 'jenis_skop' => AccessControl::SCOPE_DAERAH, 'nilai_skop' => 'petaling']);
        $roleScoped = User::factory()->withRole('pegawai_petaling')->create();
        $this->assertSame([$roleScoped->id], $this->ids($directory->resolve(RecipientDirectory::DAERAH, 'petaling')));

        $this->assertEqualsCanonicalizing(['klang', 'petaling'], array_column($directory->options(RecipientDirectory::DAERAH), 'value'));
        $this->assertContains(['value' => BaseRoles::PELULUS, 'label' => 'Pelulus'], $directory->options(RecipientDirectory::PERANAN));
    }

    public function test_SRS_RBC_04_inactive_roles_grant_no_membership(): void
    {
        $user = User::factory()->withRole(BaseRoles::JURUAUDIT)->create();
        $this->role(BaseRoles::JURUAUDIT)->update(['status' => Peranan::TIDAK_AKTIF]);

        $this->assertSame([], app(RecipientDirectory::class)->resolve(RecipientDirectory::PERANAN, BaseRoles::JURUAUDIT));
        $this->assertNotContains($user->id, $this->ids(app(ApproverDirectory::class)->usersWithRole(BaseRoles::JURUAUDIT)));
    }

    public function test_SRS_RBC_04_approvers_by_role_and_jawatan_with_fallback_supervisors(): void
    {
        $director = User::factory()->withRole(BaseRoles::PENTADBIR_SISTEM)->create(['jawatan' => 'pengarah']);
        $officer = User::factory()->create(['jawatan' => 'pegawai_hartanah']);
        $directory = app(ApproverDirectory::class);

        $this->assertSame([$director->id], $this->ids($directory->usersWithRole(BaseRoles::PENTADBIR_SISTEM)));
        $this->assertSame([$director->id], $this->ids($directory->usersWithRole('admin')), 'legacy parameter value "admin"');
        $this->assertSame([$officer->id], $this->ids($directory->usersWithPosition('pegawai_hartanah')));
        $this->assertSame('pengarah', $directory->positionOf($director));

        // No hierarchy in RBC: holders of wfe.peranan_penyelia_lalai (default "admin"), never the user.
        $this->assertSame([$director->id], $this->ids($directory->supervisorsOf($officer)));
        $this->assertSame([], $directory->supervisorsOf($director));
    }

    public function test_SRS_RBC_22_suspicious_login_activity_notifies_system_administrators(): void
    {
        $this->setPolicy(['kunci_ambang_cubaan' => 10]);
        $admin = $this->admin();
        $officer = User::factory()->withRole(BaseRoles::PEGAWAI_OPERASI)->create();
        $this->userWithPassword();

        foreach (range(1, 5) as $i) {
            $this->login('pegawai@mais.test', 'Salah#Laluan0'.$i)->assertUnprocessable();
        }

        $notified = DB::table('ntf_status_pengguna')
            ->join('ntf_mesej', 'ntf_mesej.id', '=', 'ntf_status_pengguna.mesej_id')
            ->join('ntf_jenis', 'ntf_jenis.id', '=', 'ntf_mesej.jenis_id')
            ->where('ntf_jenis.kod', 'ntf.amaran_keselamatan')
            ->pluck('ntf_status_pengguna.pengguna_id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($admin->id, $notified);
        $this->assertNotContains($officer->id, $notified);
    }
}
