<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Kes\Support\Aliran;
use App\Modules\Wfe\Models\Definisi;
use App\Modules\Wfe\Models\Kumpulan;
use App\Modules\Wfe\Services\DefinitionService;
use Illuminate\Database\Seeder;

/**
 * MOD-KES × MOD-WFE: seeds and activates the six MOD-KES flows (filing
 * decision, panel appointment, fee payment, appeal, settlement, follow-up
 * task — Support\Aliran) and their (empty) approver groups. Outside
 * app/Modules because it uses MOD-WFE internals (no seeding contract yet —
 * same as AduAliranSeeder / HkmAliranSeeder). Idempotent.
 * `php artisan db:seed --class=KesAliranSeeder`
 */
class KesAliranSeeder extends Seeder
{
    public function run(DefinitionService $definitions): void
    {
        Kumpulan::query()->firstOrCreate(['kod' => Aliran::KUMPULAN_KETUA], ['nama' => 'KES — Ketua Bahagian Perundangan']);
        Kumpulan::query()->firstOrCreate(['kod' => Aliran::KUMPULAN_PENGURUSAN], ['nama' => 'KES — Pengurusan Tertinggi MAIS (penyelesaian)']);
        Kumpulan::query()->firstOrCreate(['kod' => Aliran::KUMPULAN_PEGAWAI], ['nama' => 'KES — Pegawai Perundangan (tindakan susulan)']);

        $author = User::query()->where('role', 'admin')->orderBy('id')->first() ?? User::query()->orderBy('id')->first();
        if ($author === null) {
            $this->command?->warn('KesAliranSeeder: tiada pengguna untuk dijadikan pencipta definisi — dilangkau.');

            return;
        }
        foreach (array_keys(Aliran::FLOWS) as $flow) {
            if (Definisi::query()->where('kod_aliran', $flow)->exists()) {
                continue;
            }
            $definition = $definitions->create(Aliran::structure($flow), $author, 'api', 'Dibenihkan oleh MOD-KES');
            $definition->forceFill(['status' => Definisi::AKTIF, 'tarikh_berkuat_kuasa' => now(), 'diluluskan_pada' => now()])->save();
            $definitions->log($definition, null, 'aktif', 'sistem', 'Diaktifkan oleh KesAliranSeeder');
        }
    }
}
