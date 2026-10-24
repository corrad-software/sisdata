<?php

namespace Tests\Modules\Adm\Fakes;

use App\Models\User;
use App\Modules\Adm\Contracts\DeepLinkProvider;
use App\Modules\Adm\Contracts\FilterableSearchProvider;
use App\Modules\Adm\Contracts\Reindexable;
use App\Modules\Rbc\Contracts\AccessControl;

/** Stand-in for a domain module's provider: lots in two districts, scoped by daerah. */
class LotTanahSearchProvider implements DeepLinkProvider, FilterableSearchProvider, Reindexable
{
    public static int $reindexed = 0;

    /** @var list<array{id: string, no_lot: string, daerah: string, mukim: string}> */
    public const LOTS = [
        ['id' => 'L1', 'no_lot' => 'PT 1234', 'daerah' => 'PETALING', 'mukim' => 'DAMANSARA'],
        ['id' => 'L2', 'no_lot' => 'PT 1234', 'daerah' => 'KLANG', 'mukim' => 'KAPAR'],
        ['id' => 'L3', 'no_lot' => 'LOT 99', 'daerah' => 'PETALING', 'mukim' => 'BUKIT RAJA'],
    ];

    public function __construct(private AccessControl $access) {}

    public function entity(): string
    {
        return 'lot_tanah';
    }

    public function search(string $query, User $user, int $limit = 10): array
    {
        return $this->searchWithFilters($query, [], $user, $limit);
    }

    public function searchWithFilters(string $query, array $filters, User $user, int $limit = 10): array
    {
        $allowed = $this->access->scope($user, AccessControl::SCOPE_DAERAH);
        $hits = [];

        foreach (self::LOTS as $lot) {
            if ($allowed !== null && ! in_array($lot['daerah'], $allowed, true)) {
                continue;
            }
            if ($query !== '' && stripos($lot['no_lot'], $query) === false) {
                continue;
            }
            if (isset($filters['daerah']) && $filters['daerah'] !== $lot['daerah']) {
                continue;
            }
            $hits[] = ['id' => $lot['id'], 'title' => $lot['no_lot'], 'subtitle' => $lot['mukim'].', '.$lot['daerah'], 'link' => '/admin/pro/lot/'.$lot['id']];
        }

        return array_slice($hits, 0, $limit);
    }

    public function link(string $id, User $user): ?string
    {
        foreach ($this->search('', $user, 100) as $hit) {
            if ($hit['id'] === $id) {
                return $hit['link'];
            }
        }

        return null;
    }

    public function reindex(): int
    {
        self::$reindexed++;

        return count(self::LOTS);
    }
}
