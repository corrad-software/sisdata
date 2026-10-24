<?php

namespace App\Modules\Tsu\Support;

use App\Models\User;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Wfe\Contracts\ApproverDirectory;
use Throwable;

/**
 * Sends MOD-TSU notifications through MOD-NTF (never mail directly). A
 * notification failure never blocks the business action. Recipients may be
 * empty: the type's admin-configured rules (e.g. Bahagian Kewangan, GIS
 * Specialist) are always added by MOD-NTF.
 */
class Makluman
{
    public function __construct(private Notifier $notifier, private ApproverDirectory $directory) {}

    /**
     * @param  list<User|null>|User|null  $to
     * @param  array<string, scalar|null>  $data
     */
    public function hantar(string $type, array|User|null $to, array $data, ?string $link = null, ?string $dedupe = null): void
    {
        $recipients = array_values(array_filter(is_array($to) ? $to : [$to]));
        $unique = [];
        foreach ($recipients as $u) {
            $unique[$u->id] = $u;
        }
        try {
            $this->notifier->send($type, array_values($unique), $data, $link, $dedupe);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * The officer plus their supervisors (head of division) — SRS-TSU-23 "pegawai bertanggungjawab dan ketua bahagian".
     *
     * @return list<User>
     */
    public function denganKetua(?User $pegawai): array
    {
        if ($pegawai === null) {
            return [];
        }
        try {
            return [$pegawai, ...array_slice($this->directory->supervisorsOf($pegawai), 0, 2)];
        } catch (Throwable) {
            return [$pegawai];
        }
    }
}
