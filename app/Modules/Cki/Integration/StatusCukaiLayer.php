<?php

namespace App\Modules\Cki\Integration;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Cki\Support\Akses;
use App\Modules\Gis\Contracts\ClassifiedMapLayer;
use App\Modules\Gis\Contracts\VectorTileLayer;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * MOD-GIS mandatory slot "Lapisan Status Cukai" (SRS-GIS-11, key prefix
 * cki.): lot polygons coloured by tax status — selesai, belum dibayar,
 * tertunggak, tertunggak melebihi ambang MAIS (parameter
 * cki.ambang_tunggakan_hari). Served as vector tiles; the source SQL is built
 * from the user's scoped bill query, so GIS cannot bypass district scoping.
 */
class StatusCukaiLayer implements ClassifiedMapLayer, VectorTileLayer
{
    public const STATUS = [
        'selesai' => ['Selesai', '#16A34A'],
        'belum_dibayar' => ['Belum dibayar', '#EAB308'],
        'tertunggak' => ['Tertunggak', '#DC2626'],
        'tertunggak_ambang' => ['Tertunggak melebihi ambang MAIS', '#7F1D1D'],
    ];

    public function __construct(private Akses $akses, private AccessControl $access, private Parameters $parameters) {}

    public function key(): string
    {
        return 'cki.status_cukai';
    }

    public function label(): string
    {
        return __('cki::cki.peta.label');
    }

    public function group(): string
    {
        return 'operasi';
    }

    public function geometryType(): string
    {
        return 'polygon';
    }

    public function permission(): string
    {
        return 'cki.bil.lihat';
    }

    public function klasifikasi(): string
    {
        return 'dalaman';
    }

    public function legend(): array
    {
        return ['medan' => 'status_cukai', 'entri' => array_map(fn ($k, $v) => ['nilai' => $k, 'label' => $v[0], 'warna' => $v[1]], array_keys(self::STATUS), self::STATUS)];
    }

    public function tileSource(User $user, array $filters = []): array
    {
        [$sql, $bindings] = $this->source($user, $filters);

        return ['sql' => $sql, 'bindings' => $bindings, 'columns' => ['id', 'label', 'pautan', 'status_cukai', 'baki', 'bil_tertunggak']];
    }

    public function scopeSignature(User $user): string
    {
        $parts = [];
        foreach ([AccessControl::SCOPE_DAERAH, AccessControl::SCOPE_MUKIM, AccessControl::SCOPE_KLASIFIKASI] as $t) {
            $s = $this->access->scope($user, $t);
            $parts[] = $t.'='.($s === null ? '*' : implode(',', $s));
        }

        return substr(sha1(implode('|', $parts).'|'.$this->ambang()), 0, 16);
    }

    public function features(User $user, ?array $bbox = null, array $filters = [], int $limit = 5000): array
    {
        if (! $this->access->allows($user, $this->permission())) {
            return ['type' => 'FeatureCollection', 'features' => []];
        }
        [$sql, $bindings] = $this->source($user, $filters);
        $query = "SELECT s.id, s.label, s.pautan, s.status_cukai, s.baki, s.bil_tertunggak, ST_AsGeoJSON(s.geom, 7) AS _g FROM ({$sql}) s WHERE s.geom IS NOT NULL";
        if ($bbox !== null) {
            $query .= ' AND s.geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)';
            array_push($bindings, ...array_map('floatval', $bbox));
        }
        $query .= ' LIMIT '.max(1, min($limit, 50000));
        $features = [];
        foreach (DB::select($query, $bindings) as $r) {
            $features[] = [
                'type' => 'Feature',
                'id' => $r->id,
                'geometry' => json_decode((string) $r->_g, true),
                'properties' => [
                    'id' => (string) $r->id, 'label' => (string) $r->label, 'pautan' => (string) $r->pautan, 'status_cukai' => (string) $r->status_cukai,
                    'status_label' => self::STATUS[$r->status_cukai][0] ?? $r->status_cukai, 'baki' => (string) $r->baki, 'bil_tertunggak' => (int) $r->bil_tertunggak,
                ],
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: string, 1: list<mixed>}
     */
    private function source(User $user, array $filters): array
    {
        $today = Carbon::today()->format('Y-m-d');
        $threshold = Carbon::today()->subDays($this->ambang())->format('Y-m-d');
        $bills = $this->akses->cukai($user)->whereNotNull('cukai.obligasi_id')
            ->when(! empty($filters['kod_daerah']), fn ($q) => $q->where('cukai.kod_daerah', (string) $filters['kod_daerah']))
            ->when(! empty($filters['kod_mukim']), fn ($q) => $q->where('cukai.kod_mukim', (string) $filters['kod_mukim']))
            ->when(! empty($filters['jenis_cukai']), fn ($q) => $q->where('cukai.jenis', (string) $filters['jenis_cukai']))
            ->select('cukai.lot_id', 'cukai.status_bayaran', 'cukai.tarikh_akhir', 'cukai.amaun_pokok', 'cukai.amaun_denda', 'cukai.amaun_dibayar');

        $q = DB::query()->fromSub($bills->toBase(), 'c')
            ->join('lot_tanah as l', 'l.id', '=', 'c.lot_id')
            ->join('data_spatial as d', fn ($j) => $j->on('d.lot_id', '=', 'c.lot_id')->where('d.semasa', '=', true))
            ->groupBy('c.lot_id', 'l.no_lot', 'd.geometri')
            ->selectRaw("c.lot_id::text AS id, l.no_lot AS label, '/admin/pro/lot/' || c.lot_id || '?tab=kewangan' AS pautan, d.geometri AS geom,
                CASE WHEN bool_or(c.status_bayaran <> 'dibayar' AND c.tarikh_akhir < ?) THEN 'tertunggak_ambang'
                     WHEN bool_or(c.status_bayaran <> 'dibayar' AND c.tarikh_akhir < ?) THEN 'tertunggak'
                     WHEN bool_or(c.status_bayaran <> 'dibayar') THEN 'belum_dibayar' ELSE 'selesai' END AS status_cukai,
                coalesce(sum(CASE WHEN c.status_bayaran <> 'dibayar' THEN c.amaun_pokok + c.amaun_denda - c.amaun_dibayar ELSE 0 END), 0)::text AS baki,
                count(*) FILTER (WHERE c.status_bayaran <> 'dibayar' AND c.tarikh_akhir < ?) AS bil_tertunggak", [$threshold, $today, $today]);
        $sql = $q->toSql();
        $bindings = $q->getBindings();
        if (! empty($filters['status'])) {
            $sql = 'SELECT * FROM ('.$sql.') x WHERE x.status_cukai = ?';
            $bindings[] = (string) $filters['status'];
        }

        return [$sql, $bindings];
    }

    private function ambang(): int
    {
        return max(1, (int) $this->parameters->get('cki.ambang_tunggakan_hari', 365));
    }
}
