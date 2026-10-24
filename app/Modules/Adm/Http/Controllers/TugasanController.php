<?php

namespace App\Modules\Adm\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Adm\Jobs\JalankanTugasan;
use App\Modules\Adm\Models\Tugasan;
use App\Modules\Adm\Models\TugasanLarian;
use App\Modules\Adm\Services\Tugasan\ArahanDibenarkan;
use App\Modules\Adm\Services\Tugasan\PelaksanaTugasan;
use App\Modules\Adm\Services\Tugasan\PenjadualTugasan;
use Cron\CronExpression;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** SRS-ADM-06..10: job scheduler admin API. */
class TugasanController extends Controller
{
    public function __construct(private ArahanDibenarkan $allowed, private PenjadualTugasan $scheduler) {}

    public function index(): JsonResponse
    {
        $stats = TugasanLarian::query()->toBase()
            ->where('created_at', '>=', now()->subDays(30))
            ->select('id_tugasan', DB::raw('count(*) as jumlah'), DB::raw("sum(case when status = 'berjaya' then 1 else 0 end) as berjaya"))
            ->groupBy('id_tugasan')->get()->keyBy('id_tugasan');

        $jobs = Tugasan::query()->orderBy('kategori')->orderBy('nama')->get()->map(fn (Tugasan $t) => [
            ...$t->toArray(),
            'seterusnya_pada' => $this->safeNext($t),
            'kadar_kejayaan_30h' => isset($stats[$t->id]) && $stats[$t->id]->jumlah > 0
                ? round($stats[$t->id]->berjaya / $stats[$t->id]->jumlah * 100, 1) : null,
        ]);

        return response()->json(['data' => $jobs, 'kategori' => Tugasan::KATEGORI, 'arahan' => $this->allowed->list()]);
    }

    public function show(Tugasan $tugasan): JsonResponse
    {
        return response()->json(['data' => [...$tugasan->toArray(), 'seterusnya_pada' => $this->safeNext($tugasan)]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $tugasan = Tugasan::query()->create([...$data, 'sumber' => 'adm', 'dikemas_kini_oleh' => $request->user()->id]);

        return response()->json(['message' => __('adm::adm.saved'), 'data' => $tugasan], 201);
    }

    public function update(Request $request, Tugasan $tugasan): JsonResponse
    {
        if ($tugasan->sumber === 'sistem') {
            // Adopted framework jobs: the schedule belongs to the module's code; only on/off, retries, tolerance here.
            $data = $request->validate([
                'aktif' => ['sometimes', 'boolean'],
                'toleransi_lewat_minit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10080'],
                'justifikasi' => ['nullable', 'string', 'max:1000'],
            ]);
        } else {
            $data = $this->validated($request, $tugasan);
        }

        $tugasan->update([...$data, 'dikemas_kini_oleh' => $request->user()->id]);

        return response()->json(['message' => __('adm::adm.saved'), 'data' => $tugasan]);
    }

    public function destroy(Tugasan $tugasan): JsonResponse
    {
        if ($tugasan->larian()->exists()) {
            // Keep run history: deactivate instead of deleting.
            $tugasan->update(['aktif' => false]);

            return response()->json(['message' => __('adm::adm.tugasan.deactivated_has_history'), 'data' => $tugasan]);
        }

        $tugasan->delete();

        return response()->json(null, 204);
    }

    /** Run now — queued (SRS-ADM-06). */
    public function run(Request $request, Tugasan $tugasan, PelaksanaTugasan $runner): JsonResponse
    {
        $run = $runner->queue($tugasan, $request->user()->id);
        JalankanTugasan::dispatch($tugasan->id, $run->id, $request->user()->id);

        return response()->json(['message' => __('adm::adm.tugasan.queued'), 'data' => $run->fresh()], 202);
    }

    public function runs(Request $request, Tugasan $tugasan): JsonResponse
    {
        $request->validate(['status' => ['nullable', 'in:beratur,berjalan,berjaya,gagal,dilangkau']]);

        return response()->json(
            $tugasan->larian()
                ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
                ->latest('id')->paginate(25)
        );
    }

    /** Jobs other modules registered in Laravel's scheduler (read-only, adoptable). */
    public function system(Schedule $schedule): JsonResponse
    {
        return response()->json(['data' => $this->scheduler->foreign($schedule)]);
    }

    public function adopt(Request $request, Schedule $schedule): JsonResponse
    {
        $data = $request->validate([
            'kunci' => ['required', 'string', 'max:200'],
            'nama' => ['nullable', 'string', 'max:200'],
            'kategori' => ['required', Rule::in(Tugasan::KATEGORI)],
        ], [], (array) __('adm::adm.attributes'));

        $event = $this->scheduler->find($schedule, $data['kunci']) ?? abort(404, __('adm::adm.tugasan.system_not_found'));
        $command = PenjadualTugasan::commandLine($event);

        $tugasan = Tugasan::query()->firstOrCreate(['kunci_sistem' => $data['kunci']], [
            'kod' => 'sistem_'.substr(sha1($data['kunci']), 0, 12),
            'nama' => $data['nama'] ?? ($event->description ?: $command),
            'kategori' => $data['kategori'],
            'arahan' => $command,
            'jenis_jadual' => 'cron',
            'ungkapan_cron' => $event->expression,
            'aktif' => true,
            'sumber' => 'sistem',
            'dikemas_kini_oleh' => $request->user()->id,
        ]);

        return response()->json(['message' => __('adm::adm.saved'), 'data' => $tugasan], 201);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Tugasan $existing = null): array
    {
        $req = $existing ? 'sometimes' : 'required';
        $data = $request->validate([
            'kod' => [$req, 'string', 'max:80', 'regex:/^[a-z0-9_]+$/', Rule::unique('adm_tugasan_berjadual', 'kod')->ignore($existing?->id)],
            'nama' => [$req, 'string', 'max:200'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
            'kategori' => [$req, Rule::in(Tugasan::KATEGORI)],
            'arahan' => [$req, 'string', 'max:200'],
            'argumen' => ['nullable', 'array'],
            'jenis_jadual' => [$req, 'in:cron,selang'],
            'ungkapan_cron' => ['nullable', 'required_if:jenis_jadual,cron', 'string', 'max:100'],
            'selang_minit' => ['nullable', 'required_if:jenis_jadual,selang', 'integer', 'min:1', 'max:10080'],
            'aktif' => ['sometimes', 'boolean'],
            'cubaan_semula' => ['sometimes', 'integer', 'min:0', 'max:5'],
            'tempoh_tamat_masa_saat' => ['sometimes', 'integer', 'min:60', 'max:86400'],
            'toleransi_lewat_minit' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'justifikasi' => ['nullable', 'string', 'max:1000'],
        ], [], (array) __('adm::adm.attributes'));

        if (isset($data['arahan']) && ! $this->allowed->allowed($data['arahan'])) {
            throw ValidationException::withMessages(['arahan' => __('adm::adm.tugasan.command_not_allowed', ['arahan' => $data['arahan']])]);
        }

        $type = $data['jenis_jadual'] ?? $existing?->jenis_jadual;
        $cron = $data['ungkapan_cron'] ?? $existing?->ungkapan_cron;
        if ($type === 'cron' && ! CronExpression::isValidExpression((string) $cron)) {
            throw ValidationException::withMessages(['ungkapan_cron' => __('adm::adm.tugasan.invalid_cron')]);
        }

        return $data;
    }

    private function safeNext(Tugasan $t): ?string
    {
        try {
            return $t->nextDue()?->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }
}
