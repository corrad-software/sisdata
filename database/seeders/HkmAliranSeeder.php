<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Hkm\Support\AliranKelulusan;
use App\Modules\Wfe\Models\Definisi;
use App\Modules\Wfe\Models\Kumpulan;
use App\Modules\Wfe\Services\DefinitionService;
use Illuminate\Database\Seeder;

/**
 * MOD-HKM × MOD-WFE (SRS-HKM-19): seeds and activates the two-stage, MFA
 * flow `hkm.perubahan_hak_milik` plus its two (empty) approver groups. Lives
 * outside app/Modules because it uses MOD-WFE internals (no contract for
 * seeding definitions yet). Idempotent. `php artisan db:seed --class=HkmAliranSeeder`
 */
class HkmAliranSeeder extends Seeder
{
    public function run(DefinitionService $definitions): void
    {
        Kumpulan::query()->firstOrCreate(['kod' => AliranKelulusan::KUMPULAN_PENYEMAK], ['nama' => 'HKM — Pegawai Hartanah Kanan (penyemak perubahan hak milik)']);
        Kumpulan::query()->firstOrCreate(['kod' => AliranKelulusan::KUMPULAN_PELULUS], ['nama' => 'HKM — Ketua Bahagian (pelulus perubahan hak milik)']);

        $structure = AliranKelulusan::structure();
        if (Definisi::query()->where('kod_aliran', $structure['kod_aliran'])->exists()) {
            return;
        }

        $author = User::query()->where('role', 'admin')->orderBy('id')->first() ?? User::query()->orderBy('id')->first();
        if ($author === null) {
            $this->command?->warn('HkmAliranSeeder: tiada pengguna untuk dijadikan pencipta definisi — dilangkau.');

            return;
        }

        $definition = $definitions->create($structure, $author, 'api', 'Dibenihkan oleh MOD-HKM (SRS-HKM-19)');
        $definition->forceFill(['status' => Definisi::AKTIF, 'tarikh_berkuat_kuasa' => now(), 'diluluskan_pada' => now()])->save();
        $definitions->log($definition, null, 'aktif', 'sistem', 'Diaktifkan oleh HkmAliranSeeder');
    }
}
