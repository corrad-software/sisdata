<?php

namespace App\Modules\Pro\Services;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Aud\Contracts\AuditScope;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Pro\Models\JadualRetensi;
use App\Modules\Pro\Models\LotTanah;
use App\Modules\Pro\Models\RekodArkib;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Retention and archive (SRS-PRO-23, SRS-DAT-06):
 *  1. `pro:arkib-retensi` (scheduled) finds lots past their retention period
 *     per jadual_retensi and records them as proposals ("dicadang");
 *  2. an officer submits a proposal → MOD-WFE flow `pro.arkib_rekod`;
 *  3. on approval the lot is archived (status arkib) — still searchable
 *     with ?arkib=1 — and can be restored by an authorised role.
 * Archive and restore are both audited.
 */
class PengurusArkib
{
    public const FLOW = 'pro.arkib_rekod';

    public const SUBJECT = 'rekod_arkib';

    public function __construct(private WorkflowEngine $workflow, private AuditLogger $audit, private Notifier $notifier) {}

    /**
     * @return list<array{lot_id: string, no_lot: string, kod_daerah: string, kategori: string, id_jadual: int}>
     */
    public function candidates(): array
    {
        $out = [];
        foreach (JadualRetensi::query()->where('aktif', true)->where('entiti', 'lot_tanah')->get() as $schedule) {
            $statuses = (array) (((array) $schedule->syarat)['status_lot'] ?? []);
            if ($statuses === []) {
                continue;
            }
            $cutoff = now()->subYears((int) $schedule->tempoh_retensi_tahun);
            $lots = DB::table('lot_tanah')->whereNull('deleted_at')->whereIn('status_lot', $statuses)
                ->where('updated_at', '<=', $cutoff)
                ->whereNotExists(fn ($q) => $q->from('rekod_arkib')->whereColumn('rekod_arkib.id_rekod', DB::raw('lot_tanah.id::text'))
                    ->whereIn('status_arkib', ['dicadang', 'menunggu_kelulusan', 'diarkib']))
                ->get(['id', 'no_lot', 'kod_daerah']);
            foreach ($lots as $lot) {
                $out[] = ['lot_id' => (string) $lot->id, 'no_lot' => (string) $lot->no_lot, 'kod_daerah' => (string) $lot->kod_daerah, 'kategori' => (string) $schedule->kategori_rekod, 'id_jadual' => (int) $schedule->id];
            }
        }

        return $out;
    }

    /** Scheduled trigger: record every candidate as a proposal. */
    public function proposeDue(): int
    {
        $count = 0;
        foreach ($this->candidates() as $c) {
            RekodArkib::query()->create([
                'entiti' => 'lot_tanah',
                'id_rekod' => $c['lot_id'],
                'id_jadual_retensi' => $c['id_jadual'],
                'status_arkib' => 'dicadang',
                'sebab' => __('pro::pro.arkib.sebab_retensi', ['kategori' => $c['kategori']]),
            ]);
            $count++;
        }

        return $count;
    }

    /** Manual proposal for one lot, submitted straight for approval. */
    public function proposeLot(LotTanah $lot, User $user, string $reason): RekodArkib
    {
        if ($lot->status_lot === 'arkib') {
            throw ValidationException::withMessages(['lot' => [__('pro::pro.arkib.sudah_diarkib')]]);
        }
        $open = RekodArkib::query()->where('entiti', 'lot_tanah')->where('id_rekod', $lot->id)->whereIn('status_arkib', ['dicadang', 'menunggu_kelulusan'])->first();
        $record = $open ?? RekodArkib::query()->create([
            'entiti' => 'lot_tanah', 'id_rekod' => $lot->id, 'status_arkib' => 'dicadang', 'sebab' => $reason, 'id_pemohon' => $user->id,
        ]);

        return $this->submit($record, $user);
    }

    public function submit(RekodArkib $record, User $user): RekodArkib
    {
        if ($record->status_arkib !== 'dicadang') {
            throw ValidationException::withMessages(['status_arkib' => [__('pro::pro.arkib.bukan_cadangan')]]);
        }
        $lot = LotTanah::query()->withoutGlobalScope('access')->find($record->id_rekod);

        try {
            $instance = $this->workflow->start(self::FLOW, self::SUBJECT, $record->id, $user, [
                'kod_daerah' => $lot?->kod_daerah,
                'bahagian_pemilik' => $lot?->bahagian_pemilik,
                'pautan' => '/admin/pro/arkib',
            ], __('pro::pro.arkib.tajuk_aliran', ['no_lot' => $lot->no_lot ?? $record->id_rekod]));
        } catch (InvalidArgumentException) {
            throw new HttpException(422, __('pro::pro.lot.aliran_tiada', ['kod' => self::FLOW]));
        }

        $record->update(['status_arkib' => 'menunggu_kelulusan', 'id_aliran_kerja' => $instance, 'id_pemohon' => $record->id_pemohon ?? $user->id]);

        return $record;
    }

    public function onWorkflowCompleted(WorkflowCompleted $event): void
    {
        if ($event->flowCode !== self::FLOW || $event->subjectType !== self::SUBJECT) {
            return;
        }
        $record = RekodArkib::query()->find($event->subjectId);
        if ($record === null || $record->status_arkib !== 'menunggu_kelulusan') {
            return;
        }

        if ($event->outcome !== 'lulus') {
            $record->update(['status_arkib' => 'ditolak', 'id_pelulus_arkib' => $event->decidedBy, 'sebab' => trim(($record->sebab ?? '').' '.($event->remarks ?? ''))]);

            return;
        }

        $lot = LotTanah::query()->withoutGlobalScope('access')->find($record->id_rekod);
        if ($lot === null) {
            return;
        }

        DB::transaction(function () use ($record, $lot, $event) {
            AuditScope::withReason((string) ($record->sebab ?: __('pro::pro.arkib.sebab_lalai')), fn () => $lot->update([
                'status_sebelum_arkib' => $lot->status_lot,
                'status_lot' => 'arkib',
                'diarkib_pada' => now(),
            ]));
            $schedule = $record->id_jadual_retensi ? JadualRetensi::query()->find($record->id_jadual_retensi) : null;
            $record->update([
                'status_arkib' => 'diarkib',
                'id_pelulus_arkib' => $event->decidedBy,
                'tarikh_arkib' => now(),
                'tarikh_layak_lupus' => $schedule ? now()->addYears((int) $schedule->tempoh_retensi_tahun)->toDateString() : null,
            ]);
        });
        $this->audit->record('arkib', 'pro', 'lot_tanah', $lot->id, meta: ['id_rekod_arkib' => $record->id, 'klasifikasi' => $lot->klasifikasi]);

        if ($record->id_pemohon && ($requester = User::query()->find($record->id_pemohon))) {
            try {
                $this->notifier->send('pro.arkib_keputusan', $requester, ['no_lot' => $lot->no_lot, 'keputusan' => (string) __('pro::pro.keputusan.lulus')], '/admin/pro/arkib');
            } catch (Throwable) {
            }
        }
    }

    public function restore(LotTanah $lot, User $user, string $reason): LotTanah
    {
        if ($lot->status_lot !== 'arkib') {
            throw ValidationException::withMessages(['lot' => [__('pro::pro.arkib.bukan_arkib')]]);
        }

        DB::transaction(function () use ($lot, $user, $reason) {
            AuditScope::withReason($reason, fn () => $lot->update([
                'status_lot' => $lot->status_sebelum_arkib && $lot->status_sebelum_arkib !== 'arkib' ? $lot->status_sebelum_arkib : 'aktif',
                'status_sebelum_arkib' => null,
                'diarkib_pada' => null,
            ]));
            RekodArkib::query()->where('entiti', 'lot_tanah')->where('id_rekod', $lot->id)->where('status_arkib', 'diarkib')
                ->update(['status_arkib' => 'dipulih', 'id_pemulih' => $user->id, 'tarikh_pulih' => now(), 'updated_at' => now()]);
        });
        $this->audit->record('pulih', 'pro', 'lot_tanah', $lot->id, reason: $reason, meta: ['klasifikasi' => $lot->klasifikasi]);

        return $lot->refresh();
    }
}
