<?php

namespace App\Modules\Pjk\Services;

use App\Models\User;
use App\Modules\Pjk\Models\Bayaran;
use App\Modules\Pjk\Models\DepositCagaran;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Support\Aliran;
use App\Modules\Pjk\Support\Penomboran;
use App\Modules\Pjk\Support\Wang;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * SRS-PJK-13: deposit settlement at the end of an agreement — deduction
 * (applied to the tenant's arrears) and refund — approved by Finance through
 * WFE `pjk.penyelarasan_deposit`.
 */
class DepositService
{
    public function __construct(private WorkflowEngine $workflow, private BayaranService $bayaran, private LejarService $lejar) {}

    /** @param array<string, mixed> $input */
    public function requestSettlement(DepositCagaran $deposit, array $input, User $user): DepositCagaran
    {
        $kontrak = Kontrak::query()->findOrFail($deposit->kontrak_id);
        if ($deposit->status !== 'dipegang') {
            throw ValidationException::withMessages(['status' => __('pjk::pjk.ralat.deposit_bukan_dipegang')]);
        }
        if (! in_array($kontrak->status, ['ditamatkan', 'tamat_tempoh'], true)) {
            throw ValidationException::withMessages(['kontrak' => __('pjk::pjk.ralat.deposit_kontrak_aktif')]);
        }
        $data = Validator::make($input, [
            'amaun_tolakan' => ['required', 'decimal:0,2', 'min:0', 'lte:'.$deposit->amaun],
            'justifikasi_tolakan' => ['nullable', 'string', 'min:5', 'max:3000'],
        ], [], ['amaun_tolakan' => 'amaun tolakan', 'justifikasi_tolakan' => 'justifikasi tolakan'])->validate();
        if (Wang::sen((string) $data['amaun_tolakan']) > 0 && empty($data['justifikasi_tolakan'])) {
            throw ValidationException::withMessages(['justifikasi_tolakan' => __('pjk::pjk.ralat.justifikasi_tolakan')]);
        }

        return DB::transaction(function () use ($deposit, $kontrak, $data, $user) {
            $tolakan = Wang::sen((string) $data['amaun_tolakan']);
            $deposit->forceFill([
                'status' => 'dalam_penyelarasan', 'amaun_tolakan' => Wang::dec($tolakan), 'justifikasi_tolakan' => $data['justifikasi_tolakan'] ?? null,
                'amaun_dipulangkan' => Wang::dec(Wang::sen($deposit->amaun) - $tolakan), 'id_pemohon_penyelarasan' => $user->id,
            ])->save();
            $instance = Aliran::start($this->workflow, Aliran::DEPOSIT, $deposit->id, $user, [
                'amaun' => Wang::sen($deposit->amaun) / 100, 'amaun_tolakan' => $tolakan / 100, 'kod_daerah' => $kontrak->kod_daerah,
                'pautan' => '/admin/pjk/kontrak/'.$kontrak->id.'?tab=deposit',
            ], __('pjk::pjk.aliran.tajuk_deposit', ['no' => $kontrak->no_kontrak]));
            $deposit->forceFill(['id_instans_aliran' => $instance])->save();

            return $deposit;
        });
    }

    public function onWorkflowCompleted(WorkflowCompleted $event): void
    {
        if ($event->flowCode !== Aliran::DEPOSIT) {
            return;
        }
        $deposit = DepositCagaran::query()->find($event->subjectId);
        if ($deposit === null || $deposit->status !== 'dalam_penyelarasan') {
            return;
        }
        DB::transaction(function () use ($deposit, $event) {
            if ($event->outcome !== 'lulus' || $event->decidedBy === $deposit->id_pemohon_penyelarasan) {
                $deposit->forceFill(['status' => 'dipegang', 'amaun_tolakan' => '0.00', 'amaun_dipulangkan' => null, 'id_instans_aliran' => null])->save();

                return;
            }
            $kontrak = Kontrak::query()->withoutGlobalScope('access')->findOrFail($deposit->kontrak_id);
            $tolakan = Wang::sen($deposit->amaun_tolakan);
            if ($tolakan > 0) {
                // The deduction settles arrears like an internal payment (no receipt: no money received).
                $internal = Bayaran::query()->create([
                    'no_bayaran' => Penomboran::next('bayaran'), 'kontrak_id' => $kontrak->id, 'saluran' => 'kaunter',
                    'amaun' => Wang::dec($tolakan), 'tarikh_bayaran' => Carbon::today()->format('Y-m-d'), 'status' => 'disahkan',
                    'rujukan_luar' => 'DEPOSIT-'.$deposit->id, 'id_pengesah' => $event->decidedBy, 'tarikh_pengesahan' => now(),
                    'catatan' => __('pjk::pjk.deposit.tolakan', ['justifikasi' => $deposit->justifikasi_tolakan]),
                ]);
                $this->bayaran->allocate($internal, $kontrak);
                $this->lejar->post($kontrak, 'tolakan_deposit', 0, $tolakan, [
                    'bayaran_id' => $internal->id, 'id_pegawai' => $event->decidedBy, 'status_pelarasan' => 'diluluskan',
                    'keterangan' => mb_substr(__('pjk::pjk.deposit.tolakan', ['justifikasi' => $deposit->justifikasi_tolakan]), 0, 500),
                ]);
            }
            $refund = Wang::sen((string) $deposit->amaun_dipulangkan);
            $deposit->forceFill([
                'status' => $refund > 0 ? 'dipulangkan' : 'dilucut_hak', 'tarikh_pulangan' => Carbon::today()->format('Y-m-d'), 'id_pelulus' => $event->decidedBy,
            ])->save();
        });
    }
}
