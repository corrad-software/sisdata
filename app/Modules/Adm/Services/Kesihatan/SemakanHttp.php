<?php

namespace App\Modules\Adm\Services\Kesihatan;

use Illuminate\Support\Facades\Http;
use Throwable;

/** Any HTTP dependency: up = it answers below 500 within the timeout. */
class SemakanHttp implements Semakan
{
    public function __construct(private string $kod, private string $label, private string $url, private float $timeout = 2.0) {}

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
        try {
            $response = Http::timeout($this->timeout)->connectTimeout(min(1.5, $this->timeout))->withoutRedirecting()->get($this->url);

            return $response->status() < 500
                ? ['status' => self::OK, 'mesej' => null, 'butiran' => ['status_http' => $response->status()]]
                : ['status' => self::GAGAL, 'mesej' => 'HTTP '.$response->status(), 'butiran' => ['status_http' => $response->status()]];
        } catch (Throwable $e) {
            return ['status' => self::GAGAL, 'mesej' => class_basename($e)];
        }
    }
}
