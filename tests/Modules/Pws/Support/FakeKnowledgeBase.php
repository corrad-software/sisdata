<?php

namespace Tests\Modules\Pws\Support;

use App\Models\User;
use App\Modules\Kba\Contracts\KnowledgeBase;

/** Records the user each call was made with; returns a passage carrying an internal link. */
class FakeKnowledgeBase implements KnowledgeBase
{
    /** @var list<User|null> */
    public array $users = [];

    public function search(string $query, ?User $user, int $limit = 5): array
    {
        $this->users[] = $user;

        return [['dokumen' => 'Panduan Wakaf Awam', 'versi' => '1', 'seksyen' => '2.1', 'petikan' => 'Wakaf boleh didaftarkan melalui portal.', 'skor' => 0.9, 'pautan' => '/admin/kba/sumber/7']];
    }

    public function answer(string $question, ?User $user): array
    {
        $this->users[] = $user;

        return ['jawapan' => 'Ya, melalui borang hasrat wakaf [1].', 'cukup_sumber' => true,
            'sumber' => [['dokumen' => 'Panduan Wakaf Awam', 'versi' => '1', 'seksyen' => '2.1', 'petikan' => 'Wakaf boleh didaftarkan.', 'pautan' => '/admin/kba/sumber/7']]];
    }
}
