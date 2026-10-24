<?php

namespace App\Modules\Tsu\Services;

use App\Models\User;
use App\Modules\Pro\Contracts\LotRegistry;
use App\Modules\Tsu\Contracts\LotRecordTransfers;
use App\Modules\Tsu\Models\PemindahanRekod;
use App\Modules\Tsu\Models\UrusanKeputusan;
use App\Modules\Tsu\Models\UrusanLot;
use App\Modules\Tsu\Models\UrusanTanah;
use App\Modules\Tsu\Support\AliranTsu;
use App\Modules\Tsu\Support\Makluman;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * SRS-TSU-20: record mapping screen (pajakan, cukai, aset…) from original to
 * successor lots, two-level confirmation through MOD-WFE (two DIFFERENT
 * officers, neither the one who mapped), then execution through the owning
 * modules' LotRecordTransfer handlers.
 */
class PemindahanService
{
    public function __construct(private LotRecordTransfers $registry, private WorkflowEngine $workflow, private LotRegistry $lots, private Makluman $makluman) {}

    /**
     * @return array{lot_asal: list<array<string, mixed>>, lot_baharu: list<array<string, mixed>>, jenis: list<array{kod: string, nama: string}>, pemetaan: list<array<string, mixed>>}
     */
    public function screen(UrusanTanah $urusan, User $user): array
    {
        $asal = UrusanLot::query()->where('urusan_id', $urusan->id)->where('peranan_lot', 'asal')->pluck('lot_id')->map(fn ($v) => (string) $v)->all();
        $baharu = UrusanLot::query()->where('urusan_id', $urusan->id)->where('peranan_lot', 'baharu')->pluck('lot_id')->map(fn ($v) => (string) $v)->all();
        $sum = $this->lots->summaries([...$asal, ...$baharu]);
        $records = [];
        foreach ($asal as $lotId) {
            $items = [];
            foreach ($this->registry->all() as $jenis => $h) {
                foreach ($h->forLot($lotId) as $r) {
                    $items[] = ['jenis_rekod' => $jenis, 'jenis_label' => $h->label(), 'id_rekod' => $r['id'], 'label' => $r['label']];
                }
            }
            $records[] = ['id' => $lotId, 'no_lot' => $sum[$lotId]['no_lot'] ?? null, 'rekod' => $items];
        }

        return [
            'lot_asal' => $records,
            'lot_baharu' => array_map(fn ($id) => ['id' => $id, 'no_lot' => $sum[$id]['no_lot'] ?? null], $baharu),
            'jenis' => array_values(array_map(fn ($h) => ['kod' => $h->jenis(), 'nama' => $h->label()], $this->registry->all())),
            'pemetaan' => PemindahanRekod::query()->where('urusan_id', $urusan->id)->orderBy('id')->get()->map(fn (PemindahanRekod $p) => $p->toArray())->all(),
        ];
    }

    /** @param list<array{jenis_rekod: string, id_rekod: string, id_lot_asal: string, id_lot_baharu: string, label_rekod?: string|null}> $mappings */
    public function map(UrusanTanah $urusan, array $mappings, User $user): int
    {
        if ($urusan->dikunci) {
            throw new HttpException(409, __('tsu::tsu.urusan.dikunci'));
        }
        if (PemindahanRekod::query()->where('urusan_id', $urusan->id)->where('status_pemindahan', 'menunggu_pengesahan')->exists()) {
            throw ValidationException::withMessages(['pemetaan' => [__('tsu::tsu.pemindahan.sedang_disahkan')]]);
        }
        $asal = UrusanLot::query()->where('urusan_id', $urusan->id)->where('peranan_lot', 'asal')->pluck('lot_id')->map(fn ($v) => (string) $v)->all();
        $baharu = UrusanLot::query()->where('urusan_id', $urusan->id)->where('peranan_lot', 'baharu')->pluck('lot_id')->map(fn ($v) => (string) $v)->all();
        if ($baharu === []) {
            throw ValidationException::withMessages(['pemetaan' => [__('tsu::tsu.pemindahan.tiada_lot_baharu')]]);
        }
        $handlers = $this->registry->all();
        foreach ($mappings as $m) {
            if (! in_array($m['id_lot_asal'], $asal, true) || ! in_array($m['id_lot_baharu'], $baharu, true) || ! isset($handlers[$m['jenis_rekod']])) {
                throw ValidationException::withMessages(['pemetaan' => [__('tsu::tsu.pemindahan.pemetaan_tidak_sah')]]);
            }
            $known = collect($handlers[$m['jenis_rekod']]->forLot($m['id_lot_asal']))->firstWhere('id', $m['id_rekod']);
            if ($known === null) {
                throw ValidationException::withMessages(['pemetaan' => [__('tsu::tsu.pemindahan.rekod_tiada')]]);
            }
        }

        return DB::transaction(function () use ($urusan, $mappings, $user, $handlers) {
            PemindahanRekod::query()->where('urusan_id', $urusan->id)->where('status_pemindahan', 'draf')->update(['status_pemindahan' => 'dibatalkan', 'updated_at' => now()]);
            foreach ($mappings as $m) {
                $label = collect($handlers[$m['jenis_rekod']]->forLot($m['id_lot_asal']))->firstWhere('id', $m['id_rekod'])['label'] ?? null;
                PemindahanRekod::query()->create([
                    'urusan_id' => $urusan->id, 'id_lot_asal' => $m['id_lot_asal'], 'id_lot_baharu' => $m['id_lot_baharu'], 'jenis_rekod' => $m['jenis_rekod'],
                    'id_rekod' => $m['id_rekod'], 'label_rekod' => $label, 'status_pemindahan' => 'draf', 'id_pencipta' => $user->id,
                ]);
            }

            return count($mappings);
        });
    }

    public function submit(UrusanTanah $urusan, User $user): string
    {
        $draft = PemindahanRekod::query()->where('urusan_id', $urusan->id)->where('status_pemindahan', 'draf')->get();
        if ($draft->isEmpty()) {
            throw ValidationException::withMessages(['pemetaan' => [__('tsu::tsu.pemindahan.tiada_draf')]]);
        }
        try {
            $instance = $this->workflow->start(AliranTsu::PEMINDAHAN, AliranTsu::SUBJEK_PEMINDAHAN, $urusan->id, $user, [
                'kod_daerah' => $urusan->kod_daerah, 'bilangan' => $draft->count(), 'pautan' => '/admin/tsu/urusan/'.$urusan->id.'?tab=pemindahan',
            ], 'Pemindahan rekod lot — '.$urusan->no_rujukan_mais);
        } catch (InvalidArgumentException) {
            throw new HttpException(422, __('tsu::tsu.aliran_tiada', ['kod' => AliranTsu::PEMINDAHAN]));
        }
        PemindahanRekod::query()->whereIn('id', $draft->pluck('id'))->update(['status_pemindahan' => 'menunggu_pengesahan', 'id_aliran_kerja' => $instance, 'updated_at' => now()]);

        return $instance;
    }

    /** StepDecided on the confirmation flow: remember who confirmed each level. */
    public function onStep(string $instanceId, ?string $stageCode, string $action, int $userId): void
    {
        if ($action !== 'lulus' || ! in_array($stageCode, ['pengesahan_1', 'pengesahan_2'], true)) {
            return;
        }
        PemindahanRekod::query()->where('id_aliran_kerja', $instanceId)->where('status_pemindahan', 'menunggu_pengesahan')
            ->update([$stageCode === 'pengesahan_1' ? 'id_pengesah_1' : 'id_pengesah_2' => $userId, 'updated_at' => now()]);
    }

    public function onCompleted(UrusanTanah $urusan, string $instanceId, string $outcome, ?int $decidedBy, ?string $remarks): void
    {
        $rows = PemindahanRekod::query()->where('id_aliran_kerja', $instanceId)->where('status_pemindahan', 'menunggu_pengesahan')->get();
        if ($rows->isEmpty()) {
            return;
        }
        $first = $rows->first();
        if ($outcome === 'lulus') {
            $p1 = $first->id_pengesah_1;
            $p2 = $first->id_pengesah_2 ?? $decidedBy;
            $makers = $rows->pluck('id_pencipta')->filter()->unique()->all();
            if ($p1 === null || $p2 === null || $p1 === $p2 || in_array($p1, $makers, true) || in_array($p2, $makers, true)) {
                $outcome = 'tolak';
                $remarks = (string) __('tsu::tsu.pemindahan.dua_pengesah');
            }
        }
        $user = $decidedBy ? User::query()->find($decidedBy) : null;
        $handlers = $this->registry->all();
        $done = 0;
        foreach ($rows as $r) {
            if ($outcome !== 'lulus') {
                $r->forceFill(['status_pemindahan' => 'ditolak', 'catatan' => $remarks])->save();

                continue;
            }
            try {
                $handlers[$r->jenis_rekod]->transfer($r->id_rekod, $r->id_lot_asal, $r->id_lot_baharu, $user ?? User::query()->findOrFail($r->id_pencipta), 'Urusan '.$urusan->no_rujukan_mais);
                $r->forceFill(['status_pemindahan' => 'dilaksanakan', 'id_pengesah_2' => $r->id_pengesah_2 ?? $decidedBy, 'tarikh_pemindahan' => now()])->save();
                $done++;
            } catch (Throwable $e) {
                report($e);
                $r->forceFill(['status_pemindahan' => 'gagal', 'catatan' => mb_substr($e->getMessage(), 0, 500)])->save();
            }
        }
        UrusanKeputusan::query()->create(['urusan_id' => $urusan->id, 'konteks' => 'pemindahan', 'jenis_keputusan' => $outcome, 'justifikasi' => $outcome === 'lulus' ? $done.' / '.$rows->count().' rekod dipindahkan. '.$remarks : $remarks, 'id_pelulus' => $decidedBy, 'id_aliran' => $instanceId]);
        $this->makluman->hantar('tsu.keputusan_kelulusan', $urusan->pegawai, ['no_rujukan' => $urusan->no_rujukan_mais, 'peringkat' => 'Pemindahan rekod lot', 'keputusan' => (string) __('tsu::tsu.keputusan.'.$outcome)], '/admin/tsu/urusan/'.$urusan->id.'?tab=pemindahan');
    }
}
