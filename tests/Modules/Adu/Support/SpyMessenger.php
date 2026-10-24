<?php

namespace Tests\Modules\Adu\Support;

use App\Modules\Adu\Contracts\ReporterMessenger;

class SpyMessenger implements ReporterMessenger
{
    /** @var list<array{saluran: string, ke: string, teks: string}> */
    public array $sent = [];

    public bool $fail = false;

    public function email(string $to, string $subject, string $body): void
    {
        if ($this->fail) {
            throw new \RuntimeException('SMTP down');
        }
        $this->sent[] = ['saluran' => 'emel', 'ke' => $to, 'teks' => $subject."\n".$body];
    }

    public function sms(string $to, string $text): void
    {
        $this->sent[] = ['saluran' => 'sms', 'ke' => $to, 'teks' => $text];
    }
}
