<?php

namespace App\Modules\Ref\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ref\Models\Sempadan;
use App\Modules\Ref\Models\TempatIbadat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GeoJSON endpoints (SRID 4326). Map data © OpenStreetMap contributors (ODbL);
 * mukim polygons are approximations (properties.anggaran = true).
 */
class SpatialController extends Controller
{
    private const ATTRIBUTION = '© OpenStreetMap contributors (ODbL 1.0) — https://www.openstreetmap.org/copyright';

    /** GET /ref/{jenis}/{kod}/sempadan */
    public function show(string $jenis, string $kod): JsonResponse
    {
        $row = Sempadan::query()->where('jenis', $jenis)->where('kod', $kod)->first();
        abort_if(! $row, 404, __('ref::ref.sempadan_tiada', ['jenis' => $jenis, 'kod' => $kod]));

        return response()->json($row->toFeature(), 200, ['Content-Type' => 'application/geo+json']);
    }

    /** GET /ref/sempadan/{jenis}?daerah= — FeatureCollection */
    public function index(Request $request, string $jenis): JsonResponse
    {
        $request->validate(['daerah' => ['nullable', 'regex:/^\d{4}$/']]);

        $rows = Sempadan::query()
            ->where('jenis', $jenis)
            ->when($jenis === 'mukim' && $request->filled('daerah'), fn ($q) => $q->where('kod', 'like', $request->query('daerah').'%'))
            ->orderBy('kod')
            ->get();

        return response()->json([
            'type' => 'FeatureCollection',
            'attribution' => self::ATTRIBUTION,
            'features' => $rows->map(fn (Sempadan $s) => $s->toFeature(5))->values(),
        ], 200, ['Content-Type' => 'application/geo+json']);
    }

    /** GET /ref/tempat-ibadat?daerah=&mukim=&jenis= — FeatureCollection of masjid/surau */
    public function placesOfWorship(Request $request): JsonResponse
    {
        $request->validate([
            'daerah' => ['nullable', 'regex:/^\d{4}$/'],
            'mukim' => ['nullable', 'regex:/^\d{6}$/'],
            'jenis' => ['nullable', 'in:masjid,surau,lain'],
        ]);

        $rows = TempatIbadat::query()
            ->select(['osm_jenis', 'osm_id', 'nama', 'jenis', 'daerah_kod', 'mukim_kod'])
            ->addSelect(DB::raw('ST_X(geometri) AS lon, ST_Y(geometri) AS lat'))
            ->when($request->filled('daerah'), fn ($q) => $q->where('daerah_kod', $request->query('daerah')))
            ->when($request->filled('mukim'), fn ($q) => $q->where('mukim_kod', $request->query('mukim')))
            ->when($request->filled('jenis'), fn ($q) => $q->where('jenis', $request->query('jenis')))
            ->orderBy('osm_jenis')->orderBy('osm_id')
            ->get();

        return response()->json([
            'type' => 'FeatureCollection',
            'attribution' => self::ATTRIBUTION,
            'features' => $rows->map(fn (TempatIbadat $t) => [
                'type' => 'Feature',
                'properties' => [
                    'osm' => "{$t->osm_jenis}/{$t->osm_id}",
                    'nama' => $t->nama,
                    'jenis' => $t->jenis,
                    'daerah_kod' => $t->daerah_kod,
                    'mukim_kod' => $t->mukim_kod,
                ],
                'geometry' => ['type' => 'Point', 'coordinates' => [(float) $t->getAttribute('lon'), (float) $t->getAttribute('lat')]],
            ])->values(),
        ], 200, ['Content-Type' => 'application/geo+json']);
    }
}
