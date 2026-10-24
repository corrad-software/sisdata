<?php

namespace App\Modules\Gis\Http\Controllers;

use App\Modules\Gis\Models\RalatKualiti;
use App\Modules\Gis\Services\KualitiSpatial;
use App\Modules\Gis\Services\LotScope;
use App\Modules\Gis\Support\EksportJadual;
use App\Modules\Pro\Contracts\LotRegistry;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Spatial QA findings (SRS-GIS-27/28) — the GIS Specialist's correction list.
 * Each finding links to the lot's profile in MOD-PRO, where the geometry is
 * corrected (MOD-GIS never writes lot geometry).
 */
class KualitiController extends GisController
{
    public function __construct(private AccessControl $access, private LotRegistry $lots) {}

    /** @return Builder<RalatKualiti> */
    private function scoped(Request $request): Builder
    {
        $user = $this->user($request);
        $query = RalatKualiti::query();
        foreach (['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim'] as $type => $column) {
            $allowed = $this->access->scope($user, $type);
            if ($allowed !== null) {
                $query->whereIn($column, $allowed);
            }
        }
        // Lot-level findings only for lots the user may see (classification included).
        [$where, $bindings] = app(LotScope::class)->where($user, 'l');
        $query->where(fn ($q) => $q->whereNull('id_lot')->orWhereRaw("EXISTS (SELECT 1 FROM lot_tanah l WHERE l.id = gis_ralat_kualiti.id_lot AND {$where})", $bindings));

        return $query;
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'jenis_ralat' => ['nullable', Rule::in(RalatKualiti::JENIS)],
            'status' => ['nullable', Rule::in([...RalatKualiti::STATUS, 'terbuka'])],
            'kod_daerah' => ['nullable', 'string', 'max:4'],
        ]);
        $query = $this->scoped($request);
        if (! empty($data['jenis_ralat'])) {
            $query->where('jenis_ralat', $data['jenis_ralat']);
        }
        $status = $data['status'] ?? 'terbuka';
        $status === 'terbuka' ? $query->whereIn('status_pembetulan', ['baharu', 'dalam_tindakan']) : $query->where('status_pembetulan', $status);
        if (! empty($data['kod_daerah'])) {
            $query->where('kod_daerah', $data['kod_daerah']);
        }

        $summary = $this->scoped($request)->whereIn('status_pembetulan', ['baharu', 'dalam_tindakan'])
            ->selectRaw('jenis_ralat, count(*) AS n')->groupBy('jenis_ralat')->pluck('n', 'jenis_ralat');
        $page = $query->orderByDesc('dikesan_pada')->orderBy('id')->paginate(min(100, max(5, (int) $request->query('per_halaman', 25))));

        return response()->json([
            ...$page->through(fn (RalatKualiti $r) => $this->present($r))->toArray(),
            'ringkasan' => $summary,
        ]);
    }

    public function run(Request $request, KualitiSpatial $qa): JsonResponse
    {
        $data = $request->validate(['semakan' => ['nullable', 'array'], 'semakan.*' => [Rule::in(KualitiSpatial::CHECKS)]]);

        return response()->json($qa->run($this->user($request), $data['semakan'] ?? null));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status_pembetulan' => ['required', Rule::in(RalatKualiti::STATUS)],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);
        $finding = $this->scoped($request)->find($id) ?? throw new NotFoundHttpException(__('gis::gis.kualiti.tidak_wujud'));
        $finding->update([
            'status_pembetulan' => $data['status_pembetulan'],
            'catatan' => $data['catatan'] ?? $finding->catatan,
            'diselesaikan_pada' => in_array($data['status_pembetulan'], ['selesai', 'diabaikan'], true) ? now() : null,
            'dikemas_kini_oleh' => $this->user($request)->id,
        ]);

        return response()->json(['data' => $this->present($finding)]);
    }

    /** Geometry of the finding + an ST_MakeValid suggestion for invalid geometry. */
    public function suggestion(Request $request, int $id): JsonResponse
    {
        $finding = $this->scoped($request)->find($id) ?? throw new NotFoundHttpException(__('gis::gis.kualiti.tidak_wujud'));
        $row = DB::selectOne(
            'SELECT ST_AsGeoJSON(geom, 7) AS asal, CASE WHEN geom IS NOT NULL AND NOT ST_IsValid(geom) THEN ST_AsGeoJSON(ST_MakeValid(geom), 7) END AS cadangan,
                    CASE WHEN geom IS NOT NULL AND NOT ST_IsValid(geom) THEN ST_IsValidReason(geom) END AS sebab
             FROM gis_ralat_kualiti WHERE id = ?',
            [$finding->id],
        );

        return response()->json([
            'asal' => $row?->asal ? json_decode((string) $row->asal, true) : null,
            'cadangan' => $row?->cadangan ? json_decode((string) $row->cadangan, true) : null,
            'sebab' => $row?->sebab,
            'pautan_pembetulan' => $finding->id_lot ? $this->lots->profileLink($finding->id_lot) : null,
        ]);
    }

    /** Findings as GeoJSON for the map overlay. */
    public function features(Request $request): JsonResponse
    {
        $ids = $this->scoped($request)->whereIn('status_pembetulan', ['baharu', 'dalam_tindakan'])->limit(5000)->pluck('id')->all();
        $features = $ids === [] ? [] : array_map(fn ($r) => [
            'type' => 'Feature', 'id' => (int) $r->id, 'geometry' => json_decode((string) $r->j, true),
            'properties' => ['id' => (string) $r->id, 'label' => $r->perihal, 'jenis_ralat' => $r->jenis_ralat, 'pautan' => $r->id_lot ? $this->lots->profileLink((string) $r->id_lot) : null],
        ], DB::select('SELECT id, id_lot, jenis_ralat, perihal, ST_AsGeoJSON(geom, 7) AS j FROM gis_ralat_kualiti WHERE geom IS NOT NULL AND id IN ('.implode(',', array_map('intval', $ids)).')'));

        return response()->json(['type' => 'FeatureCollection', 'features' => $features]);
    }

    /** SRS-GIS-28 error report for correction (CSV / Excel). */
    public function export(Request $request): StreamedResponse
    {
        $format = (string) $request->query('format', 'csv');
        $rows = $this->scoped($request)->whereIn('status_pembetulan', ['baharu', 'dalam_tindakan'])->orderBy('jenis_ralat')->limit(50000)->get()
            ->map(fn (RalatKualiti $r) => $this->present($r));

        return EksportJadual::download($rows, [
            'id' => 'ID', 'jenis_ralat' => 'Jenis ralat', 'perihal' => 'Perihal', 'kod_daerah' => 'Daerah', 'kod_mukim' => 'Mukim', 'nilai' => 'Nilai (m² / m)',
            'cadangan' => 'Cadangan pembetulan', 'status_pembetulan' => 'Status', 'pautan' => 'Pautan lot', 'dikesan_pada' => 'Dikesan pada',
        ], $format, 'laporan-kualiti-spatial');
    }

    /** @return array<string, mixed> */
    private function present(RalatKualiti $r): array
    {
        return $r->only(['id', 'id_lot', 'id_lot_lain', 'kod_daerah', 'kod_mukim', 'jenis_ralat', 'perihal', 'cadangan', 'status_pembetulan', 'catatan']) + [
            'nilai' => $r->nilai !== null ? round((float) $r->nilai, 2) : null,
            'pautan' => $r->id_lot ? $this->lots->profileLink($r->id_lot) : null,
            'pautan_lain' => $r->id_lot_lain ? $this->lots->profileLink($r->id_lot_lain) : null,
            'dikesan_pada' => $r->dikesan_pada->toIso8601String(),
            'diselesaikan_pada' => $r->diselesaikan_pada?->toIso8601String(),
        ];
    }
}
