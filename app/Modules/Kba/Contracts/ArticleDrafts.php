<?php

namespace App\Modules\Kba\Contracts;

use App\Models\User;

/**
 * SRS-KBA-14: MOD-TKT hands over a solution article draft when a ticket is
 * resolved (problem → cause → solution). The draft is NEVER visible to
 * ordinary users until it is published through the review workflow
 * (SRS-KBA-13). The ticket id is kept on the article so both sides can link
 * to each other.
 */
interface ArticleDrafts
{
    /**
     * @param  array{tajuk: string, masalah: string, punca: string, penyelesaian: string, modul?: string|null, kategori?: string|null, versi_sistem?: string|null}  $data
     * @return int article id (an existing draft for the same ticket is updated, not duplicated)
     */
    public function fromTicket(string $ticketId, array $data, ?User $author = null): int;

    /**
     * Articles created from a ticket, for the ticket's page.
     *
     * @return list<array{id: int, tajuk: string, status: string, pautan: string}>
     */
    public function forTicket(string $ticketId): array;
}
