<?php

namespace App\Modules\Kba\Ai;

use App\Modules\Kba\Contracts\EmbeddingProvider;
use App\Modules\Kba\Services\Teks;

/**
 * Deterministic, model-free embedder ("feature hashing" of words and 5-letter
 * stems). Used by tests and whenever no local embedding model is configured:
 * similarity then tracks shared vocabulary, so hybrid retrieval still works
 * — just without true semantics. Never calls anything.
 */
class HashEmbeddingProvider implements EmbeddingProvider
{
    public function __construct(private int $dimensions = 768) {}

    public function model(): string
    {
        return 'cincangan-v1';
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    public function embed(array $texts, string $purpose = 'dokumen'): array
    {
        return array_map(fn (string $t) => $this->vector($t), $texts);
    }

    /** @return list<float> */
    private function vector(string $text): array
    {
        $v = array_fill(0, $this->dimensions, 0.0);
        foreach (Teks::contentTokens($text) as $token) {
            $this->add($v, $token, 1.0);
            if (mb_strlen($token) >= 6) {
                $this->add($v, '~'.mb_substr($token, 0, 5), 0.5);
            }
        }

        return Vektor::normalise(array_values($v));
    }

    /** @param array<int, float> $v */
    private function add(array &$v, string $feature, float $weight): void
    {
        $h = crc32($feature);
        $v[$h % $this->dimensions] += (($h >> 16) & 1) === 1 ? $weight : -$weight;
    }
}
