<?php

namespace App\Modules\Dsh\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Dsh\Models\DrafTransaksi;
use App\Modules\Dsh\Models\Langganan;
use App\Modules\Dsh\Models\PaparanKegemaran;
use App\Modules\Dsh\Models\Snapshot;
use App\Modules\Dsh\Services\Aras;
use App\Modules\Dsh\Services\Bantuan;
use App\Modules\Dsh\Services\Eksport;
use App\Modules\Dsh\Services\InboxTugasan;
use App\Modules\Dsh\Services\Penapis;
use App\Modules\Dsh\Services\Ringkasan;
use App\Modules\Dsh\Services\WidgetKatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * The user's own dashboard state: personal layout (SRS-DSH-16), saved views
 * (SRS-DSH-14), transaction drafts (SRS-DSH-22), task inbox (SRS-DSH-23),
 * digest subscriptions and snapshots, and online help (SRS-DSH-25). Every
 * record is owner-only — another user's id answers 404.
 */
class PeribadiController extends Controller
{
    public function __construct(private Aras $aras, private WidgetKatalog $widgets, private AuditLogger $audit) {}

    private function aras(Request $request, User $user, string $field = 'aras'): string
    {
        $aras = (string) $request->input($field, $this->aras->default($user) ?? '');
        abort_unless(in_array($aras, Aras::ALL, true), 422, __('dsh::dsh.aras_tidak_sah'));
        abort_unless($this->aras->allows($user, $aras), 403, __('dsh::dsh.aras_tiada_kebenaran'));

        return $aras;
    }

    // ---- Layout ------------------------------------------------------------

    public function layout(Request $request): JsonResponse
    {
        $user = $request->user();
        $aras = $this->aras($request, $user);

        return response()->json(['data' => $this->widgets->layout($user, $aras), 'aras' => $aras]);
    }

    public function saveLayout(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'aras' => ['required', 'string'],
            'widget' => ['required', 'array', 'max:100'],
            'widget.*.kod' => ['required', 'string', 'max:80'],
            'widget.*.saiz' => ['nullable', 'integer', 'min:1', 'max:4'],
            'widget.*.tersembunyi' => ['nullable', 'boolean'],
            'widget.*.jenis_visual' => ['nullable', 'string', Rule::in(WidgetKatalog::VISUALS)],
        ]);
        $aras = $this->aras($request, $user);
        $this->widgets->save($user, $aras, $data['widget']);

        return response()->json(['message' => __('dsh::dsh.susun_atur_disimpan'), 'data' => $this->widgets->layout($user, $aras)]);
    }

    public function resetLayout(Request $request): JsonResponse
    {
        $user = $request->user();
        $aras = $this->aras($request, $user);
        $this->widgets->reset($user, $aras);

        return response()->json(['message' => __('dsh::dsh.susun_atur_set_semula'), 'data' => $this->widgets->layout($user, $aras)]);
    }

    // ---- Saved views -------------------------------------------------------

    public function views(Request $request): JsonResponse
    {
        return response()->json(['data' => PaparanKegemaran::query()->where('id_pengguna', $request->user()->id)->orderByDesc('lalai')->orderBy('nama_paparan')->get()]);
    }

    public function saveView(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'nama_paparan' => ['required', 'string', 'max:120'],
            'aras' => ['nullable', 'string', Rule::in(Aras::ALL)],
            'parameter_penapis' => ['nullable', 'array'],
            'lalai' => ['nullable', 'boolean'],
        ]);
        $filters = Penapis::from((array) ($data['parameter_penapis'] ?? []));
        if (! empty($data['lalai'])) {
            PaparanKegemaran::query()->where('id_pengguna', $user->id)->where('lalai', true)->get()->each(fn ($v) => $v->update(['lalai' => false]));
        }
        $view = PaparanKegemaran::query()->updateOrCreate(
            ['id_pengguna' => $user->id, 'nama_paparan' => $data['nama_paparan']],
            ['aras' => $data['aras'] ?? null, 'parameter_penapis' => $filters, 'lalai' => (bool) ($data['lalai'] ?? false)],
        );

        return response()->json(['message' => __('dsh::dsh.paparan_disimpan'), 'data' => $view], 201);
    }

    public function deleteView(Request $request, int $id): JsonResponse
    {
        PaparanKegemaran::query()->where('id_pengguna', $request->user()->id)->findOrFail($id)->delete();

        return response()->json(['message' => __('dsh::dsh.paparan_dipadam')]);
    }

    // ---- Drafts (SRS-DSH-22) ----------------------------------------------

    public function drafts(Request $request): JsonResponse
    {
        $request->validate(['modul' => ['nullable', 'string', 'max:10']]);

        return response()->json(['data' => DrafTransaksi::query()->where('id_pengguna', $request->user()->id)
            ->when($request->filled('modul'), fn ($q) => $q->where('modul', $request->query('modul')))
            ->orderByDesc('tarikh_simpan')->get(['id', 'modul', 'jenis', 'tajuk', 'pautan', 'tarikh_simpan', 'created_at'])]);
    }

    public function draft(Request $request, string $id): JsonResponse
    {
        return response()->json(['data' => $this->ownDraft($request, $id)]);
    }

    public function saveDraft(Request $request, ?string $id = null): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'modul' => [$id ? 'sometimes' : 'required', 'string', 'regex:/^[a-z]{2,10}$/'],
            'jenis' => [$id ? 'sometimes' : 'required', 'string', 'max:60'],
            'tajuk' => [$id ? 'sometimes' : 'required', 'string', 'max:200'],
            'pautan' => ['nullable', 'string', 'max:255', 'regex:/^\/admin\//'],
            'muatan_json' => ['required', 'array'],
        ]);
        abort_if(strlen((string) json_encode($data['muatan_json'])) > 262144, 422, __('dsh::dsh.draf_terlalu_besar'));

        $draft = $id ? $this->ownDraft($request, $id) : new DrafTransaksi(['id_pengguna' => $user->id]);
        $draft->fill(array_merge($data, ['tarikh_simpan' => Carbon::now()]))->save();

        return response()->json(['message' => __('dsh::dsh.draf_disimpan_ok'), 'data' => $draft->fresh()], $id ? 200 : 201);
    }

    public function deleteDraft(Request $request, string $id): JsonResponse
    {
        $this->ownDraft($request, $id)->delete();

        return response()->json(['message' => __('dsh::dsh.draf_dibuang')]);
    }

    private function ownDraft(Request $request, string $id): DrafTransaksi
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/i', $id) === 1, 404);

        return DrafTransaksi::query()->where('id_pengguna', $request->user()->id)->findOrFail($id);
    }

    // ---- Inbox (SRS-DSH-23) -----------------------------------------------

    public function inbox(Request $request, InboxTugasan $inbox): JsonResponse
    {
        return response()->json(['data' => $inbox->for($request->user())]);
    }

    // ---- Digest subscriptions + snapshots -----------------------------------

    public function subscriptions(Request $request): JsonResponse
    {
        return response()->json(['data' => Langganan::query()->where('id_pengguna', $request->user()->id)->orderBy('aras')->get()]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'aras' => ['required', 'string'],
            'kekerapan' => ['required', Rule::in(array_keys(Ringkasan::HARI))],
            'penapis' => ['nullable', 'array'],
            'aktif' => ['nullable', 'boolean'],
        ]);
        $aras = $this->aras($request, $user);
        $sub = Langganan::query()->updateOrCreate(
            ['id_pengguna' => $user->id, 'aras' => $aras],
            ['kekerapan' => $data['kekerapan'], 'penapis' => Penapis::from((array) ($data['penapis'] ?? [])), 'aktif' => (bool) ($data['aktif'] ?? true)],
        );

        return response()->json(['message' => __('dsh::dsh.langganan_disimpan'), 'data' => $sub]);
    }

    public function unsubscribe(Request $request, int $id): JsonResponse
    {
        Langganan::query()->where('id_pengguna', $request->user()->id)->findOrFail($id)->delete();

        return response()->json(['message' => __('dsh::dsh.langganan_dipadam')]);
    }

    public function snapshots(Request $request): JsonResponse
    {
        return response()->json(['data' => Snapshot::query()->where('id_pengguna', $request->user()->id)->orderByDesc('dijana_pada')->limit(50)
            ->get(['id', 'aras', 'penapis', 'sumber', 'dijana_pada'])]);
    }

    public function takeSnapshot(Request $request, Ringkasan $ringkasan): JsonResponse
    {
        $user = $request->user();
        $aras = $this->aras($request, $user);
        $snap = $ringkasan->snapshot($user, $aras, Penapis::from((array) $request->input('penapis', [])));

        return response()->json(['message' => __('dsh::dsh.snapshot_dijana'), 'data' => $snap], 201);
    }

    public function snapshot(Request $request, int $id): JsonResponse
    {
        return response()->json(['data' => Snapshot::query()->where('id_pengguna', $request->user()->id)->findOrFail($id)]);
    }

    public function exportSnapshot(Request $request, int $id, Eksport $eksport): Response
    {
        $request->validate(['format' => ['required', 'in:pdf,xlsx']]);
        $user = $request->user();
        $snap = Snapshot::query()->where('id_pengguna', $user->id)->findOrFail($id);
        $this->audit->record('eksport', 'dsh', 'dsh_snapshot', $snap->id, meta: ['format' => $request->query('format'), 'penapis' => $snap->penapis]);

        return $eksport->download((string) $request->query('format'), $user, $snap->aras, $snap->nilai, $snap->penapis, $snap->dijana_pada->toIso8601String());
    }

    // ---- Help (SRS-DSH-25) -------------------------------------------------

    public function help(Request $request, Bantuan $bantuan): JsonResponse
    {
        $request->validate(['laluan' => ['nullable', 'string', 'max:255']]);

        return response()->json(['data' => $bantuan->for($request->user(), $request->query('laluan'))]);
    }
}
