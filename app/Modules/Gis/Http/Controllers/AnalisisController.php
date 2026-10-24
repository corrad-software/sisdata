<?php

namespace App\Modules\Gis\Http\Controllers;

use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Gis\Models\PaparanTersimpan;
use App\Modules\Gis\Models\SenarioAnalisis;
use App\Modules\Gis\Services\AnalisisSpatial;
use App\Modules\Gis\Support\EksportJadual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Spatial analysis (SRS-GIS-15..21), named scenarios (GIS-20) and saved map views. */
class AnalisisController extends GisController
{
    public function __construct(private AnalisisSpatial $analysis) {}

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'jenis' => ['required', Rule::in(SenarioAnalisis::JENIS)],
            'parameter' => ['required', 'array'],
            'parameter.jarak_m' => ['nullable', 'numeric', 'min:1', 'max:100000'],
            'parameter.lapisan' => ['nullable'],
            'parameter.lapisan_b' => ['required_if:jenis,tindihan', 'array', 'min:1', 'max:5'],
            'parameter.lapisan_sasaran' => ['required_if:jenis,kedekatan', 'string'],
            'parameter.sumber' => ['required_if:jenis,penimbal,keluasan', 'array'],
            'parameter.bilangan' => ['nullable', 'integer', 'min:1', 'max:100'],
            'parameter.minimum' => ['nullable', 'integer', 'min:2', 'max:100'],
        ];
    }

    /**
     * Validate, then take the whole `parameter` object (nested rules would
     * otherwise strip keys the per-analysis service reads, e.g. kawasan).
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function validated(Request $request, array $extra = []): array
    {
        $data = $request->validate($this->rules() + $extra);
        $data['parameter'] = (array) $request->input('parameter', []);

        return $data;
    }

    public function run(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        return response()->json($this->analysis->run($this->user($request), $data['jenis'], $data['parameter']));
    }

    /** Export the lot list of an analysis (re-run, so the user's current scope applies). */
    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        $data = $this->validated($request, ['format' => ['required', Rule::in(['csv', 'xlsx'])]]);
        $result = $this->analysis->run($this->user($request), $data['jenis'], $data['parameter']);
        $audit->record('eksport', 'gis', 'gis_senario_analisis', null, meta: ['jenis' => $data['jenis'], 'format' => $data['format'], 'bilangan' => count($result['hasil'])]);

        $columns = ['id' => 'ID', 'label' => 'Label', 'lapisan' => 'Lapisan'];
        foreach ($result['hasil'] as $row) {
            foreach (array_keys($row) as $k) {
                if (! isset($columns[$k]) && ! in_array($k, ['pautan', 'ahli'], true)) {
                    $columns[$k] = (string) __('gis::gis.lajur.'.$k) === 'gis::gis.lajur.'.$k ? $k : (string) __('gis::gis.lajur.'.$k);
                }
            }
        }

        return EksportJadual::download($result['hasil'], $columns, $data['format'], 'analisis-'.$data['jenis']);
    }

    // ---- Scenarios (SRS-GIS-20) ------------------------------------------------

    public function scenarios(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $rows = SenarioAnalisis::query()
            ->where(fn ($q) => $q->where('id_pengguna', $user->id)->orWhere('kebenaran_kongsi', 'semua')
                ->orWhere(fn ($w) => $w->where('kebenaran_kongsi', 'pengguna')->whereJsonContains('dikongsi_dengan', $user->id)))
            ->latest()->limit(200)->get();

        return response()->json(['data' => $rows->map(fn (SenarioAnalisis $s) => $this->present($s, $user->id))]);
    }

    public function storeScenario(Request $request): JsonResponse
    {
        $data = $this->validated($request, [
            'nama' => ['required', 'string', 'max:150'],
            'kebenaran_kongsi' => ['required', Rule::in(SenarioAnalisis::KONGSI)],
            'dikongsi_dengan' => ['nullable', 'array', 'max:100'],
            'dikongsi_dengan.*' => ['integer', 'exists:users,id'],
        ]);
        $user = $this->user($request);
        $result = $this->analysis->run($user, $data['jenis'], $data['parameter']);

        $scenario = SenarioAnalisis::query()->create([
            'nama' => $data['nama'],
            'jenis_analisis' => $data['jenis'],
            'parameter_json' => $data['parameter'],
            'ringkasan_hasil' => $result['ringkasan'] + ['ids' => array_slice(array_column($result['hasil'], 'id'), 0, 2000)],
            'id_pengguna' => $user->id,
            'kebenaran_kongsi' => $data['kebenaran_kongsi'],
            'dikongsi_dengan' => array_values(array_map('intval', $data['dikongsi_dengan'] ?? [])),
        ]);

        return response()->json(['data' => $this->present($scenario, $user->id)], 201);
    }

    /** Open a scenario: re-run with the viewer's own scope (sharing never widens access). */
    public function showScenario(Request $request, int $id): JsonResponse
    {
        $user = $this->user($request);
        $scenario = $this->scenario($id, $user->id);

        return response()->json([
            'senario' => $this->present($scenario, $user->id),
            'hasil' => $this->analysis->run($user, $scenario->jenis_analisis, $scenario->parameter_json),
        ]);
    }

    public function destroyScenario(Request $request, int $id): JsonResponse
    {
        $user = $this->user($request);
        $scenario = $this->scenario($id, $user->id);
        abort_unless($scenario->id_pengguna === $user->id, 403, __('gis::gis.senario.bukan_pemilik'));
        $scenario->delete();

        return response()->json(['message' => __('gis::gis.senario.dipadam')]);
    }

    private function scenario(int $id, int $userId): SenarioAnalisis
    {
        $scenario = SenarioAnalisis::query()->find($id);
        if ($scenario === null || ! $scenario->visibleTo($userId)) {
            throw new NotFoundHttpException(__('gis::gis.senario.tidak_wujud'));
        }

        return $scenario;
    }

    /** @return array<string, mixed> */
    private function present(SenarioAnalisis $s, int $userId): array
    {
        return [
            'id' => $s->id, 'nama' => $s->nama, 'jenis_analisis' => $s->jenis_analisis, 'parameter' => $s->parameter_json,
            'ringkasan' => array_diff_key($s->ringkasan_hasil, ['ids' => true]), 'kebenaran_kongsi' => $s->kebenaran_kongsi,
            'dikongsi_dengan' => $s->dikongsi_dengan, 'milik_saya' => $s->id_pengguna === $userId, 'dicipta_pada' => $s->created_at?->toIso8601String(),
        ];
    }

    // ---- Saved map views / bookmarks --------------------------------------------

    public function views(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return response()->json(['data' => PaparanTersimpan::query()
            ->where(fn ($q) => $q->where('id_pengguna', $user->id)->orWhere('dikongsi', true))
            ->latest()->limit(100)->get()
            ->map(fn (PaparanTersimpan $p) => $p->only(['id', 'nama', 'lapisan', 'pusat', 'zum', 'peta_asas', 'dikongsi']) + ['milik_saya' => $p->id_pengguna === $user->id])]);
    }

    public function storeView(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'lapisan' => ['present', 'array', 'max:50'],
            'lapisan.*' => ['string', 'max:60'],
            'pusat' => ['required', 'array', 'size:2'],
            'pusat.*' => ['numeric'],
            'zum' => ['required', 'numeric', 'between:0,24'],
            'peta_asas' => ['nullable', 'string', 'max:30'],
            'dikongsi' => ['nullable', 'boolean'],
        ]);
        $view = PaparanTersimpan::query()->create($data + ['id_pengguna' => $this->user($request)->id, 'peta_asas' => $data['peta_asas'] ?? 'osm', 'dikongsi' => (bool) ($data['dikongsi'] ?? false)]);

        return response()->json(['data' => $view->only(['id', 'nama', 'lapisan', 'pusat', 'zum', 'peta_asas', 'dikongsi'])], 201);
    }

    public function destroyView(Request $request, int $id): JsonResponse
    {
        $view = PaparanTersimpan::query()->where('id_pengguna', $this->user($request)->id)->findOrFail($id);
        $view->delete();

        return response()->json(['message' => __('gis::gis.paparan.dipadam')]);
    }
}
