<?php

namespace Tests\Modules\Gis\Support;

use App\Models\User;
use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Contracts\DocumentStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/** In-memory DMS; `rejectReason` simulates a refusal (e.g. "virus"). */
class FakeDocumentStore implements DocumentStore
{
    /** @var array<string, array{owner_type: string, owner_id: string, category: string, name: string}> */
    public array $stored = [];

    public ?string $rejectReason = null;

    public function store(UploadedFile $file, string $ownerType, string|int $ownerId, string $category, User $uploader, string $classification = 'dalaman', array $metadata = []): string
    {
        if ($this->rejectReason !== null) {
            throw new DocumentRejected('Fail ditolak: '.$this->rejectReason, $this->rejectReason);
        }
        $id = (string) Str::uuid();
        $this->stored[$id] = ['owner_type' => $ownerType, 'owner_id' => (string) $ownerId, 'category' => $category, 'name' => $file->getClientOriginalName()];

        return $id;
    }

    public function forOwner(string $ownerType, string|int $ownerId, User $user): array
    {
        return [];
    }

    public function temporaryUrl(string $documentId, User $user, int $minutes = 5): ?string
    {
        return null;
    }
}
