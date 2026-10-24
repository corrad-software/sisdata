<?php

namespace App\Modules\Adu\Console;

use App\Modules\Adu\Integrasi\MockSistemAduanPusat;
use App\Modules\Adu\Services\SegerakPusat;
use App\Modules\Adu\Support\Lalai;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * SRS-ADU-07: import new complaints from the Sistem Aduan berpusat and retry
 * pending status pushes. `--contoh=N` queues N synthetic complaints in the
 * mock adapter first (local only).
 */
class SegerakPusatCommand extends Command
{
    protected $signature = 'adu:segerak-pusat {--contoh=0 : Beratur N aduan sintetik dalam adapter mock dahulu}';

    protected $description = 'Import aduan daripada Sistem Aduan berpusat MAIS dan ulang cuba penyaluran status (MOD-ADU)';

    public function handle(SegerakPusat $pusat): int
    {
        $n = (int) $this->option('contoh');
        for ($i = 0; $i < $n; $i++) {
            MockSistemAduanPusat::queue([
                'id_luaran' => 'SAP-'.strtoupper(Str::random(8)),
                'kategori' => array_rand(Lalai::SLA),
                'keterangan' => '[SINTETIK] Aduan daripada Sistem Aduan berpusat — ujian integrasi #'.($i + 1),
                'tarikh' => now()->toIso8601String(),
                'tanpa_nama' => true,
            ]);
        }
        $in = $pusat->import();
        $out = $pusat->ulangCuba();
        $this->info("Import: {$in['diterima']} baharu, {$in['sedia_ada']} sedia ada, {$in['gagal']} gagal. Status: {$out['berjaya']} berjaya, {$out['gagal']} gagal.");

        return self::SUCCESS;
    }
}
