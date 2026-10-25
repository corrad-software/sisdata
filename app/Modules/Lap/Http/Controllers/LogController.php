<?php

namespace App\Modules\Lap\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Lap\Models\LogPenjanaan;
use App\Modules\Lap\Services\Eksport;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generation log (SRS-LAP-07/10): a user sees their own runs and downloads
 * their background / scheduled files; Audit (lap.log.lihat) sees everyone's
 * log entries — but never downloads someone else's file.
 */
class LogController extends Controller
{
    public function __construct(private AccessControl $access) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $all = $request->boolean('semua') && $this->access->allows($user, 'lap.log.lihat');
        $q = LogPenjanaan::query()->leftJoin('users', 'users.id', '=', 'lap_log_penjanaan.id_pengguna')
            ->select('lap_log_penjanaan.*', 'users.name as nama_pengguna')
            ->when(! $all, fn ($w) => $w->where('lap_log_penjanaan.id_pengguna', $user->id))
            ->when($request->query('format'), fn ($w, $f) => $w->where('format_eksport', $f))
            ->when($request->query('mod'), fn ($w, $m) => $w->where('mod', $m))
            ->when($request->query('fail'), fn ($w) => $w->whereNotNull('laluan_fail'))
            ->orderByDesc('cap_masa');
        $page = $q->paginate(min(100, max(10, (int) $request->query('per_halaman', 25))));

        return response()->json([
            'data' => collect($page->items())->map(fn (LogPenjanaan $l) => $this->present($l, $user))->values(),
            'meta' => ['jumlah' => $page->total(), 'halaman' => $page->currentPage(), 'halaman_akhir' => $page->lastPage(), 'semua' => $all],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $l = LogPenjanaan::query()->findOrFail($id);
        abort_unless($l->id_pengguna === $user->id || $this->access->allows($user, 'lap.log.lihat'), 404);
        $data = $this->present($l, $user);
        $data['parameter_digunakan'] = $l->parameter_digunakan;

        return response()->json(['data' => $data]);
    }

    public function download(Request $request, string $id, AuditLogger $audit): Response
    {
        /** @var User $user */
        $user = $request->user();
        $l = LogPenjanaan::query()->findOrFail($id);
        // Files were generated under the owner's scope: only the owner may download.
        abort_unless($l->id_pengguna === $user->id, 404);
        $disk = Storage::disk((string) config('lap.disk', 'local'));
        abort_unless($l->status === 'berjaya' && $l->laluan_fail && $disk->exists($l->laluan_fail), 404, __('lap::lap.ralat.fail_tiada'));
        $audit->record('muat_turun', 'lap', 'lap_log_penjanaan', $l->id, meta: ['format' => $l->format_eksport, 'laporan' => $l->tajuk, 'bilangan_rekod' => $l->bilangan_rekod]);
        $name = 'laporan_'.substr((string) preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($l->tajuk)), 0, 60).'_'.$l->cap_masa->format('Ymd_His').'.'.$l->format_eksport;

        return new Response((string) $disk->get($l->laluan_fail), 200, [
            'Content-Type' => Eksport::MIME[$l->format_eksport] ?? 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** @return array<string, mixed> */
    private function present(LogPenjanaan $l, User $user): array
    {
        return [
            'id' => $l->id, 'tajuk' => $l->tajuk, 'kod_laporan' => $l->kod_laporan, 'id_definisi' => $l->id_definisi, 'id_jadual' => $l->id_jadual,
            'set_data' => $l->set_data, 'format' => $l->format_eksport, 'mod' => $l->mod, 'mod_nama' => __('lap::lap.mod.'.$l->mod), 'status' => $l->status,
            'status_nama' => __('lap::lap.status.'.$l->status), 'bilangan_rekod' => $l->bilangan_rekod, 'cap_masa' => $l->cap_masa->toIso8601String(),
            'tamat_pada' => $l->tamat_pada?->toIso8601String(), 'saiz_bait' => $l->saiz_bait, 'checksum' => $l->checksum, 'klasifikasi' => $l->klasifikasi,
            'pengguna' => $l->getAttribute('nama_pengguna'), 'milik_saya' => $l->id_pengguna === $user->id,
            'boleh_muat_turun' => $l->id_pengguna === $user->id && $l->status === 'berjaya' && $l->laluan_fail !== null, 'ralat' => $l->ralat,
        ];
    }
}
