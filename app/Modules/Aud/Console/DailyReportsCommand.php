<?php

namespace App\Modules\Aud\Console;

use App\Modules\Aud\Services\AuditReports;
use App\Modules\Aud\Services\TrailAuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * SRS-AUD-15: generates the automatic audit reports for one local day
 * (default: yesterday) into storage/app/aud/laporan/<tarikh>.json.
 */
class DailyReportsCommand extends Command
{
    protected $signature = 'aud:laporan {--tarikh= : Tarikh tempatan Y-m-d (lalai: semalam)}';

    protected $description = 'Jana laporan audit automatik harian (SRS-AUD-15)';

    public function handle(AuditReports $reports, TrailAuditLogger $logger): int
    {
        $date = $this->option('tarikh')
            ?: Carbon::now((string) config('aud.zon_waktu_tempatan'))->subDay()->format('Y-m-d');

        $out = ['tarikh' => $date, 'dijana_pada_utc' => Carbon::now('UTC')->toIso8601ZuluString(), 'laporan' => []];
        foreach (AuditReports::TYPES as $type) {
            $report = $reports->generate($type, $date, $date, 1000);
            $out['laporan'][$type] = $report;
            $this->line(sprintf('%-16s %d', $type, $report['jumlah']));
        }

        $path = storage_path("app/aud/laporan/{$date}.json");
        File::ensureDirectoryExists(dirname($path));
        File::put($path, (string) json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $logger->record('jana_laporan', 'aud', null, null, 'berjaya', null, [], [
            'tarikh' => $date,
            'jumlah' => array_map(fn ($r) => $r['jumlah'], $out['laporan']),
        ]);

        $this->info("Laporan disimpan: {$path}");

        return self::SUCCESS;
    }
}
