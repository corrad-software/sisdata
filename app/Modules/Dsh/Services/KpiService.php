<?php

namespace App\Modules\Dsh\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Dsh\Contracts\KpiDrillDown;
use App\Modules\Dsh\Contracts\KpiProvider;
use App\Modules\Dsh\Contracts\KpiProviders;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Computes the registered KPIs for one user: only the KPIs whose permission
 * the user holds (SRS-RBC-08 — Sulit/Rahsia summaries need their explicit
 * grant), each computed by its owning provider under the user's scope and
 * cached per (KPI, scope signature, filters) for a short TTL, never longer
 * than 15 minutes (SRS-DSH-13). A failing provider yields an error entry
 * instead of breaking the dashboard.
 */
class KpiService
{
    public const MAX_TTL = 900;

    public function __construct(
        private KpiProviders $providers,
        private AccessControl $access,
        private Skop $skop,
        private Ambang $ambang,
        private Parameters $parameters,
    ) {}

    /** @return array<string, KpiProvider> every registered KPI */
    public function registered(): array
    {
        return $this->providers instanceof KpiRegistry ? $this->providers->all() : [];
    }

    /** @return array<string, KpiProvider> the KPIs $user may see */
    public function visible(User $user): array
    {
        return array_filter($this->registered(), fn (KpiProvider $p) => $this->access->allows($user, $p->permission()));
    }

    public function find(User $user, string $key): ?KpiProvider
    {
        return $this->visible($user)[$key] ?? null;
    }

    public static function modul(KpiProvider $p): string
    {
        return strtok($p->key(), '.') ?: 'dsh';
    }

    /** @return list<array{key: string, label: string, domain: string, modul: string, drill: bool}> */
    public function catalogue(User $user): array
    {
        return array_values(array_map(fn (KpiProvider $p) => [
            'key' => $p->key(),
            'label' => $p->label(),
            'domain' => $p->domain(),
            'modul' => self::modul($p),
            'drill' => $p instanceof KpiDrillDown,
        ], $this->visible($user)));
    }

    /**
     * @param  array<string, mixed>  $filters  normalised (Penapis::from)
     * @param  list<string>|null  $keys  restrict to these KPIs
     * @return list<array<string, mixed>>
     */
    public function compute(User $user, array $filters = [], ?array $keys = null, bool $fresh = false): array
    {
        $out = [];
        foreach ($this->visible($user) as $key => $provider) {
            if ($keys !== null && ! in_array($key, $keys, true)) {
                continue;
            }
            if (! empty($filters['modul']) && self::modul($provider) !== $filters['modul']) {
                continue;
            }
            $out[] = $this->one($user, $provider, $filters, $fresh);
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function one(User $user, KpiProvider $provider, array $filters, bool $fresh = false): array
    {
        $passed = $filters;
        unset($passed['modul']);
        $ttl = $this->ttl();
        $cacheKey = 'dsh:kpi:'.$provider->key().':'.$this->skop->signature($user).':'.Penapis::hash($passed);

        $run = function () use ($provider, $user, $passed) {
            try {
                return ['hasil' => $provider->compute($user, $passed), 'dikira_pada' => Carbon::now()->toIso8601String(), 'ralat' => false];
            } catch (Throwable $e) {
                report($e);

                return ['hasil' => ['nilai' => null, 'unit' => ''], 'dikira_pada' => Carbon::now()->toIso8601String(), 'ralat' => true];
            }
        };

        if ($ttl <= 0 || $fresh) {
            $cached = $run();
            if ($ttl > 0 && ! $cached['ralat']) {
                Cache::put($cacheKey, $cached, $ttl);
            }
        } else {
            $cached = Cache::get($cacheKey);
            if (! is_array($cached)) {
                $cached = $run();
                if (! $cached['ralat']) {
                    Cache::put($cacheKey, $cached, $ttl);
                }
            }
        }

        $r = (array) $cached['hasil'];
        $nilai = $r['nilai'] ?? null;
        $sasaran = isset($r['sasaran']) ? (float) $r['sasaran'] : null;

        return [
            'key' => $provider->key(),
            'label' => $provider->label(),
            'domain' => $provider->domain(),
            'modul' => self::modul($provider),
            'unit' => (string) ($r['unit'] ?? ''),
            'nilai' => is_numeric($nilai) ? $nilai + 0 : null,
            'sasaran' => $sasaran,
            'trend' => array_values((array) ($r['trend'] ?? [])),
            'pecahan' => array_values((array) ($r['pecahan'] ?? [])),
            'matriks' => $r['matriks'] ?? null,
            'pautan' => isset($r['pautan']) && is_string($r['pautan']) && str_starts_with($r['pautan'], '/') ? $r['pautan'] : null,
            'data_terakhir' => $r['data_terakhir'] ?? null,
            'lampu' => $this->ambang->status($provider->key(), is_numeric($nilai) ? (float) $nilai : null, $sasaran),
            'ambang' => $this->ambang->for($provider->key(), $sasaran),
            'drill' => $provider instanceof KpiDrillDown,
            'dikira_pada' => $cached['dikira_pada'],
            'ralat' => (bool) $cached['ralat'],
        ];
    }

    public function ttl(): int
    {
        return max(0, min(self::MAX_TTL, (int) $this->parameters->get('dsh.cache_saat', 300)));
    }
}
