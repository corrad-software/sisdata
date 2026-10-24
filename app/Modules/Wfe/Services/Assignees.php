<?php

namespace App\Modules\Wfe\Services;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Wfe\Contracts\ApproverDirectory;
use App\Modules\Wfe\Models\Kumpulan;
use App\Modules\Wfe\Models\Pemangku;
use Illuminate\Support\Carbon;

/**
 * Turns approver definitions (role / position / individual / group) into
 * task assignee keys ("peranan:pegawai_hq", "individu:12"…), and answers
 * which keys a user may act on — directly or as pemangku (SRS-WFE-05/21).
 */
class Assignees
{
    public function __construct(private ApproverDirectory $directory, private AccessControl $access) {}

    public static function key(string $type, string|int|null $value): string
    {
        return $type.':'.$value;
    }

    /**
     * Keys the user holds personally.
     *
     * @return list<string>
     */
    public function ownKeys(User $user): array
    {
        $keys = [self::key('individu', $user->id)];
        foreach ($this->access->roles($user) as $role) {
            $keys[] = self::key('peranan', $role);
        }
        if ($position = $this->directory->positionOf($user)) {
            $keys[] = self::key('jawatan', $position);
        }
        foreach (Kumpulan::query()->whereHas('ahli', fn ($q) => $q->whereKey($user->id))->pluck('kod') as $group) {
            $keys[] = self::key('kumpulan', $group);
        }

        return array_values(array_unique($keys));
    }

    /**
     * Every key the user may act on, mapped to the user they act for (null
     * = themselves). Own keys win over delegated ones.
     *
     * @return array<string, int|null>
     */
    public function actingKeys(User $user, ?Carbon $on = null): array
    {
        $keys = [];
        $delegations = Pemangku::query()->berkuatKuasa($on)->where('id_pemangku', $user->id)->with('pengguna')->get();
        foreach ($delegations as $delegation) {
            if (! $delegation->pengguna->is_active) {
                continue;
            }
            foreach ($this->ownKeys($delegation->pengguna) as $key) {
                $keys[$key] ??= $delegation->id_pengguna;
            }
        }
        foreach ($this->ownKeys($user) as $key) {
            $keys[$key] = null;
        }

        return $keys;
    }

    /** Active pemangku for a user today, if any. */
    public function delegateOf(User $user, ?Carbon $on = null): ?User
    {
        $delegation = Pemangku::query()->berkuatKuasa($on)->where('id_pengguna', $user->id)->latest('id')->with('pemangku')->first();

        return $delegation?->pemangku?->is_active ? $delegation->pemangku : null;
    }

    /**
     * Users behind an assignee key.
     *
     * @return list<User>
     */
    public function members(string $type, ?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $person = $type === 'individu' ? User::query()->whereKey((int) $value)->where('is_active', true)->first() : null;

        return match ($type) {
            'individu' => $person ? [$person] : [],
            'peranan' => $this->directory->usersWithRole($value),
            'jawatan' => $this->directory->usersWithPosition($value),
            'kumpulan' => Kumpulan::query()->where('kod', $value)->first()?->ahli()->where('is_active', true)->orderBy('users.id')->get()->all() ?? [],
            default => [],
        };
    }

    /**
     * Users to notify for an assignee: its members plus, for anyone away,
     * their active pemangku.
     *
     * @return list<User>
     */
    public function recipients(string $type, ?string $value): array
    {
        $users = [];
        foreach ($this->members($type, $value) as $member) {
            $users[$member->id] = $member;
            if ($delegate = $this->delegateOf($member)) {
                $users[$delegate->id] = $delegate;
            }
        }

        return array_values($users);
    }

    public function supervisorsOf(User $user): array
    {
        return $this->directory->supervisorsOf($user);
    }

    /** Human label for a key, for the UI. */
    public function label(string $type, ?string $value): string
    {
        return match ($type) {
            'individu' => (string) (User::query()->whereKey((int) $value)->value('name') ?? __('wfe::wfe.unknown_user')),
            'kumpulan' => (string) (Kumpulan::query()->where('kod', $value)->value('nama') ?? $value),
            default => (string) $value,
        };
    }
}
