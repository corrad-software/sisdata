<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Ast\Support\AliranAst;
use App\Modules\Wfe\Models\Definisi;
use App\Modules\Wfe\Models\Kumpulan;
use App\Modules\Wfe\Services\DefinitionService;
use Illuminate\Database\Seeder;

/**
 * MOD-AST × MOD-WFE: seeds and activates ast.kelulusan_perolehan,
 * ast.pelupusan_aset and ast.kelulusan_penyelenggaraan with their (empty)
 * approver groups. Outside app/Modules because it uses MOD-WFE internals (no seeding
 * contract — same as AduAliranSeeder). Idempotent.
 * `php artisan db:seed --class=AstAliranSeeder`
 */
class AstAliranSeeder extends Seeder
{
    public function run(DefinitionService $definitions): void
    {
        Kumpulan::query()->firstOrCreate(['kod' => AliranAst::KUMPULAN_PEROLEHAN], ['nama' => 'AST — Pelulus perolehan aset (Ketua Unit Aset)']);
        Kumpulan::query()->firstOrCreate(['kod' => AliranAst::KUMPULAN_PELUPUSAN], ['nama' => 'AST — Jawatankuasa pelupusan aset']);
        Kumpulan::query()->firstOrCreate(['kod' => AliranAst::KUMPULAN_PENYELENGGARAAN], ['nama' => 'AST — Pelulus kos penyelenggaraan']);

        $author = User::query()->where('role', 'admin')->orderBy('id')->first() ?? User::query()->orderBy('id')->first();
        if ($author === null) {
            $this->command?->warn('AstAliranSeeder: tiada pengguna untuk dijadikan pencipta definisi — dilangkau.');

            return;
        }
        foreach (AliranAst::structures() as $structure) {
            if (Definisi::query()->where('kod_aliran', $structure['kod_aliran'])->exists()) {
                continue;
            }
            $definition = $definitions->create($structure, $author, 'api', 'Dibenihkan oleh MOD-AST');
            $definition->forceFill(['status' => Definisi::AKTIF, 'tarikh_berkuat_kuasa' => now(), 'diluluskan_pada' => now()])->save();
            $definitions->log($definition, null, 'aktif', 'sistem', 'Diaktifkan oleh AstAliranSeeder');
        }
    }
}
