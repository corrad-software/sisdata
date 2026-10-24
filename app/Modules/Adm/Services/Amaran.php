<?php

namespace App\Modules\Adm\Services;

use App\Models\User;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Alerts from MOD-ADM go through MOD-NTF (SRS-NTF-02), never mail directly.
 * Recipients are resolved by permission through MOD-RBC: "Bahagian ICT" /
 * Pentadbir Sistem = holders of adm.amaran.lihat (SRS-ADM-10/17).
 */
class Amaran
{
    public const PENERIMA_ICT = 'adm.amaran.lihat';

    public function __construct(private Notifier $notifier, private AccessControl $access) {}

    /**
     * @param  array<string, scalar|null>  $data
     */
    public function ict(string $type, array $data, ?string $link = null, ?string $dedupeKey = null): void
    {
        $this->toPermission(self::PENERIMA_ICT, $type, $data, $link, $dedupeKey);
    }

    /**
     * @param  array<string, scalar|null>  $data
     */
    public function toPermission(string $permission, string $type, array $data, ?string $link = null, ?string $dedupeKey = null, ?int $exceptUserId = null): void
    {
        $recipients = $this->holders($permission, $exceptUserId);

        // Always leave a trace in the application log, even with nobody to notify.
        Log::warning('ADM alert: '.$type, ['modul' => 'adm', 'kod_ralat' => $type, 'data' => $data, 'penerima' => count($recipients)]);

        if ($recipients === []) {
            return;
        }

        try {
            $this->notifier->send($type, $recipients, $data, $link, $dedupeKey);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Active users holding a permission. RBC has no reverse lookup in its
     * contract yet, so this filters active users through AccessControl.
     *
     * @return list<User>
     */
    public function holders(string $permission, ?int $exceptUserId = null): array
    {
        $holders = [];

        foreach (User::query()->where('is_active', true)->orderBy('id')->lazy(200) as $user) {
            if ($user->id !== $exceptUserId && $this->access->allows($user, $permission)) {
                $holders[] = $user;
            }
        }

        return $holders;
    }
}
