<?php

namespace Tests\Modules\Tkt\Support;

use App\Models\User;
use App\Modules\Adu\Contracts\ComplaintIntake;
use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Kba\Contracts\ArticleDrafts;
use App\Modules\Kba\Contracts\KnowledgeBase;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Tkt\Contracts\CentralTicketSystem;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class SpyNotifier implements Notifier
{
    /** @var list<array{type: string, to: list<int>, data: array<string, mixed>, link: string|null}> */
    public array $sent = [];

    public function send(string $type, User|iterable $recipients, array $data = [], ?string $link = null, ?string $dedupeKey = null): void
    {
        $ids = [];
        foreach ($recipients instanceof User ? [$recipients] : $recipients as $u) {
            $ids[] = $u->id;
        }
        $this->sent[] = ['type' => $type, 'to' => $ids, 'data' => $data, 'link' => $link];
    }

    /** @return list<array{type: string, to: list<int>, data: array<string, mixed>, link: string|null}> */
    public function of(string $type): array
    {
        return array_values(array_filter($this->sent, fn ($s) => $s['type'] === $type));
    }
}

class FakeDocumentStore implements DocumentStore
{
    /** @var array<string, array{owner_type: string, owner_id: string, category: string, name: string, uploader: int}> */
    public array $docs = [];

    public ?string $reject = null;

    public function store(UploadedFile $file, string $ownerType, string|int $ownerId, string $category, User $uploader, string $classification = 'dalaman', array $metadata = []): string
    {
        if ($this->reject !== null) {
            throw new DocumentRejected($this->reject, 'virus');
        }
        $id = (string) Str::uuid();
        $this->docs[$id] = ['owner_type' => $ownerType, 'owner_id' => (string) $ownerId, 'category' => $category, 'name' => $file->getClientOriginalName(), 'uploader' => $uploader->id];

        return $id;
    }

    public function forOwner(string $ownerType, string|int $ownerId, User $user): array
    {
        return [];
    }

    public function temporaryUrl(string $documentId, User $user, int $minutes = 5): ?string
    {
        return isset($this->docs[$documentId]) ? 'https://dms.test/'.$documentId : null;
    }
}

class FakeWorkflowEngine implements WorkflowEngine
{
    /** @var array<string, array{flow: string, subjectType: string, subjectId: string, initiator: int, context: array<string, mixed>}> */
    public array $started = [];

    public function start(string $flowCode, string $subjectType, string|int $subjectId, User $initiator, array $context = [], ?string $title = null): string
    {
        $id = 'wf-'.(count($this->started) + 1);
        $this->started[$id] = ['flow' => $flowCode, 'subjectType' => $subjectType, 'subjectId' => (string) $subjectId, 'initiator' => $initiator->id, 'context' => $context];

        return $id;
    }

    public function status(string $instanceId): string
    {
        return 'berjalan';
    }

    public function activeInstanceFor(string $subjectType, string|int $subjectId): ?string
    {
        return null;
    }
}

class FakeKnowledgeBase implements KnowledgeBase
{
    /** @var list<array{query: string, user: int|null}> */
    public array $queries = [];

    public function search(string $query, ?User $user, int $limit = 5): array
    {
        $this->queries[] = ['query' => $query, 'user' => $user?->id];

        return array_map(fn (int $i) => [
            'dokumen' => 'Panduan '.$i, 'versi' => '1', 'seksyen' => 'Langkah '.$i, 'petikan' => 'Petikan berkaitan '.$query,
            'skor' => 1 - $i / 10, 'pautan' => '/admin/kba/artikel/'.$i,
        ], range(1, min($limit, 4)));
    }

    public function answer(string $question, ?User $user): array
    {
        return ['jawapan' => 'Jawapan bergrounding.', 'cukup_sumber' => true, 'sumber' => []];
    }
}

class FakeArticleDrafts implements ArticleDrafts
{
    /** @var array<string, array{id: int, data: array<string, mixed>, author: int|null, calls: int}> */
    public array $drafts = [];

    public function fromTicket(string $ticketId, array $data, ?User $author = null): int
    {
        $id = $this->drafts[$ticketId]['id'] ?? count($this->drafts) + 100;
        $this->drafts[$ticketId] = ['id' => $id, 'data' => $data, 'author' => $author?->id, 'calls' => ($this->drafts[$ticketId]['calls'] ?? 0) + 1];

        return $id;
    }

    public function forTicket(string $ticketId): array
    {
        return isset($this->drafts[$ticketId]) ? [['id' => $this->drafts[$ticketId]['id'], 'tajuk' => (string) $this->drafts[$ticketId]['data']['tajuk'], 'status' => 'draf', 'pautan' => '/admin/kba/artikel/'.$this->drafts[$ticketId]['id']]] : [];
    }
}

class FakeComplaintIntake implements ComplaintIntake
{
    /** @var array<string, array{data: array<string, mixed>, channel: string, reporter: int|null}> */
    public array $opened = [];

    public function open(array $data, string $channel, ?User $reporter = null, ?string $sourceRef = null): string
    {
        $key = $sourceRef ?? (string) Str::uuid();
        $this->opened[$key] ??= ['data' => $data, 'channel' => $channel, 'reporter' => $reporter?->id];

        return 'adu-'.substr(md5($key), 0, 8);
    }
}

class SpyCentral implements CentralTicketSystem
{
    /** @var list<array{nombor: string, payload: array<string, mixed>, key: string}> */
    public array $pushed = [];

    public bool $down = false;

    public function pushStatus(string $complaintNumber, array $payload, string $idempotencyKey): void
    {
        if ($this->down) {
            throw new RuntimeException('Sistem berpusat tidak dapat dihubungi');
        }
        $this->pushed[] = ['nombor' => $complaintNumber, 'payload' => $payload, 'key' => $idempotencyKey];
    }
}
