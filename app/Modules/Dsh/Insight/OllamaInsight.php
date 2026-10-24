<?php

namespace App\Modules\Dsh\Insight;

use App\Models\User;
use App\Modules\Dsh\Contracts\InsightGenerator;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

/**
 * Optional InsightGenerator backed by a LOCAL Ollama server (off by default;
 * DSH_AI_PEMACU=ollama). The endpoint must be loopback or a private-network
 * address inside the MAIS VPC — a public host is refused outright
 * (SRS-DSH-18). The model only sees KPI figures already computed under the
 * user's scope, and its output is validated against the user's KPI
 * catalogue; anything unusable falls back to the rule-based engine.
 */
class OllamaInsight implements InsightGenerator
{
    public function __construct(private PeraturanInsight $rules, private string $url, private string $model, private int $timeout = 20)
    {
        self::assertLocal($url);
    }

    public function engine(): string
    {
        return 'ollama';
    }

    /** @throws InvalidArgumentException when $url is not a loopback / private-network endpoint */
    public static function assertLocal(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (! is_string($host) || ! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('URL AI tempatan tidak sah.');
        }
        $host = trim(strtolower($host), '[]');
        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $public = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if ($public === false) {
                return; // private / loopback / reserved range
            }
        }

        throw new InvalidArgumentException('Perkhidmatan AI mesti berada dalam rangkaian persendirian MAIS (SRS-DSH-18).');
    }

    public function insights(User $user, array $kpis): array
    {
        $facts = array_map(fn ($k) => [
            'kpi' => $k['key'], 'label' => $k['label'], 'nilai' => $k['nilai'], 'unit' => $k['unit'],
            'lampu' => $k['lampu'] ?? null, 'trend' => array_slice((array) ($k['trend'] ?? []), -3),
        ], array_values(array_filter($kpis, fn ($k) => empty($k['ralat']))));

        $answer = $this->ask(
            'Anda pembantu analitik hartanah MAIS. Berdasarkan data KPI JSON berikut sahaja, berikan sehingga 5 pemerhatian ringkas dalam Bahasa Melayu. '
            .'Balas JSON sahaja: [{"tahap":"kritikal|perhatian|maklumat|baik","teks":"...","kpi":"<kunci>"}]. Data: '.json_encode($facts, JSON_UNESCAPED_UNICODE)
        );
        $known = array_column($kpis, 'key');
        $out = [];
        foreach (is_array($answer) ? $answer : [] as $row) {
            if (is_array($row) && isset($row['teks'], $row['tahap']) && in_array($row['tahap'], ['kritikal', 'perhatian', 'maklumat', 'baik'], true)) {
                $item = ['tahap' => (string) $row['tahap'], 'teks' => mb_substr(strip_tags((string) $row['teks']), 0, 300)];
                if (isset($row['kpi']) && in_array($row['kpi'], $known, true)) {
                    $item['kpi'] = (string) $row['kpi'];
                }
                $out[] = $item;
            }
        }

        return $out !== [] ? array_slice($out, 0, 8) : $this->rules->insights($user, $kpis);
    }

    public function interpret(User $user, string $prompt, array $catalogue, array $districts): array
    {
        $answer = $this->ask(
            'Tukar soalan pengguna kepada JSON pertanyaan dashboard. KPI dibenarkan: '.json_encode(array_map(fn ($c) => [$c['key'], $c['label']], $catalogue), JSON_UNESCAPED_UNICODE)
            .'. Daerah: '.json_encode($districts, JSON_UNESCAPED_UNICODE)
            .'. Balas JSON sahaja: {"niat":"nilai|senarai|trend|susun_atur","kpi":["<kunci>"],"penapis":{"kod_daerah":"<kod>","tahun":<tahun>},"visual":"stat|bar|pie|line|jadual","susun_atur":{"tindakan":"tambah|buang","kpi":["<kunci>"]}}. Soalan: '
            .json_encode($prompt, JSON_UNESCAPED_UNICODE)
        );
        $fallback = $this->rules->interpret($user, $prompt, $catalogue, $districts);
        if (! is_array($answer)) {
            return $fallback;
        }
        $allowed = array_column($catalogue, 'key');
        $kpi = array_values(array_intersect(array_map('strval', (array) ($answer['kpi'] ?? [])), $allowed));
        $niat = in_array($answer['niat'] ?? null, ['nilai', 'senarai', 'trend', 'susun_atur'], true) ? $answer['niat'] : null;
        if ($kpi === [] || $niat === null) {
            return $fallback;
        }
        $penapis = [];
        $p = (array) ($answer['penapis'] ?? []);
        if (isset($p['kod_daerah']) && isset($districts[(string) $p['kod_daerah']])) {
            $penapis['kod_daerah'] = (string) $p['kod_daerah'];
        }
        if (isset($p['tahun']) && preg_match('/^(19|20)\d{2}$/', (string) $p['tahun'])) {
            $penapis['tahun'] = (int) $p['tahun'];
        }
        $out = ['niat' => $niat, 'kpi' => array_slice($kpi, 0, 3), 'penapis' => $penapis, 'visual' => in_array($answer['visual'] ?? null, ['stat', 'bar', 'pie', 'line', 'jadual'], true) ? $answer['visual'] : 'stat'];
        if ($niat === 'susun_atur') {
            $t = (array) ($answer['susun_atur'] ?? []);
            $out['susun_atur'] = ['tindakan' => ($t['tindakan'] ?? '') === 'buang' ? 'buang' : 'tambah', 'kpi' => $out['kpi']];
        }

        return $out;
    }

    private function ask(string $prompt): mixed
    {
        try {
            $response = Http::timeout($this->timeout)->post(rtrim($this->url, '/').'/api/generate', [
                'model' => $this->model, 'prompt' => $prompt, 'stream' => false, 'format' => 'json', 'options' => ['temperature' => 0.1],
            ]);
            if (! $response->successful()) {
                return null;
            }
            $decoded = json_decode((string) $response->json('response'), true);

            return is_array($decoded) ? $decoded : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
