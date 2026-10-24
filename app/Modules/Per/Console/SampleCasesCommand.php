<?php

namespace App\Modules\Per\Console;

use App\Modules\Per\Services\SampleCases;
use Illuminate\Console\Command;

class SampleCasesCommand extends Command
{
    protected $signature = 'per:sample-cases {--count=60 : Bilangan kes sintetik} {--seed= : Benih rawak (boleh ulang)}';

    protected $description = 'Jana kes pemerolehan SINTETIK merentas 11 saluran dan semua peringkat (DB pembangunan sahaja)';

    public function handle(SampleCases $samples): int
    {
        if (app()->environment('production')) {
            $this->error('Tidak dibenarkan dalam persekitaran produksi.');

            return self::FAILURE;
        }

        $count = max(1, min(1000, (int) $this->option('count')));
        $seed = $this->option('seed') !== null ? (int) $this->option('seed') : null;
        $stats = $samples->generate($count, $seed);

        $this->info("{$stats['dicipta']} kes sintetik dicipta; {$stats['dipautkan_lot']} dipautkan kepada lot berdaftar; {$stats['selesai']} selesai dengan hak milik.");

        return self::SUCCESS;
    }
}
