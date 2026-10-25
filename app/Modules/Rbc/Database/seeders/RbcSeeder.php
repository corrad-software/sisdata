<?php

namespace App\Modules\Rbc\Database\Seeders;

use App\Models\User;
use App\Modules\Rbc\Models\PemetaanTuntutan;
use App\Modules\Rbc\Models\Peranan;
use App\Modules\Rbc\Services\PermissionSync;
use App\Modules\Rbc\Services\RoleService;
use App\Modules\Rbc\Support\BaseRoles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * MOD-RBC baseline: the 7 base roles with their default permissions, the
 * local admin account as Pentadbir Sistem, and SSO claim mapping rules
 * matching the Keycloak realm `mais` test users (infra/keycloak/realm-mais.json).
 * Idempotent.
 */
class RbcSeeder extends Seeder
{
    public function run(PermissionSync $sync, RoleService $roles): void
    {
        $sync->sync();

        $admin = User::query()->firstOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Pentadbir Sistem',
            'password' => Hash::make('password'),
            'jenis_pengesahan' => 'dalaman',
        ]);
        $admin->forceFill(['kata_laluan_ditukar_pada' => $admin->kata_laluan_ditukar_pada ?? now(), 'email_verified_at' => $admin->email_verified_at ?? now()])->save();
        $roles->assignRoleByCode($admin, BaseRoles::PENTADBIR_SISTEM);

        $role = fn (string $kod) => Peranan::query()->where('kod', $kod)->value('id');

        $rules = [
            ['roles', 'pentadbir_sistem', $role(BaseRoles::PENTADBIR_SISTEM), null, null, 'Realm role pentadbir_sistem → Pentadbir Sistem'],
            ['roles', 'pegawai_daerah', $role(BaseRoles::PEGAWAI_OPERASI), null, null, 'Pegawai daerah → Pegawai Operasi'],
            ['roles', 'pegawai_hq', $role(BaseRoles::PEGAWAI_OPERASI), null, null, 'Pegawai ibu pejabat → Pegawai Operasi'],
            ['roles', 'pengurusan', $role(BaseRoles::PENGGUNA_PAPARAN), null, null, 'Pengurusan tertinggi → Pengguna Paparan'],
            // Pejabat daerah units restrict records to their district (SRS-RBC-04/07).
            // Scope values are MOD-REF district codes (ref_daerah.kod) — records carry kod_daerah.
            ['unit', 'Pejabat Agama Islam Daerah Petaling', null, 'daerah', '1008', 'Unit PAID Petaling → skop daerah Petaling (1008)'],
            ['unit', 'Pejabat Agama Islam Daerah Klang', null, 'daerah', '1001', 'Unit PAID Klang → skop daerah Klang (1001)'],
        ];

        foreach ($rules as $i => [$jenis, $nilai, $perananId, $skopJenis, $skopNilai, $keterangan]) {
            $rule = PemetaanTuntutan::query()->firstOrCreate(
                ['jenis_tuntutan' => $jenis, 'nilai' => $nilai],
                ['peranan_id' => $perananId, 'skop_jenis' => $skopJenis, 'skop_nilai' => $skopNilai, 'keutamaan' => ($i + 1) * 10, 'keterangan' => $keterangan],
            );
            // Earlier seeds stored district names ("petaling"), which match no kod_daerah.
            if ($rule->skop_jenis === 'daerah' && ! ctype_digit((string) $rule->skop_nilai)) {
                $rule->forceFill(['skop_nilai' => $skopNilai, 'keterangan' => $keterangan])->save();
            }
        }
    }
}
