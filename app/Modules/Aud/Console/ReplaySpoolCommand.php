<?php

namespace App\Modules\Aud\Console;

use App\Modules\Aud\Services\AuditWriter;
use Illuminate\Console\Command;

/**
 * SRS-AUD-26: writes entries that failed to reach the audit store (spooled by
 * AuditWriter) into the chain. Entries that fail again stay in the spool.
 */
class ReplaySpoolCommand extends Command
{
    protected $signature = 'aud:ulang-spool';

    protected $description = 'Tulis semula rekod audit yang gagal disimpan (SRS-AUD-26)';

    public function handle(AuditWriter $writer): int
    {
        $path = (string) config('aud.laluan_spool');
        if (! is_file($path)) {
            $this->info('Tiada rekod tertunda.');

            return self::SUCCESS;
        }

        // Claim the file first so concurrent failures start a new spool.
        $claimed = $path.'.'.getmypid().'.proses';
        rename($path, $claimed);

        $ok = 0;
        $failed = [];
        foreach (file($claimed, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $entry = json_decode($line, true);
            if (! is_array($entry) || ! isset($entry['rekod'])) {
                continue;
            }

            $meta = $entry['rekod']['meta'] ? (array) json_decode((string) $entry['rekod']['meta'], true) : [];
            $meta['diulang_dari_spool'] = true;
            $entry['rekod']['meta'] = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

            $writer->persist($entry['rekod'], $entry['perubahan'] ?? []) !== null ? $ok++ : $failed[] = $line;
        }

        unlink($claimed);
        // persist() re-spools its own failures; nothing else to keep.
        $this->info("{$ok} rekod ditulis semula, ".count($failed).' masih gagal.');

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
