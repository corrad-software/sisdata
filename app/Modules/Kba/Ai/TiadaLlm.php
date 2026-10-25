<?php

namespace App\Modules\Kba\Ai;

use App\Modules\Kba\Contracts\LlmGateway;
use RuntimeException;

/** No generation model configured → MOD-KBA answers in extractive mode. */
class TiadaLlm implements LlmGateway
{
    public function model(): ?string
    {
        return null;
    }

    public function available(): bool
    {
        return false;
    }

    public function generate(string $system, string $user): array
    {
        throw new RuntimeException('Tiada model penjanaan dikonfigurasi.');
    }
}
