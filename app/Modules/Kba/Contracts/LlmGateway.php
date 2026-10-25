<?php

namespace App\Modules\Kba\Contracts;

/**
 * Local generation model behind one interface (SRS-KBA-15/21, SRS-DSH-18).
 * Never a public AI service: the Ollama implementation only accepts a
 * loopback / private-network URL. When no model is configured or it is
 * unreachable, MOD-KBA answers in EXTRACTIVE mode (quoted passages, no
 * generation), so the module works without any model.
 */
interface LlmGateway
{
    /** Model id recorded in the prompt log, e.g. "qwen2.5:1.5b"; null when no model is configured. */
    public function model(): ?string;

    /** Cheap health check (implementations cache it briefly). */
    public function available(): bool;

    /**
     * System instructions and user content are passed separately so retrieved
     * document text can never be mistaken for instructions (SRS-KBA-19).
     *
     * @return array{teks: string, token: int}
     *
     * @throws \RuntimeException when the model fails
     */
    public function generate(string $system, string $user): array;
}
