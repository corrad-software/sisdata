<?php

namespace App\Modules\Dms\Services\Scanning;

use Throwable;

/**
 * clamd over its unix socket using the INSTREAM command, so the file never
 * needs to be readable by the clamd user and nothing is written to disk by
 * the scanner.
 */
class ClamdScanner implements VirusScanner
{
    private const ENGINE = 'clamav';

    public function __construct(private string $socket, private int $timeout = 60) {}

    public function scan(string $path): ScanResult
    {
        try {
            $file = fopen($path, 'rb');
            if ($file === false) {
                return ScanResult::error(self::ENGINE, 'cannot read file');
            }

            $conn = $this->connect();
            fwrite($conn, "zINSTREAM\0");
            while (! feof($file)) {
                $chunk = fread($file, 65536);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                fwrite($conn, pack('N', strlen($chunk)).$chunk);
            }
            fwrite($conn, pack('N', 0));
            fclose($file);

            $reply = trim((string) stream_get_contents($conn), "\0\n ");
            fclose($conn);

            $version = $this->version();

            if (preg_match('/^stream: (.+) FOUND$/', $reply, $m)) {
                return ScanResult::infected(self::ENGINE, $m[1], $version);
            }
            if (str_ends_with($reply, 'OK')) {
                return ScanResult::clean(self::ENGINE, $version);
            }

            return ScanResult::error(self::ENGINE, $reply !== '' ? $reply : 'empty reply');
        } catch (Throwable $e) {
            return ScanResult::error(self::ENGINE, $e->getMessage());
        }
    }

    public function available(): bool
    {
        try {
            $conn = $this->connect();
            fwrite($conn, "zPING\0");
            $reply = trim((string) stream_get_contents($conn), "\0\n ");
            fclose($conn);

            return $reply === 'PONG';
        } catch (Throwable) {
            return false;
        }
    }

    /** e.g. "ClamAV 1.4.2/27437/Fri Oct 24 08:00:00 2026" */
    public function version(): ?string
    {
        try {
            $conn = $this->connect();
            fwrite($conn, "zVERSION\0");
            $reply = trim((string) stream_get_contents($conn), "\0\n ");
            fclose($conn);

            return $reply !== '' ? substr($reply, 0, 190) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return resource */
    private function connect()
    {
        $conn = @stream_socket_client('unix://'.$this->socket, $errno, $errstr, 5);
        if ($conn === false) {
            throw new \RuntimeException("clamd unreachable: {$errstr}");
        }
        stream_set_timeout($conn, $this->timeout);

        return $conn;
    }
}
