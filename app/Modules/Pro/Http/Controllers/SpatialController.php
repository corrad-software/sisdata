<?php

namespace App\Modules\Pro\Http\Controllers;

use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Pro\Models\DataSpatial;
use App\Modules\Pro\Services\LotQuery;
use App\Modules\Pro\Services\SpatialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** SRS-PRO-07..10: upload / link a lot geometry, history, verification. */
class SpatialController extends ProController
{
    public function __construct(private SpatialService $spatial) {}

    public function index(Request $request, string $id): JsonResponse
    {
        $lot = $this->lot($request, $id);
        $rows = DataSpatial::query()->where('lot_id', $lot->id)->orderByDesc('versi')->get()->map(fn (DataSpatial $s) => $this->out($s));

        return response()->json(['data' => $rows, 'semasa' => $this->spatial->feature($lot)]);
    }

    public function store(Request $request, string $id, DocumentStore $documents): JsonResponse
    {
        $lot = $this->lot($request, $id, false);
        $data = $request->validate([
            'format' => ['required', Rule::in(['geojson', 'wkt', 'kml', 'shp'])],
            'geometri' => ['nullable', 'string', 'max:10000000'],
            'fail' => ['nullable', 'file', 'max:51200'],
            'srid' => ['nullable', 'integer'],
            'sumber_data' => ['required', Rule::in(DataSpatial::SUMBER)],
            'rujukan_fail_sumber' => ['nullable', 'string', 'max:255'],
            'tarikh_perolehan_data' => ['nullable', 'date', 'before_or_equal:today'],
        ], [], (array) __('pro::pro.medan'));

        /** @var UploadedFile|null $file */
        $file = $request->file('fail');
        if ($file === null && empty($data['geometri'])) {
            throw ValidationException::withMessages(['geometri' => [__('pro::pro.spatial.kosong')]]);
        }
        if ($data['format'] === 'shp' && $file === null) {
            throw ValidationException::withMessages(['fail' => [__('pro::pro.spatial.shp_perlu_fail')]]);
        }

        $geometry = $this->spatial->parse($data['format'], $data['geometri'] ?? null, (int) ($data['srid'] ?? 4326), $file);

        // The source file is kept in DMS (category fail_gis), linked to the lot.
        $docId = null;
        $warnings = [];
        if ($file !== null) {
            try {
                $docId = $documents->store($file, 'lot_tanah', $lot->id, 'fail_gis', $this->user($request), 'dalaman', ['tajuk' => 'Geometri '.$lot->no_lot]);
            } catch (DocumentRejected $e) {
                $warnings[] = __('pro::pro.spatial.fail_tidak_disimpan', ['sebab' => $e->getMessage()]);
            }
        }

        $result = $this->spatial->store($lot, $geometry, [
            ...$data,
            'rujukan_fail_sumber' => $data['rujukan_fail_sumber'] ?? $file?->getClientOriginalName(),
            'id_dokumen_sumber' => $docId,
        ], $this->user($request));

        return response()->json([
            'message' => __('pro::pro.spatial.disimpan'),
            'data' => $this->out($result['spatial']),
            'amaran' => [...$warnings, ...$result['amaran']],
            'geojson' => $this->spatial->feature($lot),
        ], 201);
    }

    public function verify(Request $request, int $spatialId): JsonResponse
    {
        $spatial = DataSpatial::query()->find($spatialId);
        if ($spatial === null || app(LotQuery::class)->find($this->user($request), $spatial->lot_id) === null) {
            throw new NotFoundHttpException(__('pro::pro.spatial.tiada'));
        }
        $data = $request->validate([
            'status_pengesahan' => ['required', Rule::in(DataSpatial::STATUS)],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);
        $spatial = $this->spatial->verify($spatial, $data['status_pengesahan'], $this->user($request), $data['catatan'] ?? null);

        return response()->json(['message' => __('pro::pro.spatial.disahkan'), 'data' => $this->out($spatial)]);
    }

    /** @return array<string, mixed> */
    private function out(DataSpatial $s): array
    {
        return [
            'id' => $s->id,
            'versi' => $s->versi,
            'semasa' => $s->semasa,
            'srid_asal' => $s->srid_asal,
            'format_asal' => $s->format_asal,
            'sumber_data' => $s->sumber_data,
            'rujukan_fail_sumber' => $s->rujukan_fail_sumber,
            'id_dokumen_sumber' => $s->id_dokumen_sumber,
            'tarikh_perolehan_data' => $s->tarikh_perolehan_data?->toDateString(),
            'keluasan_dikira' => $s->keluasan_dikira !== null ? (float) $s->keluasan_dikira : null,
            'peratus_beza_keluasan' => $s->peratus_beza_keluasan !== null ? (float) $s->peratus_beza_keluasan : null,
            'status_pengesahan' => $s->status_pengesahan,
            'label_status' => __('pro::pro.status_spatial.'.$s->status_pengesahan),
            'id_pegawai_pengesah' => $s->id_pegawai_pengesah,
            'tarikh_pengesahan' => $s->tarikh_pengesahan?->toIso8601String(),
            'catatan_pertindihan' => $s->catatan_pertindihan,
            'catatan_pengesahan' => $s->catatan_pengesahan,
            'id_pemuat_naik' => $s->id_pemuat_naik,
            'dimuat_naik_pada' => $s->created_at?->toIso8601String(),
            'tarikh_kemas_kini' => $s->updated_at?->toIso8601String(),
        ];
    }
}
