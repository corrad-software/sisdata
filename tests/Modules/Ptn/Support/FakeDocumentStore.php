<?php

namespace Tests\Modules\Ptn\Support;

use App\Models\User;
use App\Modules\Dms\Contracts\DocumentStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class FakeDocumentStore implements DocumentStore
{
    /** @var array<string, array{owner_type: string, owner_id: string, category: string, name: string, uploader: int, classification: string, metadata: array<string, mixed>}> */
    public array $docs = [];

    public function store(UploadedFile $file, string $ownerType, string|int $ownerId, string $category, User $uploader, string $classification = 'dalaman', array $metadata = []): string
    {
        $id = (string) Str::uuid();
        $this->docs[$id] = ['owner_type' => $ownerType, 'owner_id' => (string) $ownerId, 'category' => $category, 'name' => $file->getClientOriginalName(), 'uploader' => $uploader->id, 'classification' => $classification, 'metadata' => $metadata];

        return $id;
    }

    public function forOwner(string $ownerType, string|int $ownerId, User $user): array
    {
        return [];
    }

    public function temporaryUrl(string $documentId, User $user, int $minutes = 5): ?string
    {
        return isset($this->docs[$documentId]) ? 'https://minio.test/'.$documentId.'?expires='.$minutes : null;
    }
}
