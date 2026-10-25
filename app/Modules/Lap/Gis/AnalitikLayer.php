<?php

namespace App\Modules\Lap\Gis;

use App\Models\User;
use App\Modules\Gis\Contracts\MapLayer;
use App\Modules\Lap\Models\HasilAnalitik;
use App\Modules\Lap\Services\Skop;
use App\Modules\Lap\Support\SetData;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Support\Facades\DB;

/**
 * MOD-LAP analytic results on the MOD-GIS map (SRS-LAP-18): development
 * potential, revenue potential, idle land and arrears risk, coloured by
 * level. Filtered by the viewer's daerah / mukim scope and lot
 * classification levels inside the query (SRS-RBC-08).
 */
class AnalitikLayer implements MapLayer
{
    public const WARNA = ['rendah' => '#16a34a', 'sederhana' => '#eab308', 'tinggi' => '#ea580c', 'kritikal' => '#dc2626'];

    public function __construct(private AccessControl $access, private Skop $skop) {}

    public function key(): string
    {
        return 'lap.analitik';
    }

    public function label(): string
    {
        return __('lap::lap.gis.label');
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
        return 'lap.analitik.lihat';
    }

    public function legend(): array
    {
        return ['medan' => 'aras', 'entri' => array_map(fn ($a) => ['nilai' => $a, 'label' => __('lap::lap.aras.'.$a), 'warna' => self::WARNA[$a]], HasilAnalitik::ARAS)];
    }

    public function features(User $user, ?array $bbox = null, array $filters = [], int $limit = 5000): array
    {
        if (! $this->access->allows($user, $this->permission())) {
            return ['type' => 'FeatureCollection', 'features' => []];
        }
        $jenis = in_array($filters['jenis'] ?? null, HasilAnalitik::JENIS, true) ? $filters['jenis'] : 'potensi_pembangunan';
        $sql = ['h.semasa = true', 'h.jenis_analitik = ?', 'l.koordinat_pusat IS NOT NULL'];
        $b = [$jenis];
        foreach (['daerah' => 'h.kod_daerah', 'mukim' => 'h.kod_mukim'] as $type => $col) {
            $scope = $this->access->scope($user, $type);
            if ($scope !== null) {
                $sql[] = $scope === [] ? 'FALSE' : $col.' IN ('.implode(',', array_fill(0, count($scope), '?')).')';
                array_push($b, ...$scope);
            }
        }
        $levels = $this->skop->levels($user, SetData::get('lot')['tahap']);
        $sql[] = 'h.klasifikasi IN ('.implode(',', array_fill(0, max(1, count($levels)), '?')).')';
        array_push($b, ...($levels ?: ['__tiada__']));
        foreach (['kod_daerah' => 'h.kod_daerah', 'kod_mukim' => 'h.kod_mukim'] as $k => $col) {
            if (! empty($filters[$k]) && is_string($filters[$k])) {
                $sql[] = "{$col} = ?";
                $b[] = $filters[$k];
            }
        }
        if ($bbox !== null) {
            $sql[] = 'l.koordinat_pusat && ST_MakeEnvelope(?, ?, ?, ?, 4326)';
            array_push($b, ...array_map('floatval', $bbox));
        }
        $rows = DB::select('SELECT h.id, h.id_lot, h.no_lot, h.skor, h.aras_risiko, h.jenis_analitik, ST_AsGeoJSON(l.koordinat_pusat, 7) AS g
            FROM lap_hasil_analitik h JOIN lot_tanah l ON l.id = h.id_lot WHERE '.implode(' AND ', $sql).' ORDER BY h.skor DESC LIMIT '.max(1, min($limit, 20000)), $b);
        $features = [];
        foreach ($rows as $r) {
            $features[] = ['type' => 'Feature', 'id' => $r->id, 'geometry' => json_decode((string) $r->g, true), 'properties' => [
                'id' => $r->id, 'label' => $r->no_lot.' — '.__('lap::lap.analitik.'.$r->jenis_analitik), 'pautan' => '/admin/pro/lot/'.$r->id_lot,
                'aras' => $r->aras_risiko, 'skor' => (float) $r->skor, 'warna' => self::WARNA[$r->aras_risiko] ?? '#71717a', 'catatan' => __('lap::lap.label_cadangan'),
            ]];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }
}
