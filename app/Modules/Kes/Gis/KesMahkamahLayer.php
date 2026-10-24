<?php

namespace App\Modules\Kes\Gis;

use App\Models\User;
use App\Modules\Gis\Contracts\ClassifiedMapLayer;
use App\Modules\Kes\Models\KesMahkamah;
use App\Modules\Kes\Services\Rujukan;
use App\Modules\Kes\Support\Akses;
use Illuminate\Support\Facades\DB;

/**
 * "Lapisan Kes Mahkamah / Perundangan" (fills MOD-GIS's mandatory
 * kes_mahkamah slot, SRS-GIS-11; integration "Modul GIS bagi lapisan lot
 * dalam tindakan undang-undang"). Lot polygons of the cases the viewer may
 * see, coloured aktif / selesai. Rahsia (ClassifiedMapLayer): MOD-GIS also
 * requires gis.rahsia.lihat. Only the lot number and case counts are
 * exposed — no court numbers or parties.
 */
class KesMahkamahLayer implements ClassifiedMapLayer
{
    public function __construct(private Akses $akses, private Rujukan $ref) {}

    public function key(): string
    {
        return 'kes.kes_mahkamah';
    }

    public function label(): string
    {
        return (string) __('kes::kes.peta.label');
    }

    public function group(): string
    {
        return 'risiko';
    }

    public function geometryType(): string
    {
        return 'polygon';
    }

    public function permission(): string
    {
        return 'kes.rahsia.lihat';
    }

    public function klasifikasi(): string
    {
        return 'rahsia';
    }

    public function legend(): array
    {
        return ['medan' => 'status_kes', 'entri' => [
            ['nilai' => 'aktif', 'label' => (string) __('kes::kes.peta.aktif'), 'warna' => '#DC2626'],
            ['nilai' => 'selesai', 'label' => (string) __('kes::kes.peta.selesai'), 'warna' => '#9CA3AF'],
        ]];
    }

    public function features(User $user, ?array $bbox = null, array $filters = [], int $limit = 5000): array
    {
        $empty = ['type' => 'FeatureCollection', 'features' => []];
        if (! $this->akses->bolehLihat($user)) {
            return $empty;
        }
        $terminal = $this->ref->statusTerminal();
        $cases = KesMahkamah::query()->bolehDilihat($user)
            ->when($filters['jenis'] ?? null, fn ($q, $v) => $q->where('kes_mahkamah.jenis', $v))
            ->select('kes_mahkamah.id', 'kes_mahkamah.status');
        $placeholders = implode(',', array_fill(0, count($terminal), '?'));
        $q = DB::table('kes_mahkamah_lot as kl')
            ->joinSub($cases->toBase(), 'k', 'k.id', '=', 'kl.kes_mahkamah_id')
            ->join('data_spatial as ds', fn ($j) => $j->on('ds.lot_id', '=', 'kl.lot_id')->where('ds.semasa', true))
            ->groupBy('kl.lot_id', 'ds.id')
            ->selectRaw("kl.lot_id::text AS id, MAX(kl.no_lot) AS no_lot, MAX(kl.kod_daerah) AS kod_daerah, MAX(kl.kod_mukim) AS kod_mukim,
                COUNT(*) AS bil_kes, SUM(CASE WHEN k.status NOT IN ({$placeholders}) THEN 1 ELSE 0 END) AS bil_aktif,
                ST_AsGeoJSON(ST_SimplifyPreserveTopology(ds.geometri, 0.000005), 7) AS _g", $terminal)
            ->when($filters['kod_daerah'] ?? null, fn ($q, $v) => $q->where('kl.kod_daerah', $v))
            ->when($filters['kod_mukim'] ?? null, fn ($q, $v) => $q->where('kl.kod_mukim', $v))
            ->when($bbox !== null, fn ($q) => $q->whereRaw('ds.geometri && ST_MakeEnvelope(?, ?, ?, ?, 4326)', array_map('floatval', (array) $bbox)))
            ->limit(max(1, min($limit, 50000)));
        $status = isset($filters['status']) && is_string($filters['status']) ? $filters['status'] : null;

        $features = [];
        foreach ($q->get() as $r) {
            $aktif = (int) $r->bil_aktif > 0;
            if (($status === 'aktif' && ! $aktif) || ($status === 'selesai' && $aktif)) {
                continue;
            }
            $features[] = [
                'type' => 'Feature',
                'id' => $r->id,
                'geometry' => json_decode((string) $r->_g, true),
                'properties' => [
                    'id' => $r->id,
                    'label' => (string) $r->no_lot,
                    'pautan' => '/admin/kes/kes?lot_id='.$r->id,
                    'status_kes' => $aktif ? 'aktif' : 'selesai',
                    'bil_kes' => (int) $r->bil_kes,
                    'bil_aktif' => (int) $r->bil_aktif,
                    'kod_daerah' => $r->kod_daerah,
                    'kod_mukim' => $r->kod_mukim,
                    'warna' => $aktif ? '#DC2626' : '#9CA3AF',
                ],
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }
}
