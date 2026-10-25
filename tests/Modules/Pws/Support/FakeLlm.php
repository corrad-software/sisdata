<?php

namespace Tests\Modules\Pws\Support;

use App\Modules\Kba\Contracts\LlmGateway;

/** Local-model stand-in recording what it was asked. */
class FakeLlm implements LlmGateway
{
    /** @var list<array{system: string, user: string}> */
    public array $calls = [];

    public bool $up = true;

    public string $reply = "Hebahan Projek Wakaf Telaga\n\nPortal Hartanah Wakaf Selangor mempelawa orang ramai menyertai projek wakaf telaga di [ISI: lokasi].";

    public function model(): ?string
    {
        return $this->up ? 'qwen2.5:1.5b' : null;
    }

    public function available(): bool
    {
        return $this->up;
    }

    public function generate(string $system, string $user): array
    {
        $this->calls[] = ['system' => $system, 'user' => $user];

        return ['teks' => $this->reply, 'token' => 42];
    }
}
