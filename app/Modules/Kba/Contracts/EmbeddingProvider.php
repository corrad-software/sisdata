<?php

namespace App\Modules\Kba\Contracts;

/**
 * Local embedding model behind one interface (SRS-KBA-06/15/21). The default
 * implementation calls a LOCAL Ollama server (loopback / MAIS VPC only — a
 * public endpoint is refused); a deterministic hashing embedder is used in
 * tests and whenever no model is configured. Meant to be shared with other
 * AI features (MOD-TKT suggestions, MOD-PWS FAQ, MOD-DSH) so the model runs once.
 */
interface EmbeddingProvider
{
    /** Model id recorded with every segment and prompt log, e.g. "nomic-embed-text". */
    public function model(): string;

    /** Vector size; must match kba_segmen.embedding (768). */
    public function dimensions(): int;

    /**
     * @param  list<string>  $texts
     * @param  string  $purpose  "dokumen" (indexing) or "soalan" (query) — some models embed them differently
     * @return list<list<float>> one unit-length vector per text, same order
     *
     * @throws \RuntimeException when the model is unreachable
     */
    public function embed(array $texts, string $purpose = 'dokumen'): array;
}
