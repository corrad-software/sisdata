<?php

namespace App\Modules\Adm\Services\Kesihatan;

use Illuminate\Support\Facades\Redis;
use Throwable;

class SemakanRedis implements Semakan
{
    public function kod(): string
    {
        return 'redis';
    }

    public function label(): string
    {
        return 'Redis (cache & baris gilir)';
    }

    public function jalankan(): array
    {
        try {
            $pong = Redis::connection()->command('ping');
            $ok = $pong === true || in_array(strtoupper((string) $pong), ['PONG', '+PONG', '1'], true);

            return ['status' => $ok ? self::OK : self::GAGAL, 'mesej' => $ok ? null : 'No PONG'];
        } catch (Throwable $e) {
            return ['status' => self::GAGAL, 'mesej' => class_basename($e)];
        }
    }
}
