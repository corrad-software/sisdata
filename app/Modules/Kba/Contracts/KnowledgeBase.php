<?php

namespace App\Modules\Kba\Contracts;

use App\Models\User;

/**
 * Grounded knowledge search & answers (MOD-KBA, SRS-KBA-*). Used by MOD-TKT
 * (suggested answers before a ticket is opened), MOD-PWS (public FAQ, only
 * documents classified Awam), MOD-DSH help. Runs entirely on local models —
 * no public AI (SRS-KBA-06, SRS-DSH-18). Results respect the caller's access:
 * with $user null only Awam-classified sources are used.
 */
interface KnowledgeBase
{
    /**
     * Semantic + keyword search over indexed sources.
     *
     * @return list<array{dokumen: string, versi: string|null, seksyen: string|null, petikan: string, skor: float, pautan: string}>
     */
    public function search(string $query, ?User $user, int $limit = 5): array;

    /**
     * Answer a question in BM or English, grounded in indexed sources only.
     * `cukup_sumber` is false — and `jawapan` says so — when the sources don't
     * support an answer (SRS-KBA-05); never an unsupported answer.
     *
     * @return array{jawapan: string, cukup_sumber: bool, sumber: list<array{dokumen: string, versi: string|null, seksyen: string|null, petikan: string, pautan: string}>}
     */
    public function answer(string $question, ?User $user): array;
}
