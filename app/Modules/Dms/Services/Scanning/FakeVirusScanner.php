<?php

namespace App\Modules\Dms\Services\Scanning;

/**
 * Test / no-clamd stand-in (DMS_SCANNER=fake): flags any file containing the
 * EICAR test string, everything else is clean. Can be told to fail, to test
 * the fail-closed path.
 */
class FakeVirusScanner implements VirusScanner
{
    public const EICAR = 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';

    public bool $failing = false;

    /** @var list<string> */
    public array $scanned = [];

    public function scan(string $path): ScanResult
    {
        $this->scanned[] = $path;

        if ($this->failing) {
            return ScanResult::error('fake', 'engine offline');
        }

        $content = (string) file_get_contents($path);

        return str_contains($content, self::EICAR)
            ? ScanResult::infected('fake', 'Eicar-Test-Signature', 'fake-1')
            : ScanResult::clean('fake', 'fake-1');
    }
}
