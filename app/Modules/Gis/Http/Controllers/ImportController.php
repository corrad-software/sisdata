<?php

namespace App\Modules\Gis\Http\Controllers;

use App\Modules\Gis\Models\LogImport;
use App\Modules\Gis\Services\ImportSpatial;
use App\Modules\Gis\Services\LotScope;
use App\Modules\Gis\Support\EksportJadual;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Spatial file import with validation report (SRS-GIS-22/27). */
class ImportController extends GisController
{
    public function __construct(private LotScope $scope) {}

    public function index(Request $request): JsonResponse
    {
        $rows = $this->visible($request)->latest()->paginate(min(100, max(5, (int) $request->query('per_halaman', 20))));

        return response()->json($rows->through(fn (LogImport $i) => $this->present($i)));
    }

    public function store(Request $request, ImportSpatial $importer): JsonResponse
    {
        $data = $request->validate([
            'fail' => ['required', 'file'],
            'nama_set_data' => ['required', 'string', 'max:150'],
            'kategori' => ['required', Rule::in(LogImport::KATEGORI)],
            'srid' => ['nullable', 'integer', Rule::in(ImportSpatial::SRID_DISOKONG)],
            'medan_no_lot' => ['nullable', 'string', 'max:60'],
            'kod_mukim' => ['nullable', 'string', 'size:6'],
            'medan_wajib' => ['nullable', 'array', 'max:20'],
            'medan_wajib.*' => ['string', 'max:60'],
            'klasifikasi' => ['nullable', Rule::in(['awam', 'dalaman', 'sulit'])],
        ]);
        $import = $importer->import($request->file('fail'), $data, $this->user($request));

        return response()->json(['data' => $this->present($import)], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $import = $this->find($request, $id);
        $errors = DB::table('gis_ciri_import')->where('id_import', $import->id)->where('sah', false)->orderBy('no_ciri')->limit(1000)
            ->get(['no_ciri', 'ralat', 'atribut'])
            ->map(fn ($r) => ['no_ciri' => (int) $r->no_ciri, 'ralat' => json_decode((string) $r->ralat, true), 'atribut' => json_decode((string) $r->atribut, true)]);

        return response()->json(['data' => $this->present($import), 'ralat_ciri' => $errors]);
    }

    /** Features of the import as GeoJSON (preview before acceptance; invalid ones flagged). */
    public function features(Request $request, string $id): JsonResponse
    {
        $import = $this->find($request, $id);
        $features = array_map(fn ($r) => [
            'type' => 'Feature', 'id' => (int) $r->id,
            'geometry' => json_decode((string) $r->j, true),
            'properties' => ['id' => (string) $r->id, 'label' => '#'.$r->no_ciri, 'no_ciri' => (int) $r->no_ciri, 'sah' => (bool) $r->sah, 'lot_id' => $r->lot_id, 'atribut' => json_decode((string) $r->atribut, true)],
        ], DB::select('SELECT id, no_ciri, sah, lot_id, atribut, ST_AsGeoJSON(ST_MakeValid(geom), 7) AS j FROM gis_ciri_import WHERE id_import = ? AND geom IS NOT NULL ORDER BY no_ciri LIMIT 20000', [$import->id]));

        return response()->json(['type' => 'FeatureCollection', 'features' => $features]);
    }

    public function accept(Request $request, string $id, ImportSpatial $importer): JsonResponse
    {
        $data = $request->validate([
            'sumber_data' => ['nullable', 'string', 'max:255'],
            'pemilik_data' => ['nullable', 'string', 'max:150'],
            'tarikh_kemas_kini' => ['nullable', 'date'],
            'tahap_ketepatan' => ['nullable', 'string', 'max:100'],
            'had_penggunaan' => ['nullable', 'string', 'max:2000'],
        ]);

        return response()->json(['data' => $this->present($importer->accept($this->find($request, $id), $this->user($request), $data))]);
    }

    public function cancel(Request $request, string $id, ImportSpatial $importer): JsonResponse
    {
        return response()->json(['data' => $this->present($importer->cancel($this->find($request, $id)))]);
    }

    /** Error report naming each failing feature (acceptance criterion 3). */
    public function report(Request $request, string $id): StreamedResponse
    {
        $import = $this->find($request, $id);
        $format = (string) $request->query('format', 'csv');
        $rows = DB::table('gis_ciri_import')->where('id_import', $import->id)->orderBy('no_ciri')->get(['no_ciri', 'sah', 'ralat', 'lot_id', 'atribut'])
            ->map(fn ($r) => ['no_ciri' => (int) $r->no_ciri, 'sah' => (bool) $r->sah, 'ralat' => implode('; ', (array) json_decode((string) $r->ralat, true)), 'lot_id' => $r->lot_id, 'atribut' => $r->atribut]);

        return EksportJadual::download($rows, ['no_ciri' => 'No. ciri', 'sah' => 'Sah', 'ralat' => 'Ralat', 'lot_id' => 'Lot dipadankan', 'atribut' => 'Atribut'], $format, 'laporan-import-'.$import->id);
    }

    /** @return Builder<LogImport> */
    private function visible(Request $request)
    {
        $levels = $this->scope->levels($this->user($request));

        return LogImport::query()->whereIn('klasifikasi', $levels);
    }

    private function find(Request $request, string $id): LogImport
    {
        $import = preg_match('/^[0-9a-f-]{36}$/i', $id) ? $this->visible($request)->find($id) : null;
        if ($import === null) {
            throw new NotFoundHttpException(__('gis::gis.import.tidak_wujud'));
        }

        return $import;
    }

    /** @return array<string, mixed> */
    private function present(LogImport $i): array
    {
        return $i->only(['id', 'nama_set_data', 'nama_fail', 'format', 'srid_asal', 'kategori', 'bilangan_ciri', 'bilangan_sah', 'bilangan_ralat', 'status', 'ralat_fail', 'amaran', 'medan', 'medan_no_lot', 'id_dokumen', 'klasifikasi'])
            + ['dicipta_pada' => $i->created_at?->toIso8601String(), 'diterima_pada' => $i->diterima_pada?->toIso8601String()];
    }
}
