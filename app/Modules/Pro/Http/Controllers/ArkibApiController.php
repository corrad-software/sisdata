<?php

namespace App\Modules\Pro\Http\Controllers;

use App\Modules\Pro\Models\JadualRetensi;
use App\Modules\Pro\Models\LotTanah;
use App\Modules\Pro\Models\RekodArkib;
use App\Modules\Pro\Services\KunciApiService;
use App\Modules\Pro\Services\LotQuery;
use App\Modules\Pro\Services\PengurusArkib;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** SRS-PRO-23 retention/archive screens and SRS-PRO-29 API key management. */
class ArkibApiController extends ProController
{
    // ---- Archive --------------------------------------------------------------

    public function archive(Request $request, LotQuery $lots): JsonResponse
    {
        $status = $request->validate(['status' => ['nullable', Rule::in(['dicadang', 'menunggu_kelulusan', 'diarkib', 'ditolak', 'dipulih'])]])['status'] ?? null;
        $visible = $lots->for($this->user($request), ['arkib' => true])->selectRaw('lot_tanah.id::text')->toBase();

        $page = RekodArkib::query()->where('entiti', 'lot_tanah')
            ->whereIn('id_rekod', $visible)
            ->when($status !== null, fn ($q) => $q->where('status_arkib', $status))
            ->orderByDesc('created_at')->paginate((int) $request->integer('per_page', 25));
        $lotRows = LotTanah::query()->withoutGlobalScope('access')->whereIn('id', collect($page->items())->pluck('id_rekod'))->get(['id', 'no_lot', 'kod_mukim', 'kod_daerah', 'status_lot'])->keyBy('id');

        return response()->json([
            'data' => collect($page->items())->map(fn (RekodArkib $r) => [
                ...$r->toArray(),
                'lot' => $lotRows[$r->id_rekod] ?? null,
            ])->values(),
            'meta' => $this->meta($page),
        ]);
    }

    public function generate(PengurusArkib $archive): JsonResponse
    {
        return response()->json(['data' => ['cadangan_baharu' => $archive->proposeDue()]]);
    }

    public function submit(Request $request, string $id, PengurusArkib $archive, LotQuery $lots): JsonResponse
    {
        $record = RekodArkib::query()->findOrFail($id);
        abort_if($lots->find($this->user($request), (string) $record->id_rekod) === null, 404);

        return response()->json(['message' => __('pro::pro.arkib.dihantar'), 'data' => $archive->submit($record, $this->user($request))]);
    }

    public function schedules(): JsonResponse
    {
        return response()->json(['data' => JadualRetensi::query()->orderBy('id')->get()]);
    }

    public function updateSchedule(Request $request, int $id): JsonResponse
    {
        $schedule = JadualRetensi::query()->findOrFail($id);
        $schedule->update($request->validate([
            'keterangan' => ['sometimes', 'string', 'max:255'],
            'syarat' => ['sometimes', 'array'],
            'syarat.status_lot' => ['sometimes', 'array'],
            'syarat.status_lot.*' => [Rule::in(LotTanah::STATUS)],
            'tempoh_retensi_tahun' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'rujukan_dasar_mais' => ['sometimes', 'nullable', 'string', 'max:255'],
            'memerlukan_kelulusan' => ['sometimes', 'boolean'],
            'aktif' => ['sometimes', 'boolean'],
        ]));

        return response()->json(['message' => __('pro::pro.arkib.jadual_disimpan'), 'data' => $schedule->refresh()]);
    }

    // ---- API keys -------------------------------------------------------------

    public function keys(Request $request, KunciApiService $keys, AccessControl $access): JsonResponse
    {
        $user = $this->user($request);
        $all = $access->allows($user, 'pro.api.kemas_kini');

        return response()->json(['data' => $keys->list($all ? null : $user), 'skop' => KunciApiService::SCOPES]);
    }

    public function createKey(Request $request, KunciApiService $keys): JsonResponse
    {
        if ($request->user()?->currentAccessToken() instanceof PersonalAccessToken) {
            throw new AccessDeniedHttpException(__('pro::pro.api.sesi_sahaja'));
        }
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:80'],
            'sistem_pemanggil' => ['required', 'string', 'max:100'],
            'skop' => ['required', 'array', 'min:1'],
            'skop.*' => [Rule::in(array_keys(KunciApiService::SCOPES))],
            'tamat_hari' => ['nullable', 'integer', 'min:1', 'max:365'],
            'had_kadar_seminit' => ['nullable', 'integer', 'min:1', 'max:6000'],
        ], [], (array) __('pro::pro.medan'));

        $key = $keys->create($this->user($request), $data['nama'], $data['sistem_pemanggil'], array_values($data['skop']), (int) ($data['tamat_hari'] ?? 90), (int) ($data['had_kadar_seminit'] ?? 60));

        return response()->json(['message' => __('pro::pro.api.dicipta'), 'data' => $key], 201);
    }

    public function revokeKey(Request $request, int $id, KunciApiService $keys, AccessControl $access): Response
    {
        $user = $this->user($request);
        $owner = DB::table('kunci_api_pro')->where('id_token', $id)->value('id_pemilik');
        abort_if($owner === null || ((int) $owner !== $user->id && ! $access->allows($user, 'pro.api.kemas_kini')), 404);
        $keys->revoke($id, $user);

        return response()->noContent();
    }

    public function usage(Request $request, KunciApiService $keys): JsonResponse
    {
        $f = $request->validate(['dari' => ['nullable', 'date'], 'hingga' => ['nullable', 'date']]);

        return response()->json(['data' => $keys->usage($f['dari'] ?? null, $f['hingga'] ?? null)]);
    }

    /** SRS-PRO-29: the OpenAPI 3.0 document of this API. */
    public function openapi(): Response
    {
        $path = base_path('docs/api/pro-openapi.yaml');
        abort_unless(is_file($path), 404);

        return response((string) file_get_contents($path), 200, ['Content-Type' => 'application/yaml; charset=UTF-8']);
    }
}
