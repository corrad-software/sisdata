<?php

namespace App\Modules\Pro\Http\Controllers;

use App\Models\User;
use App\Modules\Pro\Models\CadanganDuplikasi;
use App\Modules\Pro\Models\LotTanah;
use App\Modules\Pro\Models\PeraturanKualitiData;
use App\Modules\Pro\Models\StewardshipData;
use App\Modules\Pro\Models\TugasanKualitiData;
use App\Modules\Pro\Services\LaporanKualiti;
use App\Modules\Pro\Services\LotPresenter;
use App\Modules\Pro\Services\LotQuery;
use App\Modules\Pro\Services\Pengeksport;
use App\Modules\Pro\Services\PengesanDuplikasi;
use App\Modules\Pro\Services\PenghalaKualiti;
use App\Modules\Pro\Services\PeraturanKualiti;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Data governance screens: stewardship register (SRS-PRO-11), quality rules
 * and steward task list (SRS-PRO-12/13), data-quality report (SRS-PRO-24,
 * SRS-DAT-09) and the duplicate queue (SRS-PRO-20, SRS-DAT-02).
 */
class TadbirUrusController extends ProController
{
    // ---- Stewardship register -------------------------------------------------

    public function stewardship(): JsonResponse
    {
        $rows = StewardshipData::query()->orderBy('id')->get()->map(fn (StewardshipData $s) => [
            ...$s->toArray(),
            'nama_data_owner' => $s->id_data_owner ? User::query()->whereKey($s->id_data_owner)->value('name') : null,
            'nama_data_steward' => $s->id_data_steward ? User::query()->whereKey($s->id_data_steward)->value('name') : null,
            'bil_tugasan_terbuka' => TugasanKualitiData::query()->where('id_stewardship', $s->id)->where('status', 'terbuka')->count(),
        ]);

        return response()->json(['data' => $rows]);
    }

    public function storeStewardship(Request $request): JsonResponse
    {
        $entry = StewardshipData::query()->create($this->stewardshipInput($request));

        return response()->json(['message' => __('pro::pro.stewardship.disimpan'), 'data' => $entry], 201);
    }

    public function updateStewardship(Request $request, int $id): JsonResponse
    {
        $entry = StewardshipData::query()->findOrFail($id);
        $entry->update($this->stewardshipInput($request, true));

        return response()->json(['message' => __('pro::pro.stewardship.disimpan'), 'data' => $entry->refresh()]);
    }

    public function exportStewardship(Request $request, Pengeksport $exporter): Response
    {
        $format = (string) $request->validate(['format' => ['required', Rule::in(Pengeksport::FORMATS)]])['format'];
        $rows = collect($this->stewardship()->getData(true)['data']);

        return $exporter->download($rows->all(), [
            'komponen_data' => 'Komponen Data', 'entiti_berkaitan' => 'Entiti', 'jawatan_data_owner' => 'Jawatan Data Owner',
            'bahagian_data_owner' => 'Bahagian Data Owner', 'nama_data_owner' => 'Data Owner', 'jawatan_data_steward' => 'Jawatan Data Steward',
            'bahagian_data_steward' => 'Bahagian Data Steward', 'nama_data_steward' => 'Data Steward', 'bahagian_pemilik' => 'Bahagian Lot',
            'kod_daerah' => 'Daerah', 'peranan_tanggungjawab' => 'Tanggungjawab', 'tarikh_berkuat_kuasa' => 'Berkuat Kuasa', 'status' => 'Status',
        ], $format, 'daftar-stewardship', (string) __('pro::pro.eksport.tajuk_stewardship'));
    }

    /** Minimal user picker for owner / steward fields. */
    public function users(Request $request): JsonResponse
    {
        $q = (string) $request->query('q', '');

        return response()->json(['data' => User::query()
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'ilike', '%'.$q.'%')->orWhere('email', 'ilike', '%'.$q.'%')))
            ->orderBy('name')->limit(20)->get(['id', 'name', 'email'])]);
    }

    /** @return array<string, mixed> */
    private function stewardshipInput(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'komponen_data' => [$req, 'string', 'max:100'],
            'entiti_berkaitan' => [$req, 'string', 'max:100'],
            'jawatan_data_owner' => [$req, 'string', 'max:150'],
            'bahagian_data_owner' => [$req, 'string', 'max:150'],
            'id_data_owner' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'jawatan_data_steward' => [$req, 'string', 'max:150'],
            'bahagian_data_steward' => [$req, 'string', 'max:150'],
            'id_data_steward' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'bahagian_pemilik' => ['sometimes', 'nullable', Rule::in(LotTanah::BAHAGIAN)],
            'kod_daerah' => ['sometimes', 'nullable', 'string', 'size:4'],
            'peranan_tanggungjawab' => [$req, 'string', 'max:2000'],
            'tarikh_berkuat_kuasa' => [$req, 'date'],
            'status' => ['sometimes', Rule::in(['aktif', 'tidak_aktif'])],
        ], [], (array) __('pro::pro.medan'));
    }

    // ---- Quality rules + tasks -----------------------------------------------

    public function rules(): JsonResponse
    {
        return response()->json(['data' => PeraturanKualitiData::query()->orderBy('entiti')->orderBy('medan')->orderBy('id')->get()]);
    }

    public function storeRule(Request $request, PeraturanKualiti $engine): JsonResponse
    {
        $rule = PeraturanKualitiData::query()->create($this->ruleInput($request));
        $engine->flush();

        return response()->json(['message' => __('pro::pro.kualiti.peraturan_disimpan'), 'data' => $rule], 201);
    }

    public function updateRule(Request $request, int $id, PeraturanKualiti $engine): JsonResponse
    {
        $rule = PeraturanKualitiData::query()->findOrFail($id);
        $rule->update($this->ruleInput($request, true));
        $engine->flush();

        return response()->json(['message' => __('pro::pro.kualiti.peraturan_disimpan'), 'data' => $rule->refresh()]);
    }

    /** @return array<string, mixed> */
    private function ruleInput(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';
        $data = $request->validate([
            'entiti' => [$req, Rule::in(['lot_tanah', 'data_spatial'])],
            'medan' => [$req, 'string', 'max:64'],
            'jenis_peraturan' => [$req, Rule::in(['wajib', 'format', 'julat', 'senarai', 'panjang', 'rujukan', 'unik', 'lengkap'])],
            'ungkapan_peraturan' => ['sometimes', 'array'],
            'mesej_ralat' => [$req, 'string', 'max:255'],
            'tahap_keterukan' => ['sometimes', Rule::in(['ralat', 'amaran'])],
            'status_aktif' => ['sometimes', 'boolean'],
        ]);
        if (isset($data['ungkapan_peraturan']['regex']) && @preg_match('/'.str_replace('/', '\/', (string) $data['ungkapan_peraturan']['regex']).'/u', '') === false) {
            throw ValidationException::withMessages(['ungkapan_peraturan' => [__('pro::pro.kualiti.regex_tidak_sah')]]);
        }

        return $data;
    }

    public function tasks(Request $request): JsonResponse
    {
        $f = $request->validate([
            'status' => ['nullable', Rule::in(['terbuka', 'selesai', 'ditolak'])],
            'saya' => ['nullable', 'boolean'],
            'entiti' => ['nullable', 'string', 'max:64'],
            'kod_daerah' => ['nullable', 'string', 'max:4'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);
        $user = $this->user($request);
        $access = app(AccessControl::class);
        $districts = $access->scope($user, 'daerah');

        $page = TugasanKualitiData::query()
            ->when(! empty($f['status']), fn ($q) => $q->where('status', $f['status']))
            ->when(! empty($f['saya']), fn ($q) => $q->where('id_data_steward', $user->id))
            ->when(! empty($f['entiti']), fn ($q) => $q->where('entiti', $f['entiti']))
            ->when(! empty($f['kod_daerah']), fn ($q) => $q->where('kod_daerah', $f['kod_daerah']))
            ->when($districts !== null, fn ($q) => $q->where(fn ($w) => $w->whereIn('kod_daerah', $districts)->orWhere('id_data_steward', $user->id)))
            ->orderByRaw("CASE status WHEN 'terbuka' THEN 0 ELSE 1 END")->orderByDesc('id')
            ->paginate((int) ($f['per_page'] ?? 25));

        $lotIds = collect($page->items())->whereIn('entiti', ['lot_tanah', 'data_spatial'])->pluck('id_rekod')->unique()->all();
        $lots = LotTanah::query()->withoutGlobalScope('access')->whereIn('id', $lotIds)->pluck('no_lot', 'id');

        return response()->json([
            'data' => collect($page->items())->map(fn (TugasanKualitiData $t) => [
                ...$t->toArray(),
                'no_lot' => $lots[$t->id_rekod] ?? null,
                'pautan' => in_array($t->entiti, ['lot_tanah', 'data_spatial'], true) ? '/admin/pro/lot/'.$t->id_rekod : ($t->entiti === 'sesi_import' ? '/admin/pro/import/'.$t->id_rekod : null),
                'nama_data_steward' => $t->id_data_steward ? User::query()->whereKey($t->id_data_steward)->value('name') : null,
            ])->values(),
            'meta' => $this->meta($page),
        ]);
    }

    public function resolveTask(Request $request, int $id, PenghalaKualiti $router): JsonResponse
    {
        $task = TugasanKualitiData::query()->findOrFail($id);
        $data = $request->validate([
            'tindakan_pembetulan' => ['required', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in(['selesai', 'ditolak'])],
        ], [], (array) __('pro::pro.medan'));
        $router->resolve($task, $this->user($request), $data['tindakan_pembetulan'], $data['status'] ?? 'selesai');

        return response()->json(['message' => __('pro::pro.kualiti.tugasan_selesai'), 'data' => $task->refresh()]);
    }

    // ---- Data-quality report --------------------------------------------------

    /** @return array<string, mixed> */
    private function reportFilters(Request $request): array
    {
        return $request->validate([
            'kod_daerah' => ['nullable', 'string', 'max:4'],
            'kod_mukim' => ['nullable', 'string', 'max:6'],
            'bahagian_pemilik' => ['nullable', Rule::in(LotTanah::BAHAGIAN)],
            'entiti' => ['nullable', Rule::in(array_values(array_unique(LaporanKualiti::CATEGORIES)))],
            'id_data_steward' => ['nullable', 'integer'],
        ]);
    }

    public function report(Request $request, LaporanKualiti $report): JsonResponse
    {
        return response()->json(['data' => $report->build($this->user($request), $this->reportFilters($request), 100)]);
    }

    public function exportReport(Request $request, LaporanKualiti $report, Pengeksport $exporter): Response
    {
        $filters = $this->reportFilters($request);
        $format = (string) $request->validate(['format' => ['required', Rule::in(Pengeksport::FORMATS)]])['format'];

        return $exporter->download($report->flat($this->user($request), $filters), [
            'kategori' => 'Kategori', 'no_lot' => 'No. Lot', 'nama_mukim' => 'Mukim', 'nama_daerah' => 'Daerah',
            'bahagian_pemilik' => 'Bahagian', 'id_data_steward' => 'ID Data Steward', 'keterangan' => 'Keterangan',
        ], $format, 'laporan-kualiti-data', (string) __('pro::pro.eksport.tajuk_kualiti'));
    }

    /** Run the scheduled routing now (SRS-PRO-12). */
    public function runReport(LaporanKualiti $report, PenghalaKualiti $router): JsonResponse
    {
        return response()->json(['data' => ['tugasan_baharu' => $report->routeAll($router)]]);
    }

    // ---- Duplicates -----------------------------------------------------------

    public function duplicates(Request $request, LotPresenter $presenter, LotQuery $lots): JsonResponse
    {
        $f = $request->validate([
            'keputusan' => ['nullable', Rule::in(['menunggu', 'sah', 'gabung', 'tolak'])],
            'id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $user = $this->user($request);
        $visible = $lots->for($user, ['arkib' => true])->select('lot_tanah.id')->toBase();

        $page = CadanganDuplikasi::query()
            ->when(! empty($f['id']), fn ($q) => $q->whereKey($f['id']))
            ->when(empty($f['id']), fn ($q) => $q->where('keputusan', $f['keputusan'] ?? 'menunggu'))
            ->whereIn('id_rekod_a', $visible)->whereIn('id_rekod_b', clone $visible)
            ->orderByDesc('skor_padanan')->paginate((int) ($f['per_page'] ?? 20));

        $ids = collect($page->items())->flatMap(fn ($c) => [$c->id_rekod_a, $c->id_rekod_b])->unique()->all();
        $lotRows = LotTanah::query()->withoutGlobalScope('access')->with('spatialSemasa')->whereIn('id', $ids)->get()->keyBy('id');

        return response()->json([
            'data' => collect($page->items())->map(fn (CadanganDuplikasi $c) => [
                'id' => $c->id,
                'skor_padanan' => (float) $c->skor_padanan,
                'kriteria_padanan' => $c->kriteria_padanan,
                'keputusan' => $c->keputusan,
                'catatan_keputusan' => $c->catatan_keputusan,
                'tarikh_keputusan' => $c->tarikh_keputusan?->toIso8601String(),
                'id_data_steward' => $c->id_data_steward,
                'lot_a' => isset($lotRows[$c->id_rekod_a]) ? $presenter->summary($lotRows[$c->id_rekod_a], $user) : null,
                'lot_b' => isset($lotRows[$c->id_rekod_b]) ? $presenter->summary($lotRows[$c->id_rekod_b], $user) : null,
            ])->values(),
            'meta' => $this->meta($page),
        ]);
    }

    public function detectDuplicates(Request $request, PengesanDuplikasi $detector): JsonResponse
    {
        $lotId = $request->validate(['lot_id' => ['nullable', 'uuid']])['lot_id'] ?? null;

        return response()->json(['data' => $detector->detect($lotId)]);
    }

    public function decideDuplicate(Request $request, int $id, PengesanDuplikasi $detector, LotQuery $lots): JsonResponse
    {
        $suggestion = CadanganDuplikasi::query()->findOrFail($id);
        $user = $this->user($request);
        if ($lots->find($user, (string) $suggestion->id_rekod_a) === null || $lots->find($user, (string) $suggestion->id_rekod_b) === null) {
            throw new NotFoundHttpException(__('pro::pro.lot.tiada'));
        }
        $data = $request->validate([
            'keputusan' => ['required', Rule::in(PengesanDuplikasi::DECISIONS)],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'id_rekod_kekal' => ['nullable', 'uuid'],
        ]);
        $suggestion = $detector->decide($suggestion, $data['keputusan'], $user, $data['catatan'] ?? null, $data['id_rekod_kekal'] ?? null);

        return response()->json(['message' => __('pro::pro.duplikasi.direkod'), 'data' => $suggestion]);
    }

    public function criteria(PengesanDuplikasi $detector): JsonResponse
    {
        return response()->json(['data' => $detector->criteria(), 'parameter' => 'pro.kriteria_duplikasi']);
    }
}
