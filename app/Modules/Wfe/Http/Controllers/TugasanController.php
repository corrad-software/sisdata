<?php

namespace App\Modules\Wfe\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Wfe\Http\Present;
use App\Modules\Wfe\Models\Tugasan;
use App\Modules\Wfe\Services\Assignees;
use App\Modules\Wfe\Services\Engine;
use App\Modules\Wfe\Services\StepUp;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * SRS-WFE-14: per-user task board, with quick approve / reject / return
 * (and defer). Users see only tasks assigned to them, their role, position
 * or group, or to someone they act for (security control: capaian papan
 * tugasan dihadkan).
 */
class TugasanController extends Controller
{
    public function __construct(private Assignees $assignees, private Present $present, private Engine $engine, private StepUp $stepUp) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $keys = $this->assignees->actingKeys($user);
        $done = $request->query('status') === 'selesai';

        $query = Tugasan::query()->with(['instans.pemohon', 'peringkat', 'penyelesai:id,name']);
        if ($done) {
            $query->where('diselesaikan_oleh', $user->id)->where('status', Tugasan::SELESAI)->orderByDesc('diselesaikan_pada');
        } else {
            $query->whereIn('penerima_kunci', array_keys($keys))
                ->whereIn('status', [Tugasan::MENUNGGU, Tugasan::DITANGGUH])
                ->orderByRaw('tarikh_akhir_sla is null, tarikh_akhir_sla asc')->orderBy('id');
        }

        $page = $query->paginate(min(100, max(5, (int) $request->query('per_page', 25))));

        return response()->json([
            'data' => collect($page->items())->map(fn (Tugasan $t) => $this->present->task($t, $user, $keys))->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function show(Request $request, Tugasan $tugasan): JsonResponse
    {
        $user = $request->user();
        $keys = $this->assignees->actingKeys($user);
        $this->ensureVisible($tugasan, $user->id, $keys);
        $tugasan->load(['instans.pemohon', 'instans.definisi', 'peringkat', 'penyelesai:id,name']);
        $mfaAt = $this->stepUp->verifiedAt($request);

        return response()->json(['data' => [
            ...$this->present->task($tugasan, $user, $keys),
            'butiran_instans' => $this->present->instance($tugasan->instans, true),
            'mfa' => [
                'sah' => $this->stepUp->isFresh($mfaAt),
                'pada' => $mfaAt?->toIso8601String(),
                'tempoh_minit' => $this->stepUp->windowMinutes(),
                'sso' => $user->oidc_sub !== null,
            ],
        ]]);
    }

    /** Approve / reject / return / defer — all rules enforced in the engine. */
    public function act(Request $request, Tugasan $tugasan): JsonResponse
    {
        $data = $request->validate([
            'tindakan' => ['required', Rule::in(Engine::ACTIONS)],
            'justifikasi' => ['nullable', 'string', 'max:2000'],
            'tangguh_hingga' => ['nullable', 'date'],
        ]);

        $task = $this->engine->decide($tugasan, $request->user(), $data['tindakan'], $data['justifikasi'] ?? null, [
            'ip' => $request->ip(),
            'mfa_at' => $this->stepUp->verifiedAt($request),
            'tangguh_hingga' => isset($data['tangguh_hingga']) ? Carbon::parse($data['tangguh_hingga']) : null,
        ]);
        $task->load(['instans.pemohon', 'peringkat', 'penyelesai:id,name']);

        return response()->json([
            'message' => __('wfe::wfe.message.tindakan_'.$data['tindakan']),
            'data' => $this->present->task($task, $request->user(), $this->assignees->actingKeys($request->user())),
        ]);
    }

    /** SRS-WFE-20: mark a follow-up task done. */
    public function complete(Request $request, Tugasan $tugasan): JsonResponse
    {
        $data = $request->validate(['catatan' => ['nullable', 'string', 'max:2000']]);
        $this->engine->completeFollowUp($tugasan, $request->user(), $data['catatan'] ?? null);

        return response()->json(['message' => __('wfe::wfe.message.susulan_selesai')]);
    }

    /** @param array<string, int|null> $keys */
    private function ensureVisible(Tugasan $task, int $userId, array $keys): void
    {
        if (array_key_exists($task->penerima_kunci, $keys) || $task->diselesaikan_oleh === $userId) {
            return;
        }

        throw new AuthorizationException(__('wfe::wfe.error.bukan_penerima'));
    }
}
