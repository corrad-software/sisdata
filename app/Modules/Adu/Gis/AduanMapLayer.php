<?php

namespace App\Modules\Adu\Gis;

use App\Models\User;
use App\Modules\Adu\Models\Aduan;
use App\Modules\Adu\Support\Lalai;
use App\Modules\Adu\Support\Rujukan;
use App\Modules\Gis\Contracts\MapLayer;

/**
 * SRS-ADU-16: "Aduan & isu" point layer for MOD-GIS, coloured by category
 * (legend) with status in the properties. Features come from Aduan, so the
 * user's daerah/mukim/klasifikasi scope applies (SRS-RBC-08). No reporter data.
 */
class AduanMapLayer implements MapLayer
{
    public const WARNA = [
        'PENCEROBOHAN' => '#dc2626',
        'BINAAN_HARAM' => '#ea580c',
        'PEMBUANGAN_SAMPAH' => '#a16207',
        'KEROSAKAN_ASET' => '#7c3aed',
        'PERTIKAIAN_SEMPADAN' => '#2563eb',
        'ISU_PENYEWA' => '#0891b2',
        'TUNGGAKAN' => '#4f46e5',
        'PENCEMARAN' => '#16a34a',
        'KESELAMATAN' => '#be123c',
        'LAIN' => '#6b7280',
    ];

    public function __construct(private Rujukan $ref) {}

    public function key(): string
    {
        return 'adu.aduan';
    }

    public function label(): string
    {
        return (string) __('adu::adu.peta.label');
    }

    public function group(): string
    {
        return 'risiko';
    }

    public function geometryType(): string
    {
        return 'point';
    }

    public function permission(): string
    {
        return 'adu.aduan.lihat';
    }

    public function legend(): array
    {
        $entries = [];
        foreach (self::WARNA as $kod => $warna) {
            $entries[] = ['nilai' => $kod, 'label' => (string) ($this->ref->namaKategori($kod) ?? $kod), 'warna' => $warna];
        }

        return ['medan' => 'kategori', 'entri' => $entries];
    }

    public function features(User $user, ?array $bbox = null, array $filters = [], int $limit = 5000): array
    {
        if (! $user->can($this->permission())) {
            return ['type' => 'FeatureCollection', 'features' => []];
        }
        $status = isset($filters['status']) && is_string($filters['status']) ? $filters['status'] : null;
        $query = Aduan::query()->bolehDilihat($user)->denganKoordinat()->whereNotNull('aduan.koordinat')
            ->when($bbox !== null, fn ($q) => $q->whereRaw('aduan.koordinat && ST_MakeEnvelope(?, ?, ?, ?, 4326)', $bbox))
            ->when($filters['kod_daerah'] ?? null, fn ($q, $v) => $q->where('aduan.kod_daerah', $v))
            ->when($filters['kod_mukim'] ?? null, fn ($q, $v) => $q->where('aduan.kod_mukim', $v))
            ->when($filters['kategori'] ?? null, fn ($q, $v) => $q->where('aduan.kategori', $v))
            ->when($status === 'terbuka', fn ($q) => $q->terbuka())
            ->when($status !== null && $status !== 'terbuka', fn ($q) => $q->where('aduan.status', $status))
            ->orderByDesc('aduan.tarikh_terima')->limit($limit);

        $features = [];
        foreach ($query->get() as $a) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => [(float) $a->getAttribute('lng'), (float) $a->getAttribute('lat')]],
                'properties' => [
                    'id' => $a->id,
                    'label' => $a->no_rujukan_aduan.' · '.$this->ref->namaKategori($a->kategori),
                    'pautan' => '/admin/adu/aduan/'.$a->id,
                    'kategori' => $a->kategori,
                    'status' => $a->status,
                    'status_nama' => Lalai::STATUS[$a->status] ?? $a->status,
                    'terbuka' => $a->terbuka(),
                    'keutamaan' => $a->tahap_keutamaan,
                    'warna' => self::WARNA[$a->kategori] ?? self::WARNA['LAIN'],
                    'bukan_tanah_mais' => $a->status_bukan_tanah_mais,
                ],
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }
}
