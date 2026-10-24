<?php

namespace App\Modules\Dms\Services\Scanning;

/** SRS-DMS-07: antivirus engine used before any file is persisted. */
interface VirusScanner
{
    /** Scan a local file. Never throws — engine failures come back as ScanResult::error(). */
    public function scan(string $path): ScanResult;
}
