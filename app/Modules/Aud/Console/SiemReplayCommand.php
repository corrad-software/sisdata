<?php

namespace App\Modules\Aud\Console;

use App\Modules\Aud\Services\AuditSearch;
use App\Modules\Aud\Services\Siem\SiemSink;
use Illuminate\Console\Command;

/** SRS-AUD-22: (re)stream records to the SIEM sink, e.g. after an outage or to backfill. */
class SiemReplayCommand extends Command
{
    protected $signature = 'aud:siem-strim {--dari=1 : no_urutan pertama} {--hingga= : no_urutan terakhir}';

    protected $description = 'Strim semula rekod audit ke SIEM (SRS-AUD-22)';

    public function handle(AuditSearch $search, SiemSink $sink): int
    {
        $query = $search->query(['sumber' => AuditSearch::SUMBER_SEMUA])
            ->where('no_urutan', '>=', (int) $this->option('dari'))
            ->orderBy('no_urutan');

        if ($this->option('hingga')) {
            $query->where('no_urutan', '<=', (int) $this->option('hingga'));
        }

        $count = 0;
        $query->chunk(500, function ($rows) use ($search, $sink, &$count) {
            foreach ($search->present(collect($rows)) as $row) {
                $sink->send($row);
                $count++;
            }
        });

        $this->info("{$count} rekod dihantar ke SIEM ({$sink->name()}).");

        return self::SUCCESS;
    }
}
