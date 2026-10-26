<?php

namespace App\Modules\Pro\Http\Controllers;

use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Aud\Contracts\AuditScope;
use App\Modules\Pro\Models\DataSpatial;
use App\Modules\Pro\Models\LotTanah;
use App\Modules\Pro\Models\VersiRekodInduk;
use App\Modules\Pro\Services\Klasifikasi;
use App\Modules\Pro\Services\LotPresenter;
use App\Modules\Pro\Services\LotQuery;
use App\Modules\Pro\Services\LotService;
use App\Modules\Pro\Services\Pengeksport;
use App\Modules\Pro\Services\PenghalaKualiti;
use App\Modules\Pro\Services\PengurusArkib;
use App\Modules\Pro\Services\PeraturanKualiti;
use App\Modules\Pro\Services\Profil360;
use App\Modules\Pro\Services\RujukanInduk;
use App\Modules\Pro\Services\SpatialService;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class LotController extends ProController
{
    public function __construct(
        private LotQuery $query,
        private LotPresenter $presenter,
        private LotService $lots,
        private AuditLogger $audit,
    ) {}

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'kod_daerah' => ['nullable'],
            'kod_mukim' => ['nullable'],
            'bahagian_pemilik' => ['nullable'],
            'status_lot' => ['nullable'],
            'kategori_guna_tanah' => ['nullable'],
            'status_pengesahan' => ['nullable', 'string', 'max:30'],
            'klasifikasi' => ['nullable'],
            'id_data_steward' => ['nullable'],
            'tag' => ['nullable', 'string', 'max:50'],
            'arkib' => ['nullable', 'string', 'in:0,1,true,false,sahaja'],
            'sintetik' => ['nullable', 'string', 'in:0,1,true,false'],
            'sort' => ['nullable', 'string', 'in:no_lot,-no_lot,keluasan,-keluasan,dikemas_kini,-dikemas_kini'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);
    }

    /** SRS-PRO-10: list with verification flags; SRS-PRO-29 lots API. */
    public function index(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $sort = (string) ($filters['sort'] ?? 'no_lot');
        $column = ['no_lot' => 'no_lot', 'keluasan' => 'keluasan', 'dikemas_kini' => 'updated_at'][ltrim($sort, '-')];

        $page = $this->query->for($this->user($request), $filters)->with('spatialSemasa')
            ->orderBy('lot_tanah.'.$column, str_starts_with($sort, '-') ? 'desc' : 'asc')->orderBy('lot_tanah.id')
            ->paginate((int) ($filters['per_page'] ?? 25));

        return response()->json([
            'data' => collect($page->items())->map(fn (LotTanah $l) => $this->presenter->summary($l, $this->user($request)))->values(),
            'meta' => $this->meta($page),
        ]);
    }

    /** Small counts for the list header (verification flags, SRS-PRO-10). */
    public function summary(Request $request): JsonResponse
    {
        $base = $this->query->for($this->user($request), $this->filters($request))->toBase();
        $ids = (clone $base)->select('lot_tanah.id');
        $spatial = DB::table('data_spatial')->where('semasa', true)->whereIn('lot_id', $ids)
            ->groupBy('status_pengesahan')->selectRaw('status_pengesahan, count(*) AS n')->pluck('n', 'status_pengesahan')->map(fn ($n) => (int) $n);
        $total = (clone $base)->count();

        return response()->json(['data' => [
            'jumlah' => $total,
            'tiada_geometri' => $total - (int) $spatial->sum(),
            'status_pengesahan' => $spatial,
            'bahagian' => (clone $base)->reorder()->groupBy('lot_tanah.bahagian_pemilik')->selectRaw('lot_tanah.bahagian_pemilik AS b, count(*) AS n')->pluck('n', 'b')->map(fn ($n) => (int) $n),
        ]]);
    }

    /** SRS-PRO-18: export with field scope by role and classification. */
    public function export(Request $request, Pengeksport $exporter, Klasifikasi $klasifikasi, AccessControl $access): Response
    {
        $filters = $this->filters($request);
        $format = (string) $request->validate(['format' => ['required', Rule::in(Pengeksport::FORMATS)]])['format'];
        $user = $this->user($request);

        $columns = [
            'no_lot' => __('pro::pro.medan.no_lot'), 'nama_mukim' => __('pro::pro.medan.kod_mukim'), 'kod_mukim' => __('pro::pro.medan.kod_mukim_kod'),
            'nama_daerah' => __('pro::pro.medan.kod_daerah'), 'keluasan' => __('pro::pro.medan.keluasan'), 'unit_keluasan' => __('pro::pro.medan.unit_keluasan'),
            'kategori_guna_tanah' => __('pro::pro.medan.kategori_guna_tanah'), 'zoning' => __('pro::pro.medan.zoning'), 'syarat_nyata' => __('pro::pro.medan.syarat_nyata'),
            'bahagian_pemilik' => __('pro::pro.medan.bahagian_pemilik'), 'status_lot' => __('pro::pro.medan.status_lot'), 'klasifikasi' => __('pro::pro.medan.klasifikasi'),
            'status_pengesahan' => __('pro::pro.medan.status_pengesahan'), 'versi_rekod' => __('pro::pro.medan.versi_rekod'),
        ];
        // Lineage columns only for data-governance roles.
        if ($access->allows($user, 'pro.jejak.lihat')) {
            $columns += ['sumber_sistem' => __('pro::pro.medan.sumber_sistem'), 'kunci_asal' => __('pro::pro.medan.kunci_asal')];
        }
        $columns = array_map('strval', array_diff_key($columns, array_flip($klasifikasi->maskedFields('lot_tanah', $user))));

        $lots = $this->query->for($user, $filters)->with('spatialSemasa')->orderBy('no_lot')->limit(20000)->get();
        $rows = $lots->map(function (LotTanah $l) use ($user) {
            $row = $this->presenter->detail($l, $user);
            $row['status_pengesahan'] = $row['spatial']['label'];
            $row['sumber_sistem'] = $l->sumber_sistem;
            $row['kunci_asal'] = $l->kunci_asal;

            return $row;
        })->all();

        $this->audit->record('eksport', 'pro', 'lot_tanah', null, meta: ['format' => $format, 'bilangan' => count($rows), 'penapis' => $filters, 'lajur' => array_keys($columns), 'klasifikasi' => 'dalaman']);

        return $exporter->download($rows, $columns, $format, 'senarai-lot', (string) __('pro::pro.eksport.tajuk_lot'));
    }

    public function store(Request $request): JsonResponse
    {
        $lot = $this->lots->create($this->input($request), $this->user($request));

        return response()->json(['message' => __('pro::pro.lot.dicipta'), 'data' => $this->presenter->detail($lot, $this->user($request))], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $lot = $this->lot($request, $id);
        if ($request->user()?->currentAccessToken() instanceof PersonalAccessToken) {
            app(Profil360::class)->logAccess($lot, $this->user($request), 'api', 'api', ['sistem' => $request->attributes->get('pro_api_sistem')]);
        }

        return response()->json(['data' => $this->presenter->detail($lot, $this->user($request))]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $lot = $this->lot($request, $id, false);
        $extra = $request->validate([
            'justifikasi' => ['nullable', 'string', 'max:1000'],
            'versi_rekod' => ['nullable', 'integer'],
        ]);
        $result = $this->lots->update($lot, $this->input($request, true), $this->user($request), $extra['justifikasi'] ?? null, isset($extra['versi_rekod']) ? (int) $extra['versi_rekod'] : null);

        return response()->json([
            'message' => $result['pending'] !== null ? __('pro::pro.lot.kritikal_dihantar') : __('pro::pro.lot.dikemas_kini'),
            'data' => $this->presenter->detail($result['lot'], $this->user($request)),
            'diterapkan' => $result['applied'],
            'tertunda' => $result['pending'] !== null ? [
                'id' => $result['pending']->id,
                'medan' => array_keys((array) $result['pending']->nilai_selepas),
                'id_aliran_kerja' => $result['pending']->id_aliran_kerja,
            ] : null,
        ]);
    }

    /** Only draft lots may be deleted; anything else is archived (SRS-PRO-23). */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $lot = $this->lot($request, $id, false);
        $data = $request->validate(['sebab' => ['required', 'string', 'max:500']]);
        if ($lot->status_lot !== 'draf') {
            throw ValidationException::withMessages(['status_lot' => [__('pro::pro.lot.padam_draf_sahaja')]]);
        }
        AuditScope::withReason($data['sebab'], fn () => $lot->delete());

        return response()->json(['message' => __('pro::pro.lot.dipadam')]);
    }

    /** SRS-PRO-26/27/28/30: the 360° profile; every view is logged. */
    public function profile(Request $request, string $id, Profil360 $profile): JsonResponse
    {
        $lot = $this->lot($request, $id);
        $profile->logAccess($lot, $this->user($request), 'lihat', $request->bearerToken() ? 'api' : 'web');

        return response()->json(['data' => $profile->build($lot, $this->user($request))]);
    }

    /** SRS-PRO-30: the frontend calls this right before window.print(). */
    public function printed(Request $request, string $id, Profil360 $profile): JsonResponse
    {
        $lot = $this->lot($request, $id);
        $profile->logAccess($lot, $this->user($request), 'cetak');

        return response()->json(['message' => __('pro::pro.lot.cetak_direkod')]);
    }

    public function exportProfile(Request $request, string $id, Profil360 $profile, Pengeksport $exporter): Response
    {
        $lot = $this->lot($request, $id);
        $format = (string) $request->validate(['format' => ['required', Rule::in(['pdf', 'json', 'csv', 'xlsx'])]])['format'];
        $data = $profile->build($lot, $this->user($request));
        $profile->logAccess($lot, $this->user($request), 'eksport', 'web', ['format' => $format]);

        if ($format === 'json') {
            return response()->json($data, 200, ['Content-Disposition' => 'attachment; filename="profil-'.$lot->id.'.json"']);
        }

        $rows = [];
        foreach ($data['tab'] as $tab) {
            foreach ($tab['seksyen'] as $section) {
                if ($section['status'] === 'belum_tersedia') {
                    $rows[] = ['tab' => $tab['label'], 'seksyen' => $section['label'], 'perkara' => '', 'nilai' => $section['mesej'] ?? ''];

                    continue;
                }
                foreach ($section['items'] as $item) {
                    $rows[] = ['tab' => $tab['label'], 'seksyen' => $section['label'], 'perkara' => (string) ($item['label'] ?? ''), 'nilai' => is_scalar($item['nilai'] ?? null) ? (string) $item['nilai'] : ''];
                }
            }
        }

        return $exporter->download($rows, ['tab' => 'Tab', 'seksyen' => 'Seksyen', 'perkara' => 'Perkara', 'nilai' => 'Nilai'], $format, 'profil-'.str_replace(' ', '', $lot->no_lot), (string) __('pro::pro.eksport.tajuk_profil', ['no_lot' => $lot->no_lot]));
    }

    /** SRS-DAT-04: version history. */
    public function versions(Request $request, string $id): JsonResponse
    {
        $lot = $this->lot($request, $id);
        $rows = VersiRekodInduk::query()->where('entiti', 'lot_tanah')->where('id_rekod', $lot->id)
            ->orderByRaw('no_versi DESC NULLS FIRST')->orderByDesc('created_at')->get()
            ->map(fn (VersiRekodInduk $v) => [
                'id' => $v->id,
                'no_versi' => $v->no_versi,
                'kritikal' => $v->kritikal,
                'status_pengesahan' => $v->status_pengesahan,
                'medan' => array_keys((array) $v->nilai_selepas),
                'pengguna' => $v->id_pengguna ? DB::table('users')->where('id', $v->id_pengguna)->value('name') : null,
                'pengesah_kedua' => $v->id_pengesah_kedua ? DB::table('users')->where('id', $v->id_pengesah_kedua)->value('name') : null,
                'justifikasi' => $v->justifikasi,
                'catatan_pengesahan' => $v->catatan_pengesahan,
                'tarikh' => $v->created_at?->toIso8601String(),
                'tarikh_pengesahan' => $v->tarikh_pengesahan?->toIso8601String(),
            ]);

        return response()->json(['data' => $rows]);
    }

    public function compare(Request $request, string $id, string $versionId): JsonResponse
    {
        $lot = $this->lot($request, $id);
        $version = VersiRekodInduk::query()->where('entiti', 'lot_tanah')->where('id_rekod', $lot->id)->findOrFail($versionId);

        return response()->json(['data' => [
            'id' => $version->id,
            'no_versi' => $version->no_versi,
            'status_pengesahan' => $version->status_pengesahan,
            'perbandingan' => app(Klasifikasi::class)->maskedFields('lot_tanah', $this->user($request)) === []
                ? $this->lots->compare($version)
                : array_map(fn ($r) => in_array($r['medan'], app(Klasifikasi::class)->maskedFields('lot_tanah', $this->user($request)), true) ? [...$r, 'sebelum' => null, 'selepas' => null, 'disamarkan' => true] : $r, $this->lots->compare($version)),
        ]]);
    }

    /** SRS-PRO-29: GeoJSON of one lot. */
    public function geojson(Request $request, string $id, SpatialService $spatial): JsonResponse
    {
        $lot = $this->lot($request, $id);
        $feature = $spatial->feature($lot);
        if ($feature === null) {
            return response()->json(['message' => __('pro::pro.status_spatial.tiada_geometri')], 404);
        }

        return response()->json($feature, 200, ['Content-Type' => 'application/geo+json']);
    }

    /** FeatureCollection for the map (scoped + filtered like the list). */
    public function collection(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $ids = $this->query->for($this->user($request), $filters)->limit(5000)->pluck('lot_tanah.id')->all();
        $rows = $ids === [] ? [] : DB::table('data_spatial as d')->join('lot_tanah as l', 'l.id', '=', 'd.lot_id')
            ->where('d.semasa', true)->whereIn('d.lot_id', $ids)
            ->get(['l.id', 'l.no_lot', 'l.kod_mukim', 'l.bahagian_pemilik', 'd.status_pengesahan', DB::raw('ST_AsGeoJSON(d.geometri, 6) AS g')]);

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => collect($rows)->map(fn ($r) => [
                'type' => 'Feature', 'id' => $r->id,
                'geometry' => json_decode((string) $r->g, true),
                'properties' => ['lot_id' => $r->id, 'no_lot' => $r->no_lot, 'kod_mukim' => $r->kod_mukim, 'bahagian_pemilik' => $r->bahagian_pemilik, 'status_pengesahan' => $r->status_pengesahan, 'disahkan' => in_array($r->status_pengesahan, DataSpatial::DISAHKAN, true)],
            ])->values(),
        ], 200, ['Content-Type' => 'application/geo+json']);
    }

    /** SRS-PRO-29 "kedudukan kewangan" — data shape fixed now, figures arrive with G3 modules. */
    public function financial(Request $request, string $id, Profil360 $profile): JsonResponse
    {
        $lot = $this->lot($request, $id);
        $data = $profile->build($lot, $this->user($request));

        return response()->json(['data' => ['lot_id' => $lot->id, 'no_lot' => $lot->no_lot, ...$data['kewangan']]]);
    }

    /** SRS-PRO-12: run the quality rules on one lot now and route failures to the steward. */
    public function checkQuality(Request $request, string $id, PeraturanKualiti $rules, PenghalaKualiti $router): JsonResponse
    {
        $lot = $this->lot($request, $id, false);
        $issues = $rules->checkLot($lot);
        $tasks = $router->route('lot_tanah', $lot->id, $issues, $lot);

        return response()->json(['data' => ['isu' => $issues, 'tugasan_baharu' => $tasks->count()]]);
    }

    public function archive(Request $request, string $id, PengurusArkib $archive): JsonResponse
    {
        $lot = $this->lot($request, $id, false);
        $data = $request->validate(['sebab' => ['required', 'string', 'max:500']]);
        $record = $archive->proposeLot($lot, $this->user($request), $data['sebab']);

        return response()->json(['message' => __('pro::pro.arkib.dihantar'), 'data' => ['id' => $record->id, 'status_arkib' => $record->status_arkib]], 202);
    }

    public function restore(Request $request, string $id, PengurusArkib $archive): JsonResponse
    {
        $lot = $this->lot($request, $id);
        $data = $request->validate(['sebab' => ['required', 'string', 'max:500']]);
        $lot = $archive->restore($lot, $this->user($request), $data['sebab']);

        return response()->json(['message' => __('pro::pro.arkib.dipulih'), 'data' => $this->presenter->detail($lot, $this->user($request))]);
    }

    /** Dropdown values for the lot form — lists come from MOD-REF (SRS-PRO-25). */
    public function references(RujukanInduk $ref): JsonResponse
    {
        $current = fn (string $table, array $cols) => DB::table($table)->whereNull('sah_hingga')->where('aktif', true)->orderBy('susunan')->orderBy('kod')->get($cols);

        return response()->json(['data' => [
            'daerah' => $current('ref_daerah', ['kod', 'nama']),
            'mukim' => $current('ref_mukim', ['kod', 'nama', 'daerah_kod']),
            'kategori_guna_tanah' => $current('ref_kategori_guna_tanah', ['kod', 'nama']),
            'bahagian_pemilik' => array_map(fn ($b) => ['kod' => $b, 'nama' => __('pro::pro.bahagian.'.$b)], LotTanah::BAHAGIAN),
            'status_lot' => array_map(fn ($s) => ['kod' => $s, 'nama' => __('pro::pro.status_lot.'.$s)], LotTanah::STATUS),
            'klasifikasi' => array_map(fn ($s) => ['kod' => $s, 'nama' => __('pro::pro.klasifikasi.'.$s)], LotTanah::KLASIFIKASI),
            'unit_keluasan' => array_map(fn ($s) => ['kod' => $s, 'nama' => __('pro::pro.unit.'.$s)], LotTanah::UNIT),
            'status_pengesahan' => array_map(fn ($s) => ['kod' => $s, 'nama' => __('pro::pro.status_spatial.'.$s)], [...DataSpatial::STATUS, 'tiada_geometri']),
            'sumber_spatial' => array_map(fn ($s) => ['kod' => $s, 'nama' => __('pro::pro.sumber_spatial.'.$s)], DataSpatial::SUMBER),
            'srid' => array_map(fn ($s) => ['kod' => $s, 'nama' => __('pro::pro.srid.'.$s)], DataSpatial::SRID),
            'medan_kritikal' => $this->lots->criticalFields(),
        ]]);
    }

    /** @return array<string, mixed> */
    private function input(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'nullable';

        return $request->validate([
            'no_lot' => [$req, 'nullable', 'string', 'max:50'],
            'nama_hartanah' => ['sometimes', 'nullable', 'string', 'max:200'],
            'kod_mukim' => [$req, 'nullable', 'string', 'max:6'],
            'kod_daerah' => ['sometimes', 'nullable', 'string', 'max:4'],
            'keluasan' => [$req, 'nullable'],
            'unit_keluasan' => ['sometimes', 'nullable', 'string', 'max:10'],
            'kategori_guna_tanah' => ['sometimes', 'nullable', 'string', 'max:30'],
            'zoning' => ['sometimes', 'nullable', 'string', 'max:255'],
            'syarat_nyata' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'bahagian_pemilik' => [$req, 'nullable', 'string', 'max:30'],
            'status_lot' => ['sometimes', 'nullable', 'string', 'max:30'],
            'klasifikasi' => ['sometimes', 'nullable', 'string', 'max:10'],
            'id_data_owner' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'id_data_steward' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'tag' => ['sometimes', 'nullable', 'array', 'max:20'],
            'tag.*' => ['string', 'max:50'],
            'catatan' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ], [], (array) __('pro::pro.medan'));
    }
}
