<?php

namespace App\Modules\Dms\Services;

use App\Models\User;
use App\Modules\Dms\Contracts\RecordLinks;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Support\Str;

class RecordLinkRegistry implements RecordLinks
{
    /** @var array<string, array{label: string, path: string, permission: string|null}> */
    private array $types = [];

    public function __construct(private AccessControl $access) {}

    public function register(string $ownerType, string $label, string $pathTemplate, ?string $permission = null): void
    {
        $this->types[$ownerType] = ['label' => $label, 'path' => $pathTemplate, 'permission' => $permission];
    }

    public function resolve(string $ownerType, string $ownerId, User $user): array
    {
        $type = $this->types[$ownerType] ?? null;
        if ($type === null) {
            return ['label' => Str::headline($ownerType), 'link' => null];
        }
        if ($type['permission'] !== null && ! $this->access->allows($user, $type['permission'])) {
            return ['label' => $type['label'], 'link' => null];
        }

        return ['label' => $type['label'], 'link' => str_replace('{id}', rawurlencode($ownerId), $type['path'])];
    }

    /** @return array<string, array{label: string, path: string, permission: string|null}> */
    public function all(): array
    {
        return $this->types;
    }
}
