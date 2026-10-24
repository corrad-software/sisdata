<?php

namespace App\Modules\Pjk\Integration;

use App\Models\User;
use App\Modules\Gis\Contracts\MapLayer;
use App\Modules\Pjk\Support\Akses;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * MOD-GIS layer "Pajakan & sewaan": lot polygons of every lot with a rental
 * premise, coloured by lease status (SRS §Modul 5 integrasi GIS — status
 * penghunian). Access: pjk.kontrak.lihat + the user's daerah/mukim scope
 * (via Akses on premis_aset) — GIS can't bypass it (SRS-RBC-08).
 */
class PajakanMapLayer implements MapLayer
{
    public const STATUS = [
        'tunggakan' => ['Disewa — ada tunggakan', '#dc2626'],
        'hampir_tamat' => ['Disewa — hampir tamat', '#d97706'],
        'aktif' => ['Disewa', '#16a34a'],
        'kosong' => ['Kosong / tersedia', '#2563eb'],
        'tidak_tersedia' => ['Tidak tersedia', '#71717a'],
    ];

    public function __construct(private Akses $akses, private AccessControl $access) {}

    public function key(): string
    {
        return 'pjk.pajakan';
    }

    public function label(): string
    {
        return __('pjk::pjk.peta.label');
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
        return 'pjk.kontrak.lihat';
    }

    public function legend(): array
    {
        return ['medan' => 'status_pajakan', 'entri' => array_map(fn ($k, $v) => ['nilai' => $k, 'label' => $v[0], 'warna' => $v[1]], array_keys(self::STATUS), self::STATUS)];
    }

    public function features(User $user, ?array $bbox = null, array $filters = [], int $limit = 5000): array
    {
        if (! $this->access->allows($user, $this->permission())) {
            return ['type' => 'FeatureCollection', 'features' => []];
        }
        $today = Carbon::today()->format('Y-m-d');
        $premis = $this->akses->premis($user)
            ->when(! empty($filters['kod_daerah']), fn ($q) => $q->where('kod_daerah', $filters['kod_daerah']))
            ->when(! empty($filters['kod_mukim']), fn ($q) => $q->where('kod_mukim', $filters['kod_mukim']))
            ->select('premis_aset.id', 'premis_aset.lot_id', 'premis_aset.status_premis', 'premis_aset.nama_premis', 'premis_aset.kod_daerah');

        $q = DB::query()->fromSub($premis->toBase(), 'p')
            ->join('data_spatial as d', fn ($j) => $j->on('d.lot_id', '=', 'p.lot_id')->where('d.semasa', '=', true))
            ->join('lot_tanah as l', 'l.id', '=', 'p.lot_id')
            ->leftJoin('kontrak_pajakan as k', fn ($j) => $j->on('k.premis_id', '=', 'p.id')->whereNull('k.deleted_at')->whereIn('k.status', ['aktif', 'hampir_tamat']))
            ->groupBy('p.lot_id', 'l.no_lot', 'd.id')
            ->selectRaw('p.lot_id, l.no_lot, ST_AsGeoJSON(d.geometri, 6) as geom, count(distinct p.id) as bil_premis,
                count(distinct p.id) filter (where p.status_premis = \'kosong\') as bil_kosong,
                count(distinct k.id) as bil_kontrak,
                bool_or(k.status = \'hampir_tamat\') as hampir_tamat,
                bool_or(exists (select 1 from jadual_caj c where c.kontrak_id = k.id and c.dikeluarkan and c.status_caj in (\'belum_bayar\', \'separa\') and c.tarikh_akhir < ?)) as tunggakan', [$today])
            ->limit(max(1, min($limit, 5000)));
        if ($bbox !== null) {
            $q->whereRaw('ST_Intersects(d.geometri, ST_MakeEnvelope(?, ?, ?, ?, 4326))', array_map('floatval', $bbox));
        }

        $features = [];
        foreach ($q->get() as $r) {
            $status = match (true) {
                (bool) $r->tunggakan => 'tunggakan',
                (bool) $r->hampir_tamat => 'hampir_tamat',
                (int) $r->bil_kontrak > 0 => 'aktif',
                (int) $r->bil_kosong > 0 => 'kosong',
                default => 'tidak_tersedia',
            };
            if (! empty($filters['status']) && $filters['status'] !== $status) {
                continue;
            }
            $features[] = [
                'type' => 'Feature',
                'geometry' => json_decode((string) $r->geom, true),
                'properties' => [
                    'id' => (string) $r->lot_id,
                    'label' => (string) $r->no_lot,
                    'pautan' => '/admin/pro/lot/'.$r->lot_id.'?tab=pemanfaatan',
                    'status_pajakan' => $status,
                    'status_label' => self::STATUS[$status][0],
                    'bil_premis' => (int) $r->bil_premis,
                    'bil_kontrak_aktif' => (int) $r->bil_kontrak,
                ],
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }
}
