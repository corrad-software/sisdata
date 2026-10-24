<?php

namespace App\Modules\Adm\Services\Kesihatan;

use Throwable;

/**
 * Raw socket probe: ClamAV ("zPING\0" → "PONG") and SMTP (banner "220").
 */
class SemakanSoket implements Semakan
{
    public function __construct(
        private string $kod,
        private string $label,
        private string $address,
        private ?string $send = null,
        private ?string $expect = null,
        private float $timeout = 2.0,
    ) {}

    public function kod(): string
    {
        return $this->kod;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function jalankan(): array
    {
        $errno = 0;
        $errstr = '';

        try {
            $socket = @stream_socket_client($this->address, $errno, $errstr, $this->timeout);
            if (! $socket) {
                return ['status' => self::GAGAL, 'mesej' => $errstr !== '' ? $errstr : 'connect failed'];
            }

            stream_set_timeout($socket, (int) ceil($this->timeout));
            if ($this->send !== null) {
                fwrite($socket, $this->send);
            }
            $reply = $this->expect !== null ? (string) fread($socket, 512) : '';
            fclose($socket);

            if ($this->expect !== null && ! str_contains($reply, $this->expect)) {
                return ['status' => self::GAGAL, 'mesej' => 'Unexpected reply'];
            }

            return ['status' => self::OK, 'mesej' => null];
        } catch (Throwable $e) {
            return ['status' => self::GAGAL, 'mesej' => class_basename($e)];
        }
    }
}
