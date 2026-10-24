<?php

namespace Tests\Modules\Pro\Support;

use App\Models\User;
use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Contracts\DocumentStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/** In-memory DMS: keeps the uploads so tests can assert the original file was retained. */
class FakeDocumentStore implements DocumentStore
{
    /** @var array<string, array{owner_type: string, owner_id: string, category: string, name: string, content: string}> */
    public array $stored = [];

    /** @var list<string> extensions to reject, like DMS's whitelist */
    public array $reject = [];

    public function store(UploadedFile $file, string $ownerType, string|int $ownerId, string $category, User $uploader, string $classification = 'dalaman', array $metadata = []): string
    {
        if (in_array(strtolower($file->getClientOriginalExtension()), $this->reject, true)) {
            throw new DocumentRejected('Jenis fail tidak dibenarkan.', 'jenis');
        }
        $id = (string) Str::uuid();
        $this->stored[$id] = ['owner_type' => $ownerType, 'owner_id' => (string) $ownerId, 'category' => $category, 'name' => $file->getClientOriginalName(), 'content' => (string) file_get_contents($file->getRealPath())];

        return $id;
    }

    public function forOwner(string $ownerType, string|int $ownerId, User $user): array
    {
        $out = [];
        foreach ($this->stored as $id => $d) {
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
