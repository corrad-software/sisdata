<?php

namespace App\Modules\Kba\Console;

use App\Modules\Adm\Contracts\JobProgress;
use App\Modules\Kba\Contracts\EmbeddingProvider;
use App\Modules\Kba\Services\PengurusSumber;
use Illuminate\Console\Command;

/** SRS-KBA-01: index the project baseline documents (config kba.baseline). Incremental unless --paksa. */
class IndeksBaselineCommand extends Command
{
    protected $signature = 'kba:index-baseline {--paksa : Index again even if the content is unchanged}';

    protected $description = 'Indeks dokumen baseline projek SISDATA ke Pangkalan Pengetahuan (MOD-KBA)';

    public function handle(PengurusSumber $pengurus, EmbeddingProvider $embedder, JobProgress $progress): int
    {
        $this->info('Model embedding: '.$embedder->model());
        $mula = microtime(true);
        $rows = $pengurus->indeksBaseline((bool) $this->option('paksa'));
        $progress->processed(count($rows));
        $this->table(['Fail', 'Status', 'Segmen', 'Baharu', 'Guna semula'], array_map(fn ($r) => [
            $r['laluan'], $r['dilangkau'] ? 'tiada perubahan' : $r['status'], $r['segmen'], $r['baharu'], $r['guna_semula'],
        ], $rows));
        $this->info(sprintf('Selesai dalam %.1f saat.', microtime(true) - $mula));

        return collect($rows)->contains(fn ($r) => in_array($r['status'], ['gagal', 'tiada_fail'], true)) ? self::FAILURE : self::SUCCESS;
    }
}
