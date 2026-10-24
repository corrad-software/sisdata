<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Adu\Support\AliranPenutupan;
use App\Modules\Wfe\Models\Definisi;
use App\Modules\Wfe\Models\Kumpulan;
use App\Modules\Wfe\Services\DefinitionService;
use Illuminate\Database\Seeder;

/**
 * MOD-ADU × MOD-WFE (SRS-ADU-19): seeds and activates the closure-approval
 * flow `adu.penutupan` and its (empty) approver groups. Outside app/Modules
 * because it uses MOD-WFE internals (no seeding contract yet — same as
 * HkmAliranSeeder). Idempotent. `php artisan db:seed --class=AduAliranSeeder`
 */
class AduAliranSeeder extends Seeder
{
    public function run(DefinitionService $definitions): void
    {
        Kumpulan::query()->firstOrCreate(['kod' => AliranPenutupan::KUMPULAN_PENYELIA], ['nama' => 'ADU — Penyelia aduan (pelulus penutupan)']);
        Kumpulan::query()->firstOrCreate(['kod' => AliranPenutupan::KUMPULAN_KETUA], ['nama' => 'ADU — Ketua Bahagian (eskalasi penutupan)']);

        $structure = AliranPenutupan::structure();
        if (Definisi::query()->where('kod_aliran', $structure['kod_aliran'])->exists()) {
            return;
        }
        $author = User::query()->where('role', 'admin')->orderBy('id')->first() ?? User::query()->orderBy('id')->first();
        if ($author === null) {
            $this->command?->warn('AduAliranSeeder: tiada pengguna untuk dijadikan pencipta definisi — dilangkau.');

            return;
        }
        $definition = $definitions->create($structure, $author, 'api', 'Dibenihkan oleh MOD-ADU (SRS-ADU-19)');
        $definition->forceFill(['status' => Definisi::AKTIF, 'tarikh_berkuat_kuasa' => now(), 'diluluskan_pada' => now()])->save();
        $definitions->log($definition, null, 'aktif', 'sistem', 'Diaktifkan oleh AduAliranSeeder');
    }
}
