<?php

namespace App\Modules\Kba\Ai;

use App\Modules\Kba\Contracts\LlmGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Generation by a LOCAL Ollama server (`/api/chat`). The URL must be loopback
 * or private (SRS-KBA-15); system and user messages stay separate
 * (SRS-KBA-19). Low temperature: answers should restate sources, not invent.
 */
class OllamaLlmGateway implements LlmGateway
{
    public function __construct(private string $url, private string $model, private int $timeout = 90, private int $context = 4096)
    {
        LocalEndpoint::assert($url);
    }

    public function model(): ?string
    {
        return $this->model;
    }

    public function available(): bool
    {
        return Cache::remember('kba:llm:ada:'.md5($this->url.$this->model), 60, function () {
            try {
                $tags = Http::timeout(3)->get(rtrim($this->url, '/').'/api/tags');
                if (! $tags->successful()) {
                    return false;
                }
                $names = array_column((array) $tags->json('models', []), 'name');
                $wanted = str_contains($this->model, ':') ? $this->model : $this->model.':latest';

                return in_array($wanted, $names, true) || in_array($this->model, $names, true);
            } catch (Throwable) {
                return false;
            }
        });
    }

    public function generate(string $system, string $user): array
    {
        try {
            $response = Http::timeout($this->timeout)->post(rtrim($this->url, '/').'/api/chat', [
                'model' => $this->model,
                'stream' => false,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                'options' => ['temperature' => 0.1, 'num_ctx' => $this->context, 'num_predict' => 400],
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('Model AI tempatan tidak dapat dihubungi: '.$e->getMessage(), 0, $e);
        }
        if (! $response->successful()) {
            throw new RuntimeException('Model AI tempatan gagal (HTTP '.$response->status().').');
        }

        return [
            'teks' => trim((string) $response->json('message.content', '')),
            'token' => (int) $response->json('prompt_eval_count', 0) + (int) $response->json('eval_count', 0),
        ];
    }
}
