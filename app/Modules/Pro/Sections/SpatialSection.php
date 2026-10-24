<?php

namespace App\Modules\Pro\Sections;

use App\Models\User;
use App\Modules\Pro\Contracts\ProfileSection;
use App\Modules\Pro\Models\DataSpatial;
use App\Modules\Pro\Services\LotQuery;

/** Tab "Ruang & Aset": the lot's Data Spatial entity (SRS-PRO-07..10). */
class SpatialSection implements ProfileSection
{
    public function __construct(private LotQuery $lots) {}

    public function key(): string
    {
        return 'spatial';
    }

    public function tab(): string
    {
        return 'ruang';
    }

    public function label(): string
    {
        return (string) __('pro::pro.seksyen.spatial');
    }

    public function forLot(string $lotId, User $user): ?array
    {
        $lot = $this->lots->find($user, $lotId);
        if ($lot === null) {
            return null;
        }
        $s = DataSpatial::query()->where('lot_id', $lot->id)->where('semasa', true)->first();
        if ($s === null) {
            return ['items' => [], 'flags' => [['level' => 'danger', 'label' => (string) __('pro::pro.status_spatial.tiada_geometri')]], 'link' => '/admin/pro/lot/'.$lot->id.'?tab=ruang'];
        }
        $verifier = $s->id_pegawai_pengesah ? User::query()->find($s->id_pegawai_pengesah)?->name : null;

        $items = [
            ['label' => __('pro::pro.medan.status_pengesahan'), 'nilai' => __('pro::pro.status_spatial.'.$s->status_pengesahan), 'kod' => $s->status_pengesahan],
            ['label' => __('pro::pro.medan.sumber_data'), 'nilai' => __('pro::pro.sumber_spatial.'.$s->sumber_data)],
            ['label' => __('pro::pro.medan.srid_asal'), 'nilai' => 'EPSG:'.$s->srid_asal],
            ['label' => __('pro::pro.medan.keluasan_dikira'), 'nilai' => $s->keluasan_dikira !== null ? number_format((float) $s->keluasan_dikira, 2, '.', ',').' m²' : null],
            ['label' => __('pro::pro.medan.peratus_beza_keluasan'), 'nilai' => $s->peratus_beza_keluasan !== null ? $s->peratus_beza_keluasan.'%' : null],
            ['label' => __('pro::pro.medan.tarikh_perolehan_data'), 'nilai' => $s->tarikh_perolehan_data?->toDateString()],
            ['label' => __('pro::pro.medan.tarikh_kemas_kini'), 'nilai' => $s->updated_at?->toDateString()],
            ['label' => __('pro::pro.medan.id_pegawai_pengesah'), 'nilai' => $verifier],
            ['label' => __('pro::pro.medan.versi'), 'nilai' => $s->versi],
            ['label' => __('pro::pro.medan.catatan_pertindihan'), 'nilai' => $s->catatan_pertindihan],
        ];

        $flags = match (true) {
            $s->status_pengesahan === 'bertindih' => [['level' => 'danger', 'label' => (string) __('pro::pro.status_spatial.bertindih')]],
            ! $s->disahkan() => [['level' => 'warning', 'label' => (string) __('pro::pro.status_spatial.belum_disahkan')]],
            default => [],
        };

        return ['items' => $items, 'flags' => $flags, 'link' => '/admin/pro/lot/'.$lot->id.'?tab=ruang'];
    }

    public function timeline(string $lotId, User $user): array
    {
        $lot = $this->lots->find($user, $lotId);
        if ($lot === null) {
            return [];
        }
        $events = [];
        foreach (DataSpatial::query()->where('lot_id', $lot->id)->orderBy('versi')->get() as $s) {
            $events[] = ['date' => (string) $s->created_at?->toIso8601String(), 'type' => 'geometri', 'title' => (string) __('pro::pro.garis_masa.geometri', ['versi' => $s->versi, 'sumber' => $s->sumber_data]), 'link' => '/admin/pro/lot/'.$lot->id.'?tab=ruang'];
            if ($s->tarikh_pengesahan !== null) {
                $events[] = ['date' => $s->tarikh_pengesahan->toIso8601String(), 'type' => 'pengesahan_geometri', 'title' => (string) __('pro::pro.garis_masa.pengesahan', ['status' => __('pro::pro.status_spatial.'.$s->status_pengesahan)]), 'link' => '/admin/pro/lot/'.$lot->id.'?tab=ruang'];
            }
        }

        return $events;
    }
}
