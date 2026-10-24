<?php

namespace App\Modules\Ntf\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Ntf\Models\EskalasiLog;
use App\Modules\Ntf\Models\Jenis;
use App\Modules\Ntf\Models\Penghantaran;
use App\Modules\Ntf\Services\NotificationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Monitoring dashboard (SRS-NTF-23) and the delivery log auditors review
 * (SRS-NTF-15/16, acceptance criterion 5).
 */
class MonitoringController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $days = max(1, min(90, (int) $request->query('hari', 7)));
        $since = now()->subDays($days);

        $queueSize = null;
        try {
            $queueSize = Queue::size();
        } catch (Throwable) {
            // Redis down: the DB counts below still tell the story.
        }

        $statusCounts = Penghantaran::query()
            ->whereIn('status', [Penghantaran::BERATUR, Penghantaran::DITAHAN, Penghantaran::GAGAL])
            ->select('status', DB::raw('count(*) as jumlah'))->groupBy('status')->pluck('jumlah', 'status');

        $perChannel = Penghantaran::query()->where('created_at', '>=', $since)
            ->select('saluran', 'status', DB::raw('count(*) as jumlah'))
            ->groupBy('saluran', 'status')->get()
            ->groupBy('saluran');

        $channels = collect(Jenis::SALURAN)->map(function (string $saluran) use ($perChannel) {
            $rows = $perChannel->get($saluran, collect())->pluck('jumlah', 'status');
            $ok = collect(Penghantaran::BERJAYA)->sum(fn ($s) => (int) ($rows[$s] ?? 0));
            $failed = (int) ($rows[Penghantaran::GAGAL_MUKTAMAD] ?? 0);
            $total = (int) $rows->sum();

            return [
                'saluran' => $saluran,
                'label' => __('ntf::ntf.saluran.'.$saluran),
                'jumlah' => $total,
                'berjaya' => $ok,
                'gagal_muktamad' => $failed,
                'dalam_proses' => $total - $ok - $failed,
                'kadar_kejayaan' => $ok + $failed > 0 ? round($ok * 100 / ($ok + $failed), 1) : null,
            ];
        })->values();

        $failures = Penghantaran::query()->with(['mesej.jenis', 'penerima'])
            ->whereIn('status', [Penghantaran::GAGAL, Penghantaran::GAGAL_MUKTAMAD])
            ->orderByDesc('updated_at')->limit(20)->get()
            ->map(fn (Penghantaran $p) => $this->present($p));

        return response()->json(['data' => [
            'tempoh_hari' => $days,
            'baris_gilir' => [
                'beratur' => (int) ($statusCounts[Penghantaran::BERATUR] ?? 0),
                'ditahan_ringkasan' => (int) ($statusCounts[Penghantaran::DITAHAN] ?? 0),
                'menunggu_cuba_semula' => (int) ($statusCounts[Penghantaran::GAGAL] ?? 0),
                'redis' => $queueSize,
            ],
            'saluran' => $channels,
            'kegagalan_terkini' => $failures,
            'eskalasi' => EskalasiLog::query()->where('created_at', '>=', $since)->count(),
        ]]);
    }

    public function deliveries(Request $request): JsonResponse
    {
        $f = $request->validate([
            'jenis' => ['nullable', 'string', 'max:100'],
            'saluran' => ['nullable', Rule::in(Jenis::SALURAN)],
            'status' => ['nullable', 'string', 'max:20'],
            'penerima_id' => ['nullable', 'integer'],
            'dari' => ['nullable', 'date'],
            'hingga' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = Penghantaran::query()->with(['mesej.jenis', 'penerima'])
            ->when($f['jenis'] ?? null, fn ($q, $v) => $q->whereHas('mesej.jenis', fn ($j) => $j->where('kod', $v)))
            ->when($f['saluran'] ?? null, fn ($q, $v) => $q->where('saluran', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['penerima_id'] ?? null, fn ($q, $v) => $q->where('penerima_id', $v))
            ->when($f['dari'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($f['hingga'] ?? null, fn ($q, $v) => $q->where('created_at', '<', date('Y-m-d', strtotime($v.' +1 day'))))
            ->orderByDesc('id')
            ->paginate((int) ($f['per_page'] ?? 25));

        return response()->json([
            'data' => collect($page->items())->map(fn (Penghantaran $p) => $this->present($p)),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
        ]);
    }

    public function retry(Penghantaran $penghantaran, NotificationEngine $engine, AuditLogger $audit): JsonResponse
    {
        abort_unless(in_array($penghantaran->status, [Penghantaran::GAGAL, Penghantaran::GAGAL_MUKTAMAD], true), 422, __('ntf::ntf.tidak_boleh_cuba_semula'));

        $penghantaran->update(['status' => Penghantaran::BERATUR, 'tarikh_beratur' => now(), 'tarikh_gagal' => null]);
        $audit->record('cuba_semula', 'ntf', 'ntf_penghantaran', $penghantaran->id);
        $engine->queue($penghantaran->id);

        return response()->json(['message' => __('ntf::ntf.cuba_semula')]);
    }

    /** @return array<string, mixed> */
    private function present(Penghantaran $p): array
    {
        return [
            'id' => $p->id,
            'mesej_id' => $p->mesej_id,
            'jenis' => ['kod' => $p->mesej->jenis->kod, 'nama' => $p->mesej->jenis->nama],
            'subjek' => $p->mesej->subjek,
            'keutamaan' => $p->mesej->keutamaan,
            'penerima' => $p->penerima ? ['id' => $p->penerima->id, 'nama' => $p->penerima->name] : null,
            'saluran' => $p->saluran,
            'status' => $p->status,
            'ringkasan' => $p->ringkasan,
            'bilangan_cubaan' => $p->bilangan_cubaan,
            'kod_ralat' => $p->kod_ralat,
            'mesej_ralat' => $p->mesej_ralat,
            'tarikh_beratur' => $p->tarikh_beratur?->toIso8601String(),
            'tarikh_hantar' => $p->tarikh_hantar?->toIso8601String(),
            'tarikh_terima' => $p->tarikh_terima?->toIso8601String(),
            'tarikh_baca' => $p->tarikh_baca?->toIso8601String(),
            'tarikh_gagal' => $p->tarikh_gagal?->toIso8601String(),
        ];
    }
}
