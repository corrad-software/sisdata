<?php

namespace App\Modules\Gis\Http\Controllers;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Gis\Contracts\Geocoder;
use App\Modules\Gis\Contracts\MapLayer;
use App\Modules\Gis\Contracts\VectorTileLayer;
use App\Modules\Gis\Services\EksportSpatial;
use App\Modules\Gis\Services\LayerService;
use App\Modules\Gis\Services\Statistik;
use App\Modules\Gis\Services\TileService;
use App\Modules\Gis\Services\VersiGeometri;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Map workspace API: layers, features (GeoJSON / MVT), search, basemaps, statistics, versions, data export. */
class PetaController extends GisController
{
    public function __construct(private LayerService $layers) {}

    /** SRS-GIS-08/11/12: layers the user may use, grouped, with legend + mandatory-layer slots. */
    public function index(Request $request, Parameters $parameters): JsonResponse
    {
        $user = $this->user($request);

        return response()->json([
            'lapisan' => array_map(fn (MapLayer $l) => $this->layers->describe($l), $this->layers->visible($user)),
            'wajib' => $this->layers->mandatory($user),
            'kumpulan' => [
                ['kod' => 'asas', 'nama' => __('gis::gis.kumpulan.asas')],
                ['kod' => 'hartanah', 'nama' => __('gis::gis.kumpulan.hartanah')],
                ['kod' => 'operasi', 'nama' => __('gis::gis.kumpulan.operasi')],
                ['kod' => 'risiko', 'nama' => __('gis::gis.kumpulan.risiko')],
                ['kod' => 'rujukan', 'nama' => __('gis::gis.kumpulan.rujukan')],
            ],
            'peta_asas' => $this->basemaps($parameters),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function basemaps(Parameters $parameters): array
    {
        $satellite = (string) $parameters->get('gis.peta_asas_satelit_url', '');

        return [
            ['kod' => 'osm', 'nama' => 'OpenStreetMap', 'jenis' => 'raster', 'url' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', 'atribusi' => '© Penyumbang OpenStreetMap', 'aktif' => true, 'perlu_lesen' => false],
            ['kod' => 'satelit', 'nama' => __('gis::gis.peta_asas.satelit'), 'jenis' => 'raster', 'url' => $satellite !== '' ? $satellite : null,
                'atribusi' => (string) $parameters->get('gis.peta_asas_satelit_atribusi', ''), 'aktif' => $satellite !== '', 'perlu_lesen' => true,
                'catatan' => __('gis::gis.peta_asas.perlu_lesen')],
            ['kod' => 'kosong', 'nama' => __('gis::gis.peta_asas.kosong'), 'jenis' => 'kosong', 'url' => null, 'atribusi' => '', 'aktif' => true, 'perlu_lesen' => false],
        ];
    }

    /** GeoJSON features in a bbox, simplified by zoom (SRS-GIS-08). */
    public function features(Request $request, string $key, Parameters $parameters): JsonResponse
    {
        $user = $this->user($request);
        $layer = $this->layers->authorize($user, $key, 'geojson');
        $request->validate(['zoom' => ['nullable', 'numeric', 'between:0,24']]);
        $filters = $this->filters($request);
        if ($request->filled('zoom')) {
            $filters['_zoom'] = (float) $request->query('zoom');
        }
        $limit = (int) $parameters->get('gis.had_ciri', 5000);

        return response()->json($layer->features($user, $this->bbox($request), $filters, $limit));
    }

    /** Mapbox Vector Tile (SRS-GIS-08, RBC-08: authorised per request with the same scope). */
    public function tile(Request $request, string $key, int $z, int $x, int $y, TileService $tiles): Response
    {
        $user = $this->user($request);
        $layer = $this->layers->authorize($user, $key, 'mvt');
        if (! $layer instanceof VectorTileLayer) {
            throw new NotFoundHttpException(__('gis::gis.tile.tidak_disokong'));
        }
        $body = $tiles->tile($user, $layer, $z, $x, $y, $this->filters($request));

        return new Response($body, $body === '' ? 204 : 200, [
            'Content-Type' => 'application/vnd.mapbox-vector-tile',
            'Cache-Control' => 'private, max-age=60',
            'Vary' => 'Cookie, Authorization',
        ]);
    }

    /** SRS-GIS-08 search by location (lot no., mukim/daerah/PBT, mosque, address via adapter). */
    public function search(Request $request, Geocoder $geocoder): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:150']]);

        return response()->json(['data' => $geocoder->search($data['q'], $this->user($request), 10)]);
    }

    /** SRS-GIS-34: distribution by daerah / mukim / category. */
    public function statistics(Request $request, Statistik $statistik): JsonResponse
    {
        $data = $request->validate([
            'peringkat' => ['nullable', Rule::in(['daerah', 'mukim'])],
            'kod_daerah' => ['nullable', 'string', 'max:4'],
            'bahagian_pemilik' => ['nullable', 'string', 'max:30'],
            'kategori_guna_tanah' => ['nullable', 'string', 'max:30'],
            'geometri' => ['nullable', 'boolean'],
        ]);
        abort_unless(app(AccessControl::class)->allows($this->user($request), 'pro.lot.lihat'), 403, __('gis::gis.lapisan.ditolak'));

        return response()->json($statistik->distribution($this->user($request), $data['peringkat'] ?? 'daerah', $data, (bool) ($data['geometri'] ?? false)));
    }

    /** SRS-GIS-30: geometry versions of a lot. */
    public function versions(Request $request, string $id, VersiGeometri $versions): JsonResponse
    {
        $this->layers->authorize($this->user($request), 'gis.lot', 'versi');

        return response()->json(['data' => $versions->versions($this->user($request), $id)]);
    }

    public function compare(Request $request, string $id, VersiGeometri $versions): JsonResponse
    {
        $data = $request->validate(['a' => ['required', 'integer'], 'b' => ['required', 'integer']]);
        $this->layers->authorize($this->user($request), 'gis.lot', 'versi');

        return response()->json($versions->compare($this->user($request), $id, (int) $data['a'], (int) $data['b']));
    }

    /** SRS-GIS-26: export a layer (open formats, optional reprojection), audited. */
    public function export(Request $request, string $key, EksportSpatial $export): BinaryFileResponse
    {
        $data = $request->validate([
            'format' => ['required', Rule::in(array_keys(EksportSpatial::FORMATS))],
            'srid' => ['nullable', Rule::in(EksportSpatial::SRID)],
        ]);
        $user = $this->user($request);
        $layer = $this->layers->authorize($user, $key, 'eksport');

        return $export->export($user, $layer, $data['format'], (int) ($data['srid'] ?? 4326), $this->filters($request), $this->bbox($request));
    }
}
