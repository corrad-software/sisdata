<?php

namespace App\Modules\Aud\Services\Siem;

class NullSiemSink implements SiemSink
{
    public function send(array $record): void {}

    public function name(): string
    {
        return 'null';
    }
}
