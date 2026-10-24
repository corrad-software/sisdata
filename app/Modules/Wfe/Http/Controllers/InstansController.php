<?php

namespace App\Modules\Wfe\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Wfe\Http\Present;
use App\Modules\Wfe\Models\Definisi;
use App\Modules\Wfe\Models\Instans;
use App\Modules\Wfe\Models\PindahVersi;
use App\Modules\Wfe\Models\Tugasan;
use App\Modules\Wfe\Services\Assignees;
use App\Modules\Wfe\Services\Engine;
use App\Modules\Wfe\Services\StepUp;
use App\Modules\Wfe\Support\WfeException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Instances: the applicant's own requests (withdraw / resubmit, SRS-WFE-22),
 * monitoring list + drill-down (SRS-WFE-13/15), full history (SRS-WFE-16)
 * and version migration requests (SRS-WFE-12).
 */
class InstansController extends Controller
{
    public function __construct(private Present $present, private Engine $engine, private AccessControl $access, private Assignees $assignees, private StepUp $stepUp) {}

    /** "Permohonan saya". */
    public function mine(Request $request): JsonResponse
    {
        $page = Instans::query()->withoutGlobalScope('access')->with(['definisi', 'peringkat', 'pemohon'])
            ->where('id_pemohon', $request->user()->id)
            ->when($request->query('rekod'), fn ($q, $r) => $q->where('id_rekod', $r))
            ->orderByDesc('tarikh_mula')->paginate(25);

        return response()->json([
            'data' => collect($page->items())->map(fn (Instans $i) => $this->present->instance($i))->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /** Monitoring list — district-scoped through RBC (Instans uses ScopedByAccess). */
    public function index(Request $request): JsonResponse
    {
        $page = Instans::query()->with(['definisi', 'peringkat', 'pemohon'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('kod_aliran'), fn ($q, $k) => $q->where('kod_aliran', $k))
            ->when($request->query('peringkat'), fn ($q, $p) => $q->whereHas('peringkat', fn ($s) => $s->where('kod', $p)))
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w->where('tajuk', 'ilike', '%'.$term.'%')->orWhere('id_rekod', $term)))
            ->orderByDesc('tarikh_mula')->paginate(min(100, max(5, (int) $request->query('per_page', 25))));

        return response()->json([
            'data' => collect($page->items())->map(fn (Instans $i) => $this->present->instance($i))->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function show(Request $request, string $instans): JsonResponse
    {
        $instance = $this->visible($request, $instans);
        $instance->load(['definisi', 'peringkat', 'pemohon']);
        $data = $this->present->instance($instance, true);
        $data['boleh_tarik_balik'] = $instance->id_pemohon === $request->user()->id && $instance->status === Instans::BERJALAN;
        $data['boleh_hantar_semula'] = $data['boleh_tarik_balik'] && $instance->keadaan === Instans::MENUNGGU_PEMOHON;
        $data['versi_lain'] = Definisi::query()->where('kod_aliran', $instance->kod_aliran)->where('status', Definisi::AKTIF)
            ->where('id', '!=', $instance->id_definisi)->get(['id', 'no_versi'])->toArray();
        $data['pindah_versi'] = PindahVersi::query()->where('id_instans', $instance->id)->orderByDesc('id')->get()
            ->map(fn (PindahVersi $p) => ['id' => $p->id, 'status' => $p->status, 'sebab' => $p->sebab, 'id_definisi_baru' => $p->id_definisi_baru, 'created_at' => $p->created_at->toIso8601String()])->all();

        return response()->json(['data' => $data]);
    }

    public function withdraw(Request $request, string $instans): JsonResponse
    {
        $data = $request->validate(['justifikasi' => ['required', 'string', 'max:2000']]);
        $this->engine->withdraw($instans, $request->user(), $data['justifikasi']);

        return response()->json(['message' => __('wfe::wfe.message.ditarik_balik')]);
    }

    public function resubmit(Request $request, string $instans): JsonResponse
    {
        $data = $request->validate(['catatan' => ['nullable', 'string', 'max:2000']]);
        $this->engine->resubmit($instans, $request->user(), $data['catatan'] ?? null);

        return response()->json(['message' => __('wfe::wfe.message.dihantar_semula')]);
    }

    public function requestMigration(Request $request, string $instans): JsonResponse
    {
        $data = $request->validate([
            'id_definisi' => ['required', 'integer', 'exists:wf_definisi,id'],
            'sebab' => ['required', 'string', 'max:1000'],
        ]);
        $instance = Instans::query()->findOrFail($instans);
        $target = Definisi::query()->findOrFail($data['id_definisi']);
        $migration = $this->engine->requestMigration($instance, $target, $request->user(), $data['sebab']);

        return response()->json(['message' => __('wfe::wfe.message.pindah_dimohon'), 'data' => ['id' => $migration->id]], 201);
    }

    public function migrations(): JsonResponse
    {
        $rows = PindahVersi::query()->where('status', 'menunggu')->with(['instans', 'definisiBaru'])->orderBy('id')->get()
            ->map(fn (PindahVersi $p) => [
                'id' => $p->id,
                'id_instans' => $p->id_instans,
                'tajuk' => $p->instans->tajuk,
                'kod_aliran' => $p->instans->kod_aliran,
                'dari_versi' => $p->instans->no_versi_definisi,
                'ke_versi' => $p->definisiBaru->no_versi,
                'sebab' => $p->sebab,
                'diminta_oleh' => $p->diminta_oleh,
                'created_at' => $p->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $rows]);
    }

    /** Second administrator decides a migration; approving needs a fresh MFA step-up (SRS-WFE-19). */
    public function decideMigration(Request $request, PindahVersi $pindahVersi): JsonResponse
    {
        $data = $request->validate(['lulus' => ['required', 'boolean'], 'catatan' => ['nullable', 'string', 'max:1000']]);
        if ($data['lulus'] && ! $this->stepUp->isFresh($this->stepUp->verifiedAt($request))) {
            throw new WfeException(__('wfe::wfe.error.mfa_diperlukan'), 'mfa_diperlukan', 403);
        }
        $this->engine->decideMigration($pindahVersi, $request->user(), (bool) $data['lulus'], $data['catatan'] ?? null);

        return response()->json(['message' => __($data['lulus'] ? 'wfe::wfe.message.pindah_lulus' : 'wfe::wfe.message.pindah_tolak')]);
    }

    /**
     * Instance detail is visible to: monitors (district-scoped), the
     * applicant, and anyone who holds or held a task in it.
     */
    private function visible(Request $request, string $id): Instans
    {
        $user = $request->user();
        if ($this->access->allows($user, 'wfe.pemantauan.lihat') && ($found = Instans::query()->find($id))) {
            return $found;
        }

        $instance = Instans::query()->withoutGlobalScope('access')->findOrFail($id);
        if ($instance->id_pemohon === $user->id) {
            return $instance;
        }
        $keys = array_keys($this->assignees->actingKeys($user));
        $involved = Tugasan::query()->where('id_instans', $instance->id)
            ->where(fn ($q) => $q->whereIn('penerima_kunci', $keys)->orWhere('diselesaikan_oleh', $user->id))->exists();
        if ($involved) {
            return $instance;
        }

        throw new AuthorizationException(__('wfe::wfe.error.tiada_kebenaran'));
    }
}
