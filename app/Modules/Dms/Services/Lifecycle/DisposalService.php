<?php

namespace App\Modules\Dms\Services\Lifecycle;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Dms\Events\DocumentDisposed;
use App\Modules\Dms\Models\Dokumen;
use App\Modules\Dms\Models\DokumenPelupusan;
use App\Modules\Dms\Services\Storage\DocumentVault;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * SRS-DMS-23: disposal of an archived document happens only when MOD-WFE
 * reports the "dms.pelupusan" flow (two approval stages, configured by the
 * WFE admin) as approved. There is no other code path that destroys file
 * content. Disposal crypto-shreds (wrapped data keys deleted) and removes
 * the objects; the metadata row stays as a tombstone (status dilupus).
 */
class DisposalService
{
    public const FLOW = 'dms.pelupusan';

    public const SUBJECT = 'dokumen_pelupusan';

    public function __construct(
        private DocumentVault $vault,
        private AuditLogger $audit,
        private Notifier $notifier,
    ) {}

    public function request(Dokumen $doc, User $user, string $justification): DokumenPelupusan
    {
        if ($doc->status !== 'diarkib') {
            throw new HttpException(409, __('dms::mesej.bukan_diarkib'));
        }
        if (DokumenPelupusan::query()->where('dokumen_id', $doc->id)->where('status', 'menunggu')->exists()) {
            throw new HttpException(409, __('dms::mesej.pelupusan_sedang_berjalan'));
        }
        if (! app()->bound(WorkflowEngine::class)) {
            throw new HttpException(503, __('dms::mesej.aliran_tiada'));
        }

        return DB::transaction(function () use ($doc, $user, $justification) {
            $request = DokumenPelupusan::query()->create([
                'dokumen_id' => $doc->id,
                'justifikasi' => $justification,
                'dimohon_oleh' => $user->id,
                'status' => 'menunggu',
            ]);

            $instance = app(WorkflowEngine::class)->start(self::FLOW, self::SUBJECT, $request->id, $user, [
                'kategori' => $doc->kategori->kod,
                'klasifikasi' => $doc->klasifikasi_keselamatan,
                'modul_sumber' => $doc->modul_sumber,
                'kod_daerah' => $doc->kod_daerah,
            ], 'Pelupusan dokumen '.$doc->no_rujukan);

            $request->forceFill(['id_aliran_kerja' => $instance])->save();

            $this->audit->record('mohon_lupus', 'dms', 'dokumen', $doc->id, meta: [
                'justifikasi' => $justification, 'id_aliran_kerja' => $instance, 'id_permohonan' => $request->id,
            ]);

            return $request;
        });
    }

    /** Listener for WFE's WorkflowCompleted. */
    public function handle(WorkflowCompleted $event): void
    {
        if ($event->flowCode !== self::FLOW || $event->subjectType !== self::SUBJECT) {
            return;
        }

        $request = DokumenPelupusan::query()
            ->whereKey($event->subjectId)
            ->where('id_aliran_kerja', $event->instanceId)
            ->where('status', 'menunggu')
            ->first();
        if ($request === null) {
            return;
        }

        $approved = $event->outcome === 'lulus';
        $remarks = $event->remarks;
        // Segregation of duties: the requester can never be the final approver.
        if ($approved && $event->decidedBy !== null && $event->decidedBy === $request->dimohon_oleh) {
            $approved = false;
            $remarks = trim(($remarks ?? '').' [ditolak: pemohon tidak boleh meluluskan]');
        }

        $request->forceFill([
            'status' => $approved ? 'lulus' : ($event->outcome === 'lulus' ? 'tolak' : $event->outcome),
            'diputuskan_oleh' => $event->decidedBy,
            'catatan_keputusan' => $remarks,
            'tarikh_keputusan' => now(),
        ])->save();

        if ($approved) {
            $this->dispose($request, $event);
        } else {
            $this->audit->record('lupus', 'dms', 'dokumen', $request->dokumen_id, 'gagal', $event->outcome, meta: [
                'id_aliran_kerja' => $event->instanceId, 'catatan' => $remarks,
            ]);
        }

        if ($request->pemohon !== null) {
            $doc = $request->dokumen;
            $this->notifier->send('dms.pelupusan_keputusan', $request->pemohon, [
                'no_rujukan' => $doc->no_rujukan,
                'keputusan' => $approved ? 'diluluskan' : 'tidak diluluskan',
            ], '/admin/dms/'.$doc->id, 'dms.pelupusan:'.$request->id);
        }
    }

    private function dispose(DokumenPelupusan $request, WorkflowCompleted $event): void
    {
        $doc = $request->dokumen;
        $doc->loadMissing('versi');

        foreach ($doc->versi as $version) {
            if ($version->kunci_storan !== null) {
                $this->vault->delete($version->kunci_storan);
            }
        }

        DB::transaction(function () use ($doc) {
            foreach ($doc->versi as $version) {
                $version->forceFill(['kunci_storan' => null, 'kunci_data_bersulit' => null])->save();
            }
            $doc->forceFill([
                'status' => 'dilupus',
                'tarikh_lupus' => now(),
                'teks_kandungan' => null,
                'status_indeks' => 'tiada',
            ])->save();
        });

        $this->audit->record('lupus', 'dms', 'dokumen', $doc->id, reason: $request->justifikasi, changes: [
            'status' => ['diarkib', 'dilupus'],
        ], meta: [
            'justifikasi' => $request->justifikasi,
            'id_aliran_kerja' => $event->instanceId,
            'dimohon_oleh' => $request->dimohon_oleh,
            'diluluskan_oleh' => $event->decidedBy,
            'versi_dimusnahkan' => $doc->versi->count(),
        ]);

        DocumentDisposed::dispatch($doc->id, $doc->jenis_entiti_induk, $doc->id_rekod_induk);
    }
}
