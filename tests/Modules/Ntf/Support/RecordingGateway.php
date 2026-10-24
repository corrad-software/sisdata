<?php

namespace Tests\Modules\Ntf\Support;

use App\Models\User;
use App\Modules\Ntf\Contracts\SmsGateway;
use App\Modules\Ntf\Contracts\WhatsAppGateway;

class RecordingGateway implements SmsGateway, WhatsAppGateway
{
    /** @var list<array{0: string, 1: int, 2: string}> */
    public static array $sent = [];

    public function __construct(private string $name) {}

    /** @return list<array{0: string, 1: int, 2: string}> */
    public static function sent(): array
    {
        return self::$sent;
    }

    public function send(User $to, string $text): string
    {
        self::$sent[] = [$this->name, $to->id, $text];

        return $this->name.'-'.count(self::$sent);
    }
}
