<?php

namespace App\Modules\Tkt\Console;

use App\Modules\Tkt\Services\SegerakPusat;
use Illuminate\Console\Command;

/** SRS-TKT-16: retry due status pushes to the Sistem Aduan berpusat (back-off), every 10 minutes. */
class SegerakPusatCommand extends Command
{
    protected $signature = 'tkt:segerak-pusat {--semua : Cuba semula semua transaksi gagal, abaikan jadual back-off}';

    protected $description = 'Cuba semula penyegerakan status tiket ke Sistem Aduan berpusat MAIS (MOD-TKT)';

    public function handle(SegerakPusat $segerak): int
    {
        $out = $segerak->ulangCuba((bool) $this->option('semua'));
        $this->info("Berjaya: {$out['berjaya']}, gagal: {$out['gagal']}.");

        return self::SUCCESS;
    }
}
