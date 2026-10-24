<?php

namespace App\Modules\Rbc\Services;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Rbc\Contracts\AccountStatus;
use App\Modules\Rbc\Events\UserRolesChanged;
use App\Modules\Rbc\Models\Peranan;
use App\Modules\Rbc\Models\RecertKeputusan;
use App\Modules\Rbc\Models\RecertKitaran;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Periodic access recertification (SRS-RBC-27): a cycle snapshots every
 * user × role assignment (optionally for one bahagian); the bahagian's data
 * owner confirms or withdraws each one; withdrawn roles are removed at once,
 * and on closing, anything not confirmed is withdrawn when the policy says so.
 */
class Recertification
{
    public function __construct(
        private AccessControl $access,
        private SecurityPolicy $policy,
    ) {}

    public function open(string $name, ?string $bahagian, ?Carbon $until = null, ?User $actor = null): RecertKitaran
    {
        return DB::transaction(function () use ($name, $bahagian, $until, $actor) {
            $cycle = RecertKitaran::query()->create([
                'nama' => $name,
                'bahagian' => $bahagian ?: null,
                'tarikh_mula' => Carbon::today(),
                'tarikh_akhir' => $until ?? Carbon::today()->addDays($this->policy->int('recert_tempoh_hari')),
                'status' => RecertKitaran::DIBUKA,
                'dicipta_oleh' => $actor?->id,
            ]);

            $assignments = DB::table('pengguna_peranan')
                ->join('users', 'users.id', '=', 'pengguna_peranan.user_id')
                ->where('users.status_akaun', AccountStatus::AKTIF)
                ->when($bahagian, fn ($q) => $q->where('users.bahagian', $bahagian))
                ->get(['pengguna_peranan.user_id', 'pengguna_peranan.peranan_id']);

            foreach ($assignments as $row) {
                RecertKeputusan::query()->create([
                    'kitaran_id' => $cycle->id,
                    'user_id' => $row->user_id,
                    'peranan_id' => $row->peranan_id,
                ]);
            }

            return $cycle;
        });
    }

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function decide(RecertKeputusan $decision, string $outcome, ?string $note, User $reviewer): RecertKeputusan
    {
        $cycle = $decision->kitaran;

        if ($cycle->status !== RecertKitaran::DIBUKA) {
            throw ValidationException::withMessages(['keputusan' => [__('rbc::rbc.recert.ditutup')]]);
        }

        if (! in_array($outcome, [RecertKeputusan::DISAHKAN, RecertKeputusan::DITARIK], true)) {
            throw ValidationException::withMessages(['keputusan' => [__('rbc::rbc.recert.keputusan_tidak_sah')]]);
        }

        if (! $this->canReview($reviewer, $cycle)) {
            throw new AuthorizationException(__('rbc::rbc.recert.luar_skop'));
        }

        // Segregation of duties: nobody recertifies their own access.
        if ($decision->user_id === $reviewer->id) {
            throw new AuthorizationException(__('rbc::rbc.recert.diri_sendiri'));
        }

        $decision->forceFill([
            'keputusan' => $outcome,
            'catatan' => $note,
            'disemak_oleh' => $reviewer->id,
            'disemak_pada' => Carbon::now(),
        ])->save();

        if ($outcome === RecertKeputusan::DITARIK) {
            $this->withdraw($decision, $reviewer);
        }

        return $decision;
    }

    public function close(RecertKitaran $cycle, ?User $actor = null): RecertKitaran
    {
        if ($cycle->status === RecertKitaran::DITUTUP) {
            return $cycle;
        }

        $withdraw = $this->policy->bool('recert_tarik_belum_disemak');

        foreach ($cycle->keputusan()->where('keputusan', RecertKeputusan::BELUM)->get() as $pending) {
            if ($withdraw) {
                $pending->forceFill([
                    'keputusan' => RecertKeputusan::DITARIK,
                    'catatan' => __('rbc::rbc.recert.tidak_disahkan'),
                    'disemak_oleh' => $actor?->id,
                    'disemak_pada' => Carbon::now(),
                ])->save();
                $this->withdraw($pending, $actor);
            }
        }

        $cycle->forceFill(['status' => RecertKitaran::DITUTUP, 'ditutup_pada' => Carbon::now()])->save();

        return $cycle;
    }

    /**
     * Scheduled: close overdue cycles; open a new one per bahagian when the
     * configured interval has passed since the last.
     *
     * @return array{dibuka: int, ditutup: int}
     */
    public function runSchedule(): array
    {
        $closed = 0;
        foreach (RecertKitaran::query()->where('status', RecertKitaran::DIBUKA)->whereDate('tarikh_akhir', '<', Carbon::today())->get() as $cycle) {
            $this->close($cycle);
            $closed++;
        }

        $opened = 0;
        $interval = $this->policy->int('recert_kitaran_hari');
        if ($interval > 0) {
            $divisions = DB::table('users')->where('status_akaun', AccountStatus::AKTIF)->distinct()->pluck('bahagian');

            foreach ($divisions as $bahagian) {
                $recent = RecertKitaran::query()
                    ->when($bahagian === null, fn ($q) => $q->whereNull('bahagian'), fn ($q) => $q->where('bahagian', $bahagian))
                    ->where('created_at', '>=', Carbon::now()->subDays($interval))
                    ->exists();

                if (! $recent) {
                    $this->open(__('rbc::rbc.recert.nama_auto', ['bahagian' => $bahagian ?? __('rbc::rbc.recert.tiada_bahagian'), 'tarikh' => Carbon::today()->format('d/m/Y')]), $bahagian);
                    $opened++;
                }
            }
        }

        return ['dibuka' => $opened, 'ditutup' => $closed];
    }

    public function canReview(User $reviewer, RecertKitaran $cycle): bool
    {
        if (! $this->access->allows($reviewer, 'rbc.recert.lulus')) {
            return false;
        }

        $scope = $this->access->scope($reviewer, AccessControl::SCOPE_BAHAGIAN);

        return $scope === null || ($cycle->bahagian !== null && in_array($cycle->bahagian, $scope, true));
    }

    private function withdraw(RecertKeputusan $decision, ?User $actor): void
    {
        $deleted = DB::table('pengguna_peranan')
            ->where('user_id', $decision->user_id)
            ->where('peranan_id', $decision->peranan_id)
            ->delete();

        if ($deleted) {
            RbcAccessControl::flushAll();
            $code = (string) Peranan::query()->whereKey($decision->peranan_id)->value('kod');
            UserRolesChanged::dispatch($decision->user, [], [$code], 'recert', $actor);
        }
    }
}
