<?php

namespace Tests\Modules\Per\Support;

use App\Models\User;
use App\Modules\Hkm\Contracts\TitleRegistry;
use Illuminate\Support\Str;

/** Stand-in for MOD-HKM: records every title + vault deposit it is asked for. */
class FakeTitleRegistry implements TitleRegistry
{
    /** @var list<array{id: string, lot_id: string, data: array<string, mixed>, vault: array<string, mixed>|null, source: string|null, actor: int}> */
    public array $titles = [];

    public function registerFromAcquisition(string $lotId, array $data, User $actor, ?array $vault = null, ?string $sourceRef = null): string
    {
        $id = (string) Str::uuid();
        $this->titles[] = ['id' => $id, 'lot_id' => $lotId, 'data' => $data, 'vault' => $vault, 'source' => $sourceRef, 'actor' => $actor->id];

        return $id;
    }

    public function forLot(string $lotId, User $user): array
    {
        return array_values(array_map(fn ($t) => [
            'id' => $t['id'], 'no_hak_milik' => (string) $t['data']['no_hak_milik'], 'jenis_hak_milik' => (string) $t['data']['jenis_hak_milik'],
            'status_semasa' => 'aktif', 'tarikh_tamat_tempoh' => null,
        ], array_filter($this->titles, fn ($t) => $t['lot_id'] === $lotId)));
    }
}
