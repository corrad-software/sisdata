<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Ptn\Support\AliranPtn;
use App\Modules\Wfe\Models\Definisi;
use App\Modules\Wfe\Models\Kumpulan;
use App\Modules\Wfe\Services\DefinitionService;
use Illuminate\Database\Seeder;

/**
 * MOD-PTN × MOD-WFE: seeds and activates ptn.pengesahan_laporan,
 * ptn.kemaskini_lot and ptn.pengesahan_tindakan with their (empty) approver
 * groups. Outside app/Modules because it uses MOD-WFE internals (no seeding
 * contract — same as AduAliranSeeder). Idempotent.
 * `php artisan db:seed --class=PtnAliranSeeder`
 */
class PtnAliranSeeder extends Seeder
{
    public function run(DefinitionService $definitions): void
    {
        Kumpulan::query()->firstOrCreate(['kod' => AliranPtn::KUMPULAN_PENGESAH], ['nama' => 'PTN — Pegawai pengesah laporan lawatan (Pegawai Daerah)']);
        Kumpulan::query()->firstOrCreate(['kod' => AliranPtn::KUMPULAN_PEGAWAI_BERKUASA], ['nama' => 'PTN — Pegawai berkuasa rekod tanah (kemas kini Pangkalan Data Induk)']);
        Kumpulan::query()->firstOrCreate(['kod' => AliranPtn::KUMPULAN_PENYELIA], ['nama' => 'PTN — Penyelia tindakan pembetulan']);

        $author = User::query()->where('role', 'admin')->orderBy('id')->first() ?? User::query()->orderBy('id')->first();
        if ($author === null) {
            $this->command?->warn('PtnAliranSeeder: tiada pengguna untuk dijadikan pencipta definisi — dilangkau.');

            return;
        }
        foreach (AliranPtn::structures() as $structure) {
            if (Definisi::query()->where('kod_aliran', $structure['kod_aliran'])->exists()) {
                continue;
            }
            $definition = $definitions->create($structure, $author, 'api', 'Dibenihkan oleh MOD-PTN');
            $definition->forceFill(['status' => Definisi::AKTIF, 'tarikh_berkuat_kuasa' => now(), 'diluluskan_pada' => now()])->save();
            $definitions->log($definition, null, 'aktif', 'sistem', 'Diaktifkan oleh PtnAliranSeeder');
        }
    }
}
