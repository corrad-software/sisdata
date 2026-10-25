<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Tkt\Support\AliranPerubahan;
use App\Modules\Wfe\Models\Definisi;
use App\Modules\Wfe\Models\Kumpulan;
use App\Modules\Wfe\Services\DefinitionService;
use Illuminate\Database\Seeder;

/**
 * MOD-TKT × MOD-WFE: seeds and activates the change-request approval flow
 * `tkt.permintaan_perubahan` and its (empty) approver groups. Outside
 * app/Modules because it uses MOD-WFE internals (no seeding contract —
 * same as AduAliranSeeder). Idempotent.
 * `php artisan db:seed --class=TktAliranSeeder`
 */
class TktAliranSeeder extends Seeder
{
    public function run(DefinitionService $definitions): void
    {
        Kumpulan::query()->firstOrCreate(['kod' => AliranPerubahan::KUMPULAN_PENGURUSAN], ['nama' => 'TKT — Pengurusan sokongan (pelulus permintaan perubahan)']);
        Kumpulan::query()->firstOrCreate(['kod' => AliranPerubahan::KUMPULAN_JAWATANKUASA], ['nama' => 'TKT — Jawatankuasa Projek MAIS (eskalasi)']);

        $structure = AliranPerubahan::structure();
        if (Definisi::query()->where('kod_aliran', $structure['kod_aliran'])->exists()) {
            return;
        }
        $author = User::query()->where('role', 'admin')->orderBy('id')->first() ?? User::query()->orderBy('id')->first();
        if ($author === null) {
            $this->command?->warn('TktAliranSeeder: tiada pengguna untuk dijadikan pencipta definisi — dilangkau.');

            return;
        }
        $definition = $definitions->create($structure, $author, 'api', 'Dibenihkan oleh MOD-TKT (permintaan perubahan §18.4)');
        $definition->forceFill(['status' => Definisi::AKTIF, 'tarikh_berkuat_kuasa' => now(), 'diluluskan_pada' => now()])->save();
        $definitions->log($definition, null, 'aktif', 'sistem', 'Diaktifkan oleh TktAliranSeeder');
    }
}
