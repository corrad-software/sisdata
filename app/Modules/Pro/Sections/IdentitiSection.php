<?php

namespace App\Modules\Pro\Sections;

use App\Models\User;
use App\Modules\Pro\Contracts\ProfileSection;
use App\Modules\Pro\Models\VersiRekodInduk;
use App\Modules\Pro\Services\LotPresenter;
use App\Modules\Pro\Services\LotQuery;

/** Tab "Identiti & Hak Milik": the master lot record itself. */
class IdentitiSection implements ProfileSection
{
    public function __construct(private LotQuery $lots, private LotPresenter $presenter) {}

    public function key(): string
    {
        return 'lot_identiti';
    }

    public function tab(): string
    {
        return 'identiti';
    }

    public function label(): string
    {
        return (string) __('pro::pro.seksyen.lot_identiti');
    }

    public function forLot(string $lotId, User $user): ?array
    {
        $lot = $this->lots->find($user, $lotId);
        if ($lot === null) {
            return null;
        }
        $d = $this->presenter->detail($lot, $user);
        $masked = (array) $d['medan_disamarkan'];
        $value = fn (string $field, mixed $v) => in_array($field, $masked, true) ? (string) __('pro::pro.disamarkan') : $v;

        $items = [
            ['label' => __('pro::pro.medan.no_lot'), 'nilai' => $d['no_lot']],
            ['label' => __('pro::pro.medan.kod_mukim'), 'nilai' => trim(($d['nama_mukim'] ?? '').' ('.$d['kod_mukim'].')')],
            ['label' => __('pro::pro.medan.kod_daerah'), 'nilai' => trim(($d['nama_daerah'] ?? '').' ('.$d['kod_daerah'].')')],
            ['label' => __('pro::pro.medan.keluasan'), 'nilai' => $d['keluasan'] !== null ? $d['keluasan'].' '.$d['unit_keluasan'] : null],
            ['label' => __('pro::pro.medan.kategori_guna_tanah'), 'nilai' => $d['nama_kategori_guna_tanah'] ?? $d['kategori_guna_tanah']],
            ['label' => __('pro::pro.medan.zoning'), 'nilai' => $d['zoning']],
            ['label' => __('pro::pro.medan.syarat_nyata'), 'nilai' => $value('syarat_nyata', $d['syarat_nyata'])],
            ['label' => __('pro::pro.medan.bahagian_pemilik'), 'nilai' => $d['bahagian_pemilik'] ? __('pro::pro.bahagian.'.$d['bahagian_pemilik']) : null],
            ['label' => __('pro::pro.medan.status_lot'), 'nilai' => __('pro::pro.status_lot.'.$d['status_lot'])],
            ['label' => __('pro::pro.medan.klasifikasi'), 'nilai' => __('pro::pro.klasifikasi.'.$d['klasifikasi'])],
            ['label' => __('pro::pro.medan.id_data_owner'), 'nilai' => $d['nama_data_owner']],
            ['label' => __('pro::pro.medan.id_data_steward'), 'nilai' => $d['nama_data_steward']],
            ['label' => __('pro::pro.medan.versi_rekod'), 'nilai' => $d['versi_rekod']],
            ['label' => __('pro::pro.medan.sumber_sistem'), 'nilai' => $d['salasilah']['sumber_sistem'] ?? null],
            ['label' => __('pro::pro.medan.kunci_asal'), 'nilai' => $d['salasilah']['kunci_asal'] ?? null],
        ];

        $flags = [];
        if ($lot->sintetik) {
            $flags[] = ['level' => 'info', 'label' => (string) __('pro::pro.bendera.sintetik')];
        }
        if ($lot->status_lot === 'arkib') {
            $flags[] = ['level' => 'warning', 'label' => (string) __('pro::pro.bendera.arkib')];
        }
        if (VersiRekodInduk::query()->where('entiti', 'lot_tanah')->where('id_rekod', $lot->id)->where('status_pengesahan', 'menunggu')->exists()) {
            $flags[] = ['level' => 'warning', 'label' => (string) __('pro::pro.bendera.kritikal_tertunda')];
        }

        return ['items' => $items, 'flags' => $flags, 'link' => '/admin/pro/lot/'.$lot->id.'/kemas-kini'];
    }

    public function timeline(string $lotId, User $user): array
    {
        $lot = $this->lots->find($user, $lotId);
        if ($lot === null) {
            return [];
        }
        $events = [[
            'date' => (string) $lot->created_at?->toIso8601String(),
            'type' => 'rekod_dicipta',
            'title' => (string) __('pro::pro.garis_masa.dicipta', ['no_lot' => $lot->no_lot]),
            'link' => '/admin/pro/lot/'.$lot->id,
        ]];
        foreach (VersiRekodInduk::query()->where('entiti', 'lot_tanah')->where('id_rekod', $lot->id)->where('kritikal', true)->whereIn('status_pengesahan', ['lulus', 'tolak'])->get() as $v) {
            $events[] = [
                'date' => (string) ($v->tarikh_pengesahan ?? $v->created_at)?->toIso8601String(),
                'type' => 'perubahan_kritikal',
                'title' => (string) __('pro::pro.garis_masa.kritikal_'.$v->status_pengesahan, ['medan' => implode(', ', array_keys((array) $v->nilai_selepas))]),
                'link' => '/admin/pro/lot/'.$lot->id.'?tab=dokumen',
            ];
        }

        return $events;
    }
}
