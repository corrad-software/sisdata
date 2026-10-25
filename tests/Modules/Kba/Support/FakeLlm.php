<?php

namespace Tests\Modules\Kba\Support;

use App\Modules\Kba\Contracts\LlmGateway;
use RuntimeException;

/**
 * Deterministic LlmGateway for tests (no Ollama needed): returns a scripted
 * answer — or, with $echoFirstSource, a sentence quoting the first source
 * block — and records every call so tests can inspect exactly what context
 * reached the "model".
 */
class FakeLlm implements LlmGateway
{
    /** @var list<array{system: string, user: string}> */
    public array $calls = [];

    public ?string $reply = null;

    public bool $echoFirstSource = true;

    public bool $fails = false;

    public bool $up = true;

    public function model(): ?string
    {
        return 'palsu-llm';
    }

    public function available(): bool
    {
        return $this->up;
    }

    public function generate(string $system, string $user): array
    {
        $this->calls[] = ['system' => $system, 'user' => $user];
        if ($this->fails) {
            throw new RuntimeException('model rosak');
        }
        if ($this->reply !== null) {
            return ['teks' => $this->reply, 'token' => 42];
        }
        if (preg_match('/<sumber no="1"[^>]*>\n(.+?)\n/su', $user, $m)) {
            $line = trim(preg_split('/(?<=[.!?])\s/u', $m[1])[0] ?? $m[1]);

            return ['teks' => $line.' [1]', 'token' => 42];
        }

        return ['teks' => 'TIADA_SUMBER', 'token' => 5];
    }

    public function lastUser(): string
    {
        return $this->calls === [] ? '' : $this->calls[count($this->calls) - 1]['user'];
    }
}
