<?php

namespace App\Support\Fallbacks;

use App\Models\User;
use App\Modules\Kba\Contracts\KnowledgeBase;

/** Stand-in for KnowledgeBase until MOD-KBA binds the real one: finds nothing, says so. */
class FallbackKnowledgeBase implements KnowledgeBase
{
    public function search(string $query, ?User $user, int $limit = 5): array
    {
        return [];
    }

    public function answer(string $question, ?User $user): array
    {
        return ['jawapan' => 'Pangkalan pengetahuan belum tersedia.', 'cukup_sumber' => false, 'sumber' => []];
    }
}
