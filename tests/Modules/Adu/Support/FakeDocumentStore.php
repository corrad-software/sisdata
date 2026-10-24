<?php

namespace Tests\Modules\Adu\Support;

use App\Models\User;
use App\Modules\Dms\Contracts\DocumentStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class FakeDocumentStore implements DocumentStore
{
    /** @var array<string, array{owner_type: string, owner_id: string, category: string, name: string, uploader: int}> */
    public array $docs = [];

    public function store(UploadedFile $file, string $ownerType, string|int $ownerId, string $category, User $uploader, string $classification = 'dalaman', array $metadata = []): string
    {
        $id = (string) Str::uuid();
        $this->docs[$id] = ['owner_type' => $ownerType, 'owner_id' => (string) $ownerId, 'category' => $category, 'name' => $file->getClientOriginalName(), 'uploader' => $uploader->id];

        return $id;
    }

    public function forOwner(string $ownerType, string|int $ownerId, User $user): array
    {
        $out = [];
        foreach ($this->docs as $id => $d) {
            if ($d['owner_type'] === $ownerType && $d['owner_id'] === (string) $ownerId) {
                $out[] = ['id' => $id, 'name' => $d['name'], 'category' => $d['category'], 'version' => 1, 'classification' => 'dalaman', 'uploaded_at' => now()->toIso8601String()];
            }
        }

        return $out;
    }

    public function temporaryUrl(string $documentId, User $user, int $minutes = 5): ?string
    {
        return null;
    }
}
