<?php

namespace App\Modules\Rbc\Services;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccountStatus;
use App\Modules\Rbc\Events\AccountStatusChanged;
use App\Modules\Rbc\Models\StatusAkaunLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Account lifecycle (SRS-RBC-15..17). The only place users.status_akaun is
 * changed: logs STATUS_AKAUN_LOG, dispatches AccountStatusChanged (MOD-AUD
 * records it) and, for any non-Aktif status, ends every session and token
 * immediately (acceptance #2).
 */
class AccountStatusService
{
    public function __construct(private SessionManager $sessions, private SecurityPolicy $policy) {}

    public function change(User $user, string $to, ?string $reason = null, ?User $actor = null): bool
    {
        if (! in_array($to, AccountStatus::ALL, true)) {
            throw new InvalidArgumentException("Unknown account status [{$to}]");
        }

        $from = $user->status_akaun ?? AccountStatus::AKTIF;
        if ($from === $to) {
            return false;
        }

        DB::transaction(function () use ($user, $from, $to, $reason, $actor) {
            $attributes = ['status_akaun' => $to, 'is_active' => $to === AccountStatus::AKTIF];

            if ($to === AccountStatus::AKTIF) {
                // Reactivation restarts the inactivity clock and clears any lockout.
                $attributes += ['tarikh_diaktifkan_semula' => Carbon::now(), 'dikunci_hingga' => null, 'cubaan_gagal' => 0];

                // Reactivating an expired account without moving its end date would expire it again.
                if ($from === AccountStatus::TAMAT_TEMPOH && $user->tarikh_tamat_capaian?->isPast()) {
                    $attributes['tarikh_tamat_capaian'] = null;
                }
            }

            $user->forceFill($attributes)->save();

            StatusAkaunLog::query()->create([
                'user_id' => $user->id,
                'status_lama' => $from,
                'status_baru' => $to,
                'sebab' => $reason,
                'dilakukan_oleh' => $actor?->id,
            ]);
        });

        RbcAccessControl::flushAll();

        if ($to !== AccountStatus::AKTIF) {
            $this->sessions->terminateAllFor($user, SessionManager::STATUS_AKAUN, $actor);
        }

        AccountStatusChanged::dispatch($user, $from, $to, $reason);

        return true;
    }

    /**
     * SRS-RBC-16: Tamat Tempoh on/after the access end date; Gantung after
     * N days without login (from last login, reactivation or creation).
     * Returns the status the user should now have (and applies it).
     */
    public function applyAutomaticRules(User $user): string
    {
        if (($user->status_akaun ?? AccountStatus::AKTIF) !== AccountStatus::AKTIF) {
            return $user->status_akaun;
        }

        if ($user->tarikh_tamat_capaian && $user->tarikh_tamat_capaian->copy()->endOfDay()->isPast()) {
            $this->change($user, AccountStatus::TAMAT_TEMPOH, __('rbc::rbc.status.sebab_tamat_tempoh'));

            return AccountStatus::TAMAT_TEMPOH;
        }

        $days = $this->policy->int('tidak_aktif_gantung_hari');
        if ($days > 0) {
            $reference = collect([$user->tarikh_log_masuk_akhir, $user->tarikh_diaktifkan_semula, $user->created_at])
                ->filter()
                ->max();

            if ($reference && Carbon::parse($reference)->lt(Carbon::now()->subDays($days))) {
                $this->change($user, AccountStatus::GANTUNG, __('rbc::rbc.status.sebab_tidak_aktif', ['hari' => $days]));

                return AccountStatus::GANTUNG;
            }
        }

        return AccountStatus::AKTIF;
    }

    /** @return array{tamat_tempoh: int, gantung: int} */
    public function sweep(): array
    {
        $counts = ['tamat_tempoh' => 0, 'gantung' => 0];

        User::query()->where('status_akaun', AccountStatus::AKTIF)->chunkById(200, function ($users) use (&$counts) {
            foreach ($users as $user) {
                $result = $this->applyAutomaticRules($user);
                if ($result !== AccountStatus::AKTIF) {
                    $counts[$result]++;
                }
            }
        });

        return $counts;
    }
}
