<?php

namespace App\Modules\Aud\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Aud\Http\Requests\AuditSearchRequest;
use App\Modules\Aud\Services\AuditSearch;
use App\Modules\Aud\Services\TrailAuditLogger;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SRS-AUD-10/11/14/21/23: read-only search, record detail and per-record
 * chronology. There are deliberately no create/update/delete routes; every
 * access is itself audited.
 */
class AuditTrailController extends Controller
{
    public function __construct(private AuditSearch $search, private TrailAuditLogger $logger) {}

    public function index(AuditSearchRequest $request, AccessControl $access): JsonResponse
    {
        $this->ensureArchiveAccess($request, $access);
        $criteria = $request->criteria();

        $page = $this->search->query($criteria)
            ->orderByDesc('tarikh_masa_utc')->orderByDesc('no_urutan')
            ->paginate((int) $request->input('per_page', 25));

        $this->logger->record($request->wantsArchive() ? 'capaian_arkib' : 'carian', 'aud', 'audit_rekod', null, 'berjaya', null, [], [
            'kriteria' => $criteria,
            'bilangan_hasil' => $page->total(),
        ]);

        return response()->json([
            'data' => $this->search->present(collect($page->items()))->values(),
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

    public function show(Request $request, AccessControl $access, int $id): JsonResponse
    {
        $source = $request->query('sumber') === AuditSearch::SUMBER_ARKIB ? AuditSearch::SUMBER_ARKIB : AuditSearch::SUMBER_DALAM_TALIAN;
        if ($source === AuditSearch::SUMBER_ARKIB) {
            $this->ensureArchiveAccess($request, $access, true);
        }

        $row = $this->search->query(['sumber' => $source])->where('id', $id)->first();
        abort_if($row === null, 404, __('aud::aud.tidak_dijumpai'));

        $this->logger->record($source === AuditSearch::SUMBER_ARKIB ? 'capaian_arkib' : 'lihat', 'aud', 'audit_rekod', $id);

        return response()->json(['data' => $this->search->present(collect([$row]))->first()]);
    }

    public function chronology(Request $request, AccessControl $access): JsonResponse
    {
        $validated = $request->validate([
            'nama_entiti' => ['required', 'string', 'max:100'],
            'id_rekod' => ['required', 'string', 'max:100'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $source = $access->allows($user, 'aud.arkib.lihat') ? AuditSearch::SUMBER_SEMUA : AuditSearch::SUMBER_DALAM_TALIAN;
        $rows = $this->search->chronology($validated['nama_entiti'], $validated['id_rekod'], $source);

        $this->logger->record('lihat_kronologi', 'aud', $validated['nama_entiti'], $validated['id_rekod'], 'berjaya', null, [], [
            'bilangan_hasil' => $rows->count(),
            'termasuk_arkib' => $source === AuditSearch::SUMBER_SEMUA,
        ]);

        return response()->json(['data' => $rows->values(), 'meta' => ['termasuk_arkib' => $source === AuditSearch::SUMBER_SEMUA]]);
    }

    /** Distinct values for the search form's dropdowns. */
    public function options(): JsonResponse
    {
        $distinct = fn (string $column) => DB::table('audit_rekod')->distinct()->orderBy($column)->limit(200)->pluck($column)->filter()->values();

        return response()->json(['data' => [
            'modul' => $distinct('modul'),
            'jenis_tindakan' => $distinct('jenis_tindakan'),
            'status_transaksi' => $distinct('status_transaksi'),
            'saluran_capaian' => $distinct('saluran_capaian'),
            'nama_entiti' => $distinct('nama_entiti'),
        ]]);
    }

    private function ensureArchiveAccess(Request $request, AccessControl $access, bool $force = false): void
    {
        $wants = $force || in_array($request->input('sumber'), [AuditSearch::SUMBER_ARKIB, AuditSearch::SUMBER_SEMUA], true);
        /** @var User $user */
        $user = $request->user();

        if ($wants && ! $access->allows($user, 'aud.arkib.lihat')) {
            abort(403, __('aud::aud.tiada_kebenaran_arkib'));
        }
    }
}
