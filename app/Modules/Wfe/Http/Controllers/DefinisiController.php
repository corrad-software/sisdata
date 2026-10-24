<?php

namespace App\Modules\Wfe\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Wfe\Models\Definisi;
use App\Modules\Wfe\Models\DefinisiLog;
use App\Modules\Wfe\Models\Instans;
use App\Modules\Wfe\Models\Kumpulan;
use App\Modules\Wfe\Models\Peralihan;
use App\Modules\Wfe\Services\ConditionEvaluator;
use App\Modules\Wfe\Services\DefinitionService;
use App\Modules\Wfe\Services\FlowRegistry;
use App\Modules\Wfe\Services\StepUp;
use App\Modules\Wfe\Support\WfeException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * SRS-WFE-01..05, 09, 12: flow definitions. The form editor and the visual
 * (React Flow) editor both send the same structure to store()/update(); the
 * only difference is the recorded method (kaedah).
 */
class DefinisiController extends Controller
{
    public function __construct(private DefinitionService $definitions, private FlowRegistry $flows, private StepUp $stepUp, private ConditionEvaluator $conditions) {}

    /** Flow codes modules have declared (WorkflowFlows). */
    public function flows(): JsonResponse
    {
        $active = Definisi::query()->aktif()->pluck('no_versi', 'kod_aliran');

        return response()->json(['data' => array_map(fn ($f) => $f + ['versi_aktif' => $active[$f['kod_aliran']] ?? null], $this->flows->all())]);
    }

    /** Lookups for the editors. */
    public function reference(): JsonResponse
    {
        return response()->json(['data' => [
            'operator' => ConditionEvaluator::OPERATORS,
            'jenis_pelulus' => ['peranan' => 'Peranan', 'jawatan' => 'Jawatan', 'individu' => 'Individu', 'kumpulan' => 'Kumpulan pelulus', 'penyelia_pemohon' => 'Penyelia pemohon'],
            'jenis_penerima_eskalasi' => ['penyelia' => 'Penyelia (hierarki)', 'peranan' => 'Peranan', 'jawatan' => 'Jawatan', 'individu' => 'Individu', 'kumpulan' => 'Kumpulan'],
            'mod_kelulusan' => ['mana_mana' => 'Mana-mana satu (selari)', 'semua' => 'Semua pelulus (selari)', 'kuorum' => 'Kuorum'],
            'unit_sla' => ['hari_bekerja' => 'Hari bekerja', 'jam' => 'Jam bekerja'],
            'kumpulan' => Kumpulan::query()->orderBy('nama')->get(['kod', 'nama']),
            'aliran' => $this->flows->all(),
        ]]);
    }

    public function index(Request $request): JsonResponse
    {
        $rows = Definisi::query()
            ->when($request->boolean('templat'), fn ($q) => $q->where('templat', true))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('kod_aliran'), fn ($q, $k) => $q->where('kod_aliran', $k))
            ->when(! $request->query('kod_aliran') && ! $request->boolean('semua_versi'), function ($q) {
                // Latest version of each flow.
                $q->whereIn('id', Definisi::query()->selectRaw('max(id)')->groupBy('kod_aliran'));
            })
            ->withCount('peringkat')->with('pencipta:id,name')
            ->orderBy('kod_aliran')->orderByDesc('no_versi')->get()
            ->map(fn (Definisi $d) => [
                'id' => $d->id,
                'kod_aliran' => $d->kod_aliran,
                'nama' => $d->nama,
                'modul' => $d->modul,
                'jenis_transaksi' => $d->jenis_transaksi,
                'no_versi' => $d->no_versi,
                'status' => $d->status,
                'templat' => $d->templat,
                'kaedah_cipta' => $d->kaedah_cipta,
                'bil_peringkat' => $d->getAttribute('peringkat_count'),
                'pencipta' => $d->pencipta?->name,
                'versi_aktif' => Definisi::query()->aktif()->where('kod_aliran', $d->kod_aliran)->value('no_versi'),
                'updated_at' => $d->updated_at->toIso8601String(),
            ]);

        return response()->json(['data' => $rows]);
    }

    public function show(Definisi $definisi): JsonResponse
    {
        $data = $this->definitions->export($definisi);
        $data['versi'] = Definisi::query()->where('kod_aliran', $definisi->kod_aliran)->orderByDesc('no_versi')
            ->get(['id', 'no_versi', 'status', 'tarikh_berkuat_kuasa', 'created_at'])
            ->map(fn (Definisi $d) => [
                'id' => $d->id, 'no_versi' => $d->no_versi, 'status' => $d->status,
                'tarikh_berkuat_kuasa' => $d->tarikh_berkuat_kuasa?->toIso8601String(),
                'instans_berjalan' => Instans::query()->withoutGlobalScope('access')->where('id_definisi', $d->id)->where('status', Instans::BERJALAN)->count(),
            ])->all();
        $data['log'] = DefinisiLog::query()->where('id_definisi', $definisi->id)->with('pengguna:id,name')->orderByDesc('id')->get()
            ->map(fn (DefinisiLog $l) => [
                'tindakan' => $l->tindakan, 'kaedah' => $l->kaedah, 'arahan' => $l->arahan,
                'pengguna' => $l->pengguna?->name, 'created_at' => $l->created_at->toIso8601String(),
            ])->all();
        $data['diminta_aktif_oleh'] = $definisi->diminta_aktif_oleh;
        $data['tarikh_berkuat_kuasa'] = $definisi->tarikh_berkuat_kuasa?->toIso8601String();

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $definition = $this->definitions->create($request->except(['kaedah', 'catatan']), $request->user(), $this->method($request), $request->input('catatan'));

        return response()->json(['message' => __('wfe::wfe.message.definisi_dicipta'), 'data' => ['id' => $definition->id]], 201);
    }

    public function update(Request $request, Definisi $definisi): JsonResponse
    {
        $this->definitions->update($definisi, $request->except(['kaedah', 'catatan']), $request->user(), $this->method($request), $request->input('catatan'));

        return response()->json(['message' => __('wfe::wfe.message.definisi_disimpan'), 'data' => $this->definitions->export($definisi->refresh())]);
    }

    public function newVersion(Request $request, Definisi $definisi): JsonResponse
    {
        $copy = $this->definitions->newVersion($definisi, $request->user());

        return response()->json(['message' => __('wfe::wfe.message.versi_baharu', ['versi' => $copy->no_versi]), 'data' => ['id' => $copy->id]], 201);
    }

    /** SRS-WFE-04: copy a template / definition into a new flow. */
    public function copy(Request $request, Definisi $definisi): JsonResponse
    {
        $data = $request->validate(['kod_aliran' => ['required', 'string'], 'nama' => ['required', 'string'], 'templat' => ['sometimes', 'boolean']]);
        $copy = $this->definitions->copyAs($definisi, $request->user(), $data['kod_aliran'], $data['nama'], (bool) ($data['templat'] ?? false));

        return response()->json(['message' => __('wfe::wfe.message.definisi_disalin'), 'data' => ['id' => $copy->id]], 201);
    }

    public function requestActivation(Request $request, Definisi $definisi): JsonResponse
    {
        $this->definitions->requestActivation($definisi, $request->user(), $request->input('catatan'));

        return response()->json(['message' => __('wfe::wfe.message.mohon_aktif')]);
    }

    /** Second administrator + MFA step-up (SRS-WFE-19: perubahan konfigurasi aliran kerja). */
    public function approveActivation(Request $request, Definisi $definisi): JsonResponse
    {
        if (! $this->stepUp->isFresh($this->stepUp->verifiedAt($request))) {
            throw new WfeException(__('wfe::wfe.error.mfa_diperlukan'), 'mfa_diperlukan', 403);
        }
        $this->definitions->approveActivation($definisi, $request->user(), $request->input('catatan'));

        return response()->json(['message' => __('wfe::wfe.message.diaktifkan')]);
    }

    public function rejectActivation(Request $request, Definisi $definisi): JsonResponse
    {
        $data = $request->validate(['catatan' => ['required', 'string', 'max:1000']]);
        $this->definitions->rejectActivation($definisi, $request->user(), $data['catatan']);

        return response()->json(['message' => __('wfe::wfe.message.aktif_ditolak')]);
    }

    /** Preview which stages a sample context would go through (no data written). */
    public function simulate(Request $request): JsonResponse
    {
        $data = $request->validate(['struktur' => ['required', 'array'], 'konteks' => ['nullable', 'array']]);
        $structure = $this->definitions->validate($data['struktur']);
        $context = $data['konteks'] ?? [];

        $path = [];
        $node = Peralihan::MULA;
        for ($guard = 0; $guard < 50 && $node !== Peralihan::TAMAT; $guard++) {
            $edges = collect($structure['peralihan'])->where('dari', $node)->sortBy('keutamaan')->values();
            $edge = $edges->first(fn ($e) => $e['syarat'] !== [] && $this->conditions->matchesAll($e['syarat'], $context, $e['logik']))
                ?? $edges->first(fn ($e) => $e['syarat'] === []);
            $node = $edge['ke'] ?? Peralihan::TAMAT;
            if ($node !== Peralihan::TAMAT) {
                $stage = collect($structure['peringkat'])->firstWhere('kod', $node);
                $path[] = ['kod' => $node, 'nama' => $stage['nama'] ?? $node];
            }
        }

        return response()->json(['data' => ['laluan' => $path]]);
    }

    private function method(Request $request): string
    {
        $method = (string) $request->input('kaedah', 'borang');
        validator(['kaedah' => $method], ['kaedah' => [Rule::in(['borang', 'editor_visual', 'api'])]])->validate();

        return $method;
    }
}
