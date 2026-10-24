<?php

namespace App\Modules\Dms\Contracts;

use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Central document API (SRS-GEN-04, SRS-DMS-18). Modules store files ONLY
 * through this — never on a disk of their own. Every document is linked to a
 * parent record (polymorphic: type + id) and inherits its classification and
 * access. Uploads go through type whitelist, magic-number check, size limits
 * and antivirus before they are kept; a rejected file throws
 * DocumentRejected (message in Malay, safe to show).
 */
interface DocumentStore
{
    /**
     * @param  string  $ownerType  parent entity, e.g. "lot_tanah" (use the table name)
     * @param  array<string, mixed>  $metadata  category-specific metadata fields
     * @return string document id
     *
     * @throws DocumentRejected
     */
    public function store(
        UploadedFile $file,
        string $ownerType,
        string|int $ownerId,
        string $category,
        User $uploader,
        string $classification = 'dalaman',
        array $metadata = [],
    ): string;

    /**
     * Documents attached to a record that $user may see.
     *
     * @return list<array{id: string, name: string, category: string, version: int, classification: string, uploaded_at: string}>
     */
    public function forOwner(string $ownerType, string|int $ownerId, User $user): array;

    /** Short-lived download URL, audited; null when $user may not access it. */
    public function temporaryUrl(string $documentId, User $user, int $minutes = 5): ?string;
}
