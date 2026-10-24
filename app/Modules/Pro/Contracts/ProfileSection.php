<?php

namespace App\Modules\Pro\Contracts;

use App\Models\User;

/**
 * A module's contribution to the Profil Hartanah 360° (SRS-PRO-26, SRS-HKM-15)
 * and to the lot timeline (SRS-PRO-27). Register with ProfileSections from the
 * module's provider. Return only what $user may see.
 */
interface ProfileSection
{
    /** Stable key, e.g. "hak_milik". */
    public function key(): string;

    /** Tab the section belongs to: identiti | kewangan | pemanfaatan | risiko | ruang | dokumen. */
    public function tab(): string;

    public function label(): string;

    /**
     * Summary block for the lot, or null when the module has nothing for it.
     *
     * @return array{items: list<array<string, mixed>>, flags?: list<array{level: string, label: string}>, link?: string}|null
     */
    public function forLot(string $lotId, User $user): ?array;

    /**
     * Timeline events for the lot.
     *
     * @return list<array{date: string, type: string, title: string, link?: string}>
     */
    public function timeline(string $lotId, User $user): array;
}
