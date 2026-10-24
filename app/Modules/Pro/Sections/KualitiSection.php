<?php

namespace App\Modules\Pro\Sections;

use App\Models\User;
use App\Modules\Pro\Contracts\ProfileSection;
use App\Modules\Pro\Models\CadanganDuplikasi;
use App\Modules\Pro\Models\TugasanKualitiData;
use App\Modules\Pro\Services\LotQuery;

/** Tab "Risiko & Isu": open data-quality tasks and pending duplicate suggestions of the lot. */
class KualitiSection implements ProfileSection
{
    public function __construct(private LotQuery $lots) {}

    public function key(): string
    {
        return 'kualiti_data';
    }

    public function tab(): string
    {
        return 'risiko';
    }

    public function label(): string
    {
        return (string) __('pro::pro.seksyen.kualiti_data');
    }

    public function forLot(string $lotId, User $user): ?array
    {
        if ($this->lots->find($user, $lotId) === null) {
            return null;
        }

        $items = [];
        foreach (TugasanKualitiData::query()->whereIn('entiti', ['lot_tanah', 'data_spatial'])->where('id_rekod', $lotId)->where('status', 'terbuka')->orderByDesc('id')->get() as $t) {
            $items[] = ['label' => (string) __('pro::pro.seksyen.tugasan'), 'nilai' => $t->keterangan, 'tahap' => $t->tahap_keterukan, 'link' => '/admin/pro/kualiti?tab=tugasan'];
        }
        $pairs = CadanganDuplikasi::query()->where('keputusan', 'menunggu')
            ->where(fn ($q) => $q->where('id_rekod_a', $lotId)->orWhere('id_rekod_b', $lotId))->get();
        foreach ($pairs as $p) {
            $items[] = ['label' => (string) __('pro::pro.seksyen.duplikasi'), 'nilai' => (string) __('pro::pro.duplikasi.skor', ['skor' => $p->skor_padanan]), 'link' => '/admin/pro/duplikasi?id='.$p->id];
        }

        $flags = [];
        if ($pairs->isNotEmpty()) {
            $flags[] = ['level' => 'warning', 'label' => (string) __('pro::pro.bendera.duplikasi')];
        }
        if (collect($items)->contains(fn ($i) => ($i['tahap'] ?? null) === 'ralat')) {
            $flags[] = ['level' => 'danger', 'label' => (string) __('pro::pro.bendera.kualiti_gagal')];
        }

        return ['items' => $items, 'flags' => $flags];
    }

    public function timeline(string $lotId, User $user): array
    {
        if ($this->lots->find($user, $lotId) === null) {
            return [];
        }

        return CadanganDuplikasi::query()->whereNotNull('tarikh_keputusan')
            ->where(fn ($q) => $q->where('id_rekod_a', $lotId)->orWhere('id_rekod_b', $lotId))->get()
            ->map(fn ($p) => [
                'date' => (string) $p->tarikh_keputusan?->toIso8601String(),
                'type' => 'keputusan_duplikasi',
                'title' => (string) __('pro::pro.garis_masa.duplikasi', ['keputusan' => __('pro::pro.keputusan_duplikasi.'.$p->keputusan)]),
                'link' => '/admin/pro/duplikasi?id='.$p->id,
            ])->values()->all();
    }
}
