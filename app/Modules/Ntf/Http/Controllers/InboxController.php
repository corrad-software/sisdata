<?php

namespace App\Modules\Ntf\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Ntf\Models\Jenis;
use App\Modules\Ntf\Models\Penghantaran;
use App\Modules\Ntf\Models\StatusPengguna;
use App\Modules\Ntf\NtfServiceProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The user's own notification list (SRS-NTF-11..14). Only ever touches rows
 * of the authenticated user; the delivery log is never deleted.
 */
class InboxController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'folder' => ['nullable', Rule::in(['peti', 'arkib'])],
            'status' => ['nullable', Rule::in(['belum_baca', 'dibaca'])],
            'jenis' => ['nullable', 'string', 'max:100'],
            'keutamaan' => ['nullable', Rule::in(Jenis::KEUTAMAAN)],
            'modul' => ['nullable', 'string', 'max:20'],
            'dari' => ['nullable', 'date'],
            'hingga' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = $this->base($request->user())
            ->when(($filters['folder'] ?? 'peti') === 'arkib',
                fn ($q) => $q->whereNotNull('ntf_status_pengguna.diarkib_pada'),
                fn ($q) => $q->whereNull('ntf_status_pengguna.diarkib_pada'))
            ->when(($filters['status'] ?? null) === 'belum_baca', fn ($q) => $q->whereNull('ntf_status_pengguna.dibaca_pada'))
            ->when(($filters['status'] ?? null) === 'dibaca', fn ($q) => $q->whereNotNull('ntf_status_pengguna.dibaca_pada'))
            ->when($filters['jenis'] ?? null, fn ($q, $v) => $q->where('ntf_jenis.kod', $v))
            ->when($filters['keutamaan'] ?? null, fn ($q, $v) => $q->where('ntf_mesej.keutamaan', $v))
            ->when($filters['modul'] ?? null, fn ($q, $v) => $q->where('ntf_mesej.modul', $v))
            ->when($filters['dari'] ?? null, fn ($q, $v) => $q->where('ntf_status_pengguna.created_at', '>=', $v))
            ->when($filters['hingga'] ?? null, fn ($q, $v) => $q->where('ntf_status_pengguna.created_at', '<', date('Y-m-d', strtotime($v.' +1 day'))))
            ->when($filters['q'] ?? null, function ($q, $v) {
                $like = '%'.addcslashes($v, '%_\\').'%';
                $q->where(fn ($w) => $w->where('ntf_mesej.subjek', 'ilike', $like)->orWhere('ntf_mesej.kandungan', 'ilike', $like));
            });

        $page = $query
            ->orderByRaw('ntf_status_pengguna.dibaca_pada IS NULL DESC')
            ->orderByRaw("CASE ntf_mesej.keutamaan WHEN 'kritikal' THEN 1 WHEN 'tinggi' THEN 2 WHEN 'biasa' THEN 3 ELSE 4 END")
            ->orderByDesc('ntf_status_pengguna.created_at')
            ->paginate((int) ($filters['per_page'] ?? 20));

        $items = collect($page->items());
        $this->markReceived($request->user(), $items);

        return response()->json([
            'data' => $items->map(fn ($row) => $this->present($row))->values(),
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

    /** Bell: unread count + latest items (polled by the frontend). */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $latest = $this->base($user)->whereNull('ntf_status_pengguna.diarkib_pada')
            ->orderByDesc('ntf_status_pengguna.created_at')->limit(8)->get();
        $this->markReceived($user, $latest);

        return response()->json(['data' => [
            'belum_baca' => $this->unreadCount($user),
            'terkini' => $latest->map(fn ($row) => $this->present($row))->values(),
        ]]);
    }

    /** Filter options for the list. */
    public function options(Request $request): JsonResponse
    {
        $rows = Jenis::query()->whereIn('id', function ($q) use ($request) {
            $q->select('ntf_mesej.jenis_id')->from('ntf_status_pengguna')
                ->join('ntf_mesej', 'ntf_mesej.id', '=', 'ntf_status_pengguna.mesej_id')
                ->where('ntf_status_pengguna.pengguna_id', $request->user()->id);
        })->orderBy('nama')->get(['kod', 'nama', 'modul']);

        return response()->json(['data' => [
            'jenis' => $rows->map(fn ($j) => ['value' => $j->kod, 'label' => $j->nama])->values(),
            'modul' => $rows->pluck('modul')->unique()->sort()->values(),
            'keutamaan' => collect(Jenis::KEUTAMAAN)->map(fn ($k) => ['value' => $k, 'label' => __('ntf::ntf.keutamaan.'.$k)]),
        ]]);
    }

    /** Which MOD-NTF admin screens the user may open (UI hint only; the API enforces). */
    public function abilities(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => collect(array_keys(NtfServiceProvider::PERMISSIONS))
            ->mapWithKeys(fn ($p) => [$p => $user->can($p)])]);
    }

    /** Individual and bulk actions (SRS-NTF-11/12/13). */
    public function act(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'tindakan' => ['required', Rule::in(['baca', 'belum_baca', 'arkib', 'nyaharkib', 'padam'])],
            'ids' => ['required_without:semua', 'array', 'max:500'],
            'ids.*' => ['integer'],
            'semua' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $query = StatusPengguna::query()->where('pengguna_id', $user->id)->whereNull('dipadam_pada');
        if (! ($data['semua'] ?? false)) {
            $query->whereIn('id', $data['ids'] ?? []);
        } elseif ($data['tindakan'] !== 'baca') {
            $query->whereRaw('false'); // "all" is only offered for mark-all-read
        }

        $rows = $query->get(['id', 'mesej_id']);
        $ids = $rows->pluck('id');
        $now = now();

        DB::transaction(function () use ($data, $ids, $rows, $user, $now) {
            $target = StatusPengguna::query()->whereIn('id', $ids);
            match ($data['tindakan']) {
                'baca' => $target->whereNull('dibaca_pada')->update(['dibaca_pada' => $now, 'updated_at' => $now]),
                'belum_baca' => $target->update(['dibaca_pada' => null, 'updated_at' => $now]),
                'arkib' => $target->update(['diarkib_pada' => $now, 'updated_at' => $now]),
                'nyaharkib' => $target->update(['diarkib_pada' => null, 'updated_at' => $now]),
                default => $target->update(['dipadam_pada' => $now, 'updated_at' => $now]), // padam
            };

            if ($data['tindakan'] === 'baca') {
                // SRS-NTF-16: stamp "baca" on the in-app delivery record.
                $inApp = fn () => Penghantaran::query()
                    ->where('penerima_id', $user->id)
                    ->where('saluran', 'dalam_sistem')
                    ->whereIn('mesej_id', $rows->pluck('mesej_id'))
                    ->whereNull('tarikh_baca');
                $inApp()->whereNull('tarikh_terima')->update(['tarikh_terima' => $now]);
                $inApp()->update(['status' => Penghantaran::DIBACA, 'tarikh_baca' => $now, 'updated_at' => $now]);
            }
        });

        if ($ids->isNotEmpty()) {
            $audit->record($data['tindakan'] === 'padam' ? 'padam' : 'kemas_kini', 'ntf', 'ntf_status_pengguna', null,
                meta: ['tindakan' => $data['tindakan'], 'ids' => $ids->all()]);
        }

        return response()->json([
            'message' => __('ntf::ntf.dikemas_kini'),
            'data' => ['dikemas_kini' => $ids->count(), 'belum_baca' => $this->unreadCount($user)],
        ]);
    }

    /** @return Builder<StatusPengguna> */
    private function base(User $user): Builder
    {
        return StatusPengguna::query()
            ->join('ntf_mesej', 'ntf_mesej.id', '=', 'ntf_status_pengguna.mesej_id')
            ->join('ntf_jenis', 'ntf_jenis.id', '=', 'ntf_mesej.jenis_id')
            ->where('ntf_status_pengguna.pengguna_id', $user->id)
            ->whereNull('ntf_status_pengguna.dipadam_pada')
            ->select([
                'ntf_status_pengguna.*',
                'ntf_mesej.subjek', 'ntf_mesej.kandungan', 'ntf_mesej.pautan', 'ntf_mesej.keutamaan', 'ntf_mesej.modul',
                'ntf_jenis.kod as jenis_kod', 'ntf_jenis.nama as jenis_nama',
            ]);
    }

    private function unreadCount(User $user): int
    {
        return StatusPengguna::query()->where('pengguna_id', $user->id)
            ->whereNull('dipadam_pada')->whereNull('diarkib_pada')->whereNull('dibaca_pada')->count();
    }

    /**
     * SRS-NTF-16: the first time the user's client fetches an in-app item, it is "diterima".
     *
     * @param  Collection<int, StatusPengguna>  $rows
     */
    private function markReceived(User $user, Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        Penghantaran::query()
            ->where('penerima_id', $user->id)
            ->where('saluran', 'dalam_sistem')
            ->whereIn('mesej_id', $rows->pluck('mesej_id'))
            ->where('status', Penghantaran::DIHANTAR)
            ->update(['status' => Penghantaran::DITERIMA, 'tarikh_terima' => now(), 'updated_at' => now()]);
    }

    /** @return array<string, mixed> */
    private function present(StatusPengguna $row): array
    {
        return [
            'id' => $row->id,
            'mesej_id' => $row->mesej_id,
            'jenis' => ['kod' => $row->getAttribute('jenis_kod'), 'nama' => $row->getAttribute('jenis_nama')],
            'modul' => $row->getAttribute('modul'),
            'keutamaan' => $row->getAttribute('keutamaan'),
            'subjek' => $row->getAttribute('subjek'),
            'kandungan' => $row->getAttribute('kandungan'),
            'pautan' => $row->getAttribute('pautan'),
            'dibaca' => $row->dibaca_pada !== null,
            'dibaca_pada' => $row->dibaca_pada?->toIso8601String(),
            'diarkib' => $row->diarkib_pada !== null,
            'dieskalasi' => $row->dieskalasi_pada !== null,
            'created_at' => $row->created_at->toIso8601String(),
        ];
    }
}
