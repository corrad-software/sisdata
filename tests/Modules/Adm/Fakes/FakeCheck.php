<?php

namespace Tests\Modules\Adm\Fakes;

use App\Modules\Adm\Services\Kesihatan\Semakan;

class FakeCheck implements Semakan
{
    /** @param array<string, mixed> $details */
    public function __construct(private string $code, private string $status = self::OK, private ?string $message = null, private array $details = []) {}

    public function kod(): string
    {
        return $this->code;
    }

    public function label(): string
    {
        return strtoupper($this->code);
    }

    public function jalankan(): array
    {
        return ['status' => $this->status, 'mesej' => $this->message, 'butiran' => $this->details];
    }
}
