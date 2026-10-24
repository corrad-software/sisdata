<?php

namespace Tests\Modules\Adm\Fakes;

use App\Models\User;
use App\Modules\Adm\Contracts\SearchProvider;
use RuntimeException;

class BrokenSearchProvider implements SearchProvider
{
    public function entity(): string
    {
        return 'rosak';
    }

    public function search(string $query, User $user, int $limit = 10): array
    {
        throw new RuntimeException('provider down');
    }
}
