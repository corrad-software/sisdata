<?php

namespace App\Modules\Adm\Services\Carian;

use App\Models\User;
use App\Modules\Adm\Contracts\DeepLinkProvider;
use App\Modules\Adm\Contracts\FilterableSearchProvider;
use App\Modules\Adm\Contracts\SearchProvider;
use App\Modules\Adm\Contracts\SearchProviders;
use App\Modules\Adm\Contracts\SearchSettings;
use App\Modules\Adm\Models\IndeksCarian;
use App\Modules\Adm\Models\SinonimCarian;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Central search service (SRS-ADM-20/21, SRS-GEN-05). Modules register a
 * SearchProvider; a global query fans out to every enabled provider with the
 * admin's synonyms applied, each provider filters by the user's access, and
 * groups are ordered by the admin-set weight. One failing provider never
 * breaks the others. Deep links (SRS-ADM-22) resolve through the entity's own
 * provider, so the same access rules apply.
 */
class PendaftaranCarian implements SearchProviders, SearchSettings
{
    /** @var array<class-string<SearchProvider>, true> */
    private array $classes = [];

    /** @var array<string, SearchProvider>|null */
    private ?array $resolved = null;

    public function __construct(private Container $app) {}

    public function register(string $provider): void
    {
        $this->classes[$provider] = true;
        $this->resolved = null;
    }

    /** @return array<string, SearchProvider> entity => provider */
    public function providers(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $providers = [];
        foreach (array_keys($this->classes) as $class) {
            try {
                $provider = $this->app->make($class);
                $providers[$provider->entity()] = $provider;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $this->resolved = $providers;
    }

    public function fields(string $entity): array
    {
        return $this->settings()[$entity]['medan'] ?? [];
    }

    /**
     * @return array<string, array{label: string, medan: list<string>, pemberat: float, aktif: bool}>
     */
    public function settings(): array
    {
        $rows = [];
        try {
            if (Schema::hasTable('adm_indeks_carian')) {
                foreach (IndeksCarian::query()->get() as $row) {
                    $rows[$row->entiti] = [
                        'label' => $row->label ?? $row->entiti,
                        'medan' => array_map('strval', $row->medan ?? []),
                        'pemberat' => (float) $row->pemberat,
                        'aktif' => $row->aktif,
                    ];
                }
            }
        } catch (Throwable) {
        }

        foreach (array_keys($this->providers()) as $entity) {
            $rows[$entity] ??= ['label' => self::label($entity), 'medan' => [], 'pemberat' => 1.0, 'aktif' => true];
        }

        return $rows;
    }

    /** Ensure every registered entity has a settings row admins can edit. */
    public function syncSettings(): void
    {
        foreach (array_keys($this->providers()) as $entity) {
            IndeksCarian::query()->firstOrCreate(['entiti' => $entity], ['label' => self::label($entity), 'medan' => [], 'pemberat' => 1, 'aktif' => true]);
        }
    }

    public static function label(string $entity): string
    {
        return ucfirst(str_replace('_', ' ', $entity));
    }

    /**
     * @param  array<string, string>  $filters
     * @param  list<string>|null  $entities
     * @return list<array{entiti: string, label: string, pemberat: float, hasil: list<array<string, mixed>>}>
     */
    public function search(string $query, User $user, array $filters = [], ?array $entities = null, int $limit = 10): array
    {
        $query = trim($query);
        if ($query === '' && $filters === []) {
            return [];
        }

        $terms = $query === '' ? [''] : $this->expand($query);
        $settings = $this->settings();
        $groups = [];

        foreach ($this->providers() as $entity => $provider) {
            $setting = $settings[$entity];
            if (! $setting['aktif'] || ($entities !== null && ! in_array($entity, $entities, true))) {
                continue;
            }
            if ($filters !== [] && ! $provider instanceof FilterableSearchProvider) {
                continue;
            }

            $results = [];
            foreach ($terms as $term) {
                try {
                    $found = $provider instanceof FilterableSearchProvider && $filters !== []
                        ? $provider->searchWithFilters($term, $filters, $user, $limit)
                        : $provider->search($term, $user, $limit);
                } catch (Throwable $e) {
                    report($e);

                    continue 2;
                }

                foreach ($found as $hit) {
                    $results[(string) $hit['id']] ??= $hit + ['entiti' => $entity];
                }
                if (count($results) >= $limit) {
                    break;
                }
            }

            if ($results !== []) {
                $groups[] = [
                    'entiti' => $entity,
                    'label' => $setting['label'],
                    'pemberat' => $setting['pemberat'],
                    'hasil' => array_slice(array_values($results), 0, $limit),
                ];
            }
        }

        usort($groups, fn ($a, $b) => [$b['pemberat'], count($b['hasil'])] <=> [$a['pemberat'], count($a['hasil'])]);

        return $groups;
    }

    /**
     * Query plus admin-defined synonyms (whole query and single words).
     *
     * @return list<string>
     */
    public function expand(string $query): array
    {
        $terms = [$query];
        $needle = mb_strtolower($query);

        try {
            foreach (SinonimCarian::query()->get() as $row) {
                $group = array_map('mb_strtolower', [$row->istilah, ...$row->sinonim]);
                if (! in_array($needle, $group, true)) {
                    continue;
                }
                foreach ($group as $synonym) {
                    if ($synonym !== $needle) {
                        $terms[] = $synonym;
                    }
                }
            }
        } catch (Throwable) {
        }

        return array_slice(array_values(array_unique($terms)), 0, 5);
    }

    /** SRS-ADM-22: entity + id → frontend path, or null (unknown entity, missing record, or no access). */
    public function resolve(string $entity, string $id, User $user): ?string
    {
        $provider = $this->providers()[$entity] ?? null;
        if (! $provider) {
            return null;
        }

        try {
            if ($provider instanceof DeepLinkProvider) {
                return $provider->link($id, $user);
            }

            foreach ($provider->search($id, $user, 25) as $hit) {
                if ((string) $hit['id'] === $id) {
                    return $hit['link'];
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        return null;
    }
}
