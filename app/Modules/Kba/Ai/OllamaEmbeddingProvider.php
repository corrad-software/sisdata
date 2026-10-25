<?php

namespace App\Modules\Kba\Ai;

use App\Modules\Kba\Contracts\EmbeddingProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Embeddings from a LOCAL Ollama server (`/api/embed`). nomic-embed-text
 * expects task prefixes ("search_document:" / "search_query:"); vectors are
 * re-normalised and their size checked against the column.
 */
class OllamaEmbeddingProvider implements EmbeddingProvider
{
    public function __construct(private string $url, private string $model, private int $dimensions = 768, private int $timeout = 60)
    {
        LocalEndpoint::assert($url);
    }

    public function model(): string
    {
        return $this->model;
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    public function embed(array $texts, string $purpose = 'dokumen'): array
    {
        if ($texts === []) {
            return [];
        }
        $prefix = str_contains($this->model, 'nomic') ? ($purpose === 'soalan' ? 'search_query: ' : 'search_document: ') : '';

        try {
            $response = Http::timeout($this->timeout)->post(rtrim($this->url, '/').'/api/embed', [
                'model' => $this->model,
                'input' => array_map(fn (string $t) => $prefix.mb_substr($t, 0, 6000), $texts),
                'truncate' => true,
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('Model embedding tempatan tidak dapat dihubungi: '.$e->getMessage(), 0, $e);
        }
        if (! $response->successful()) {
            throw new RuntimeException('Model embedding tempatan gagal (HTTP '.$response->status().').');
        }
        $vectors = $response->json('embeddings');
        if (! is_array($vectors) || count($vectors) !== count($texts)) {
            throw new RuntimeException('Respons model embedding tidak sah.');
        }

        return array_map(function ($v) {
            if (! is_array($v) || count($v) !== $this->dimensions) {
                throw new RuntimeException('Saiz vektor embedding tidak sepadan dengan '.$this->dimensions.'.');
            }

            return Vektor::normalise(array_map('floatval', array_values($v)));
        }, array_values($vectors));
    }
}
