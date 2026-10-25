<?php

namespace App\Modules\Tkt\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Tkt\Http\Present;
use App\Modules\Tkt\Models\Lampiran;
use App\Modules\Tkt\Models\Tiket;
use App\Modules\Tkt\Services\Cadangan;
use App\Modules\Tkt\Services\Pendua;
use App\Modules\Tkt\Services\TiketService;
use App\Modules\Tkt\Support\Akses;
use App\Modules\Tkt\Support\Lalai;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * SRS-TKT-01..07, 11, 12, 20: ticket intake from the help button (context,
 * consented screenshot, KB suggestions + deflection), the list (own /
 * assigned / team), detail and the reporter's own actions.
 */
class TiketController extends Controller
{
    /** Per-file ceiling at the API edge; MOD-DMS applies its own type / size / antivirus rules after. */
    public const SAIZ_MAKS_KB = 10240;

    public function __construct(private TiketService $service, private Present $present, private Akses $akses, private AuditLogger $audit) {}

    public function rujukan(): JsonResponse
    {
        $pairs = fn (array $a) => array_map(fn ($k, $v) => ['kod' => (string) $k, 'nama' => $v], array_keys($a), $a);

        return response()->json(['data' => [
            'kategori' => $pairs(Lalai::KATEGORI),
            'keterukan' => $pairs(Lalai::KETERUKAN),
            'kesan' => $pairs(Lalai::KESAN),
            'kesegeraan' => $pairs(Lalai::KESEGERAAN),
            'matriks' => $this->service->cadangKeterukanMatriks(),
            'modul' => $pairs(Lalai::MODUL),
            'status' => $pairs(Lalai::STATUS),
            'saluran' => $pairs(Lalai::SALURAN),
            'jenis_aktiviti' => $pairs(Lalai::JENIS_AKTIVITI),
            'peranan_ahli' => $pairs(Lalai::PERANAN_AHLI),
        ]]);
    }

    /** SRS-TKT-06: suggestions while typing (≥3 most relevant articles) + the reporter's own similar open tickets. */
    public function cadangan(Request $request, Cadangan $cadangan, Pendua $pendua): JsonResponse
    {
        $data = $request->validate([
            'teks' => ['required', 'string', 'max:4000'],
            'tajuk' => ['nullable', 'string', 'max:200'],
            'sesi' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]{8,64}$/'],
            'modul' => ['nullable', 'string', 'max:20'],
        ]);
        $user = $request->user();
        $modul = $this->service->modul($data['modul'] ?? null);

        return response()->json(['data' => [
            'artikel' => $cadangan->cari(trim(($data['tajuk'] ?? '').' '.$data['teks']), $data['sesi'], $user, $modul),
            'tiket_serupa' => $pendua->cari((string) ($data['tajuk'] ?? mb_substr($data['teks'], 0, 200)), null, $user),
        ]]);
    }

    public function jawapan(Request $request, Cadangan $cadangan): JsonResponse
    {
        $data = $request->validate(['teks' => ['required', 'string', 'min:8', 'max:2000']]);

        return response()->json(['data' => $cadangan->jawapan($data['teks'], $request->user())]);
    }

    public function klik(Request $request, Cadangan $cadangan): JsonResponse
    {
        $data = $request->validate(['sesi' => ['required', 'string', 'max:64'], 'pautan' => ['required', 'string', 'max:500']]);
        $cadangan->klik($data['sesi'], $data['pautan'], $request->user());

        return response()->json(['message' => 'ok']);
    }

    /** SRS-TKT-07 step 3: the article solved it — the user cancels; recorded as self-service. */
    public function selesaiSendiri(Request $request, Cadangan $cadangan): JsonResponse
    {
        $data = $request->validate(['sesi' => ['required', 'string', 'max:64']]);
        $n = $cadangan->keputusan($data['sesi'], $request->user(), false);

        return response()->json(['message' => __('tkt::tkt.mesej.selesai_sendiri'), 'data' => ['direkod' => $n]]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->bolehSenarai($user);
        $request->validate([
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:100'],
            'skop' => ['nullable', Rule::in(['saya', 'tugasan', 'pasukan', 'semua'])],
            'susun' => ['nullable', Rule::in(['tarikh_buka', 'sla', 'keterukan'])],
            'keterukan' => ['nullable', 'integer', 'between:1,4'],
        ]);
        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));
        [$teams] = Tiket::liputanPasukan($user);

        $query = Tiket::query()->bolehDilihat($user)->with(['pelapor', 'juruteknik', 'pasukan'])
            ->when($request->query('skop') === 'saya', fn (Builder $b) => $b->where('id_pelapor', $user->id))
            ->when($request->query('skop') === 'tugasan', fn (Builder $b) => $b->where('id_juruteknik', $user->id))
            ->when($request->query('skop') === 'pasukan', fn (Builder $b) => $b->whereIn('id_pasukan', $teams))
            ->when($status === 'terbuka', fn (Builder $b) => $b->whereIn('status', Lalai::TERBUKA))
            ->when($status !== '' && $status !== 'terbuka', fn (Builder $b) => $b->where('status', $status))
            ->when($request->query('keterukan'), fn (Builder $b, $v) => $b->where('keterukan', (int) $v))
            ->when($request->query('modul'), fn (Builder $b, $v) => $b->where('modul', $v))
            ->when($request->query('kategori'), fn (Builder $b, $v) => $b->where('kategori', $v))
            ->when($request->query('saluran'), fn (Builder $b, $v) => $b->where('saluran', $v))
            ->when($request->query('aras'), fn (Builder $b, $v) => $b->where('aras_sokongan', $v))
            ->when($request->query('id_pasukan'), fn (Builder $b, $v) => $b->where('id_pasukan', (int) $v))
            ->when($request->boolean('belum_ditugaskan'), fn (Builder $b) => $b->whereNull('id_juruteknik'))
            ->when($request->boolean('melepasi_sla'), fn (Builder $b) => $b->where(fn ($w) => $w->where('selesai_dilepasi', true)->orWhere('respons_dilepasi', true)))
            ->when($request->query('dari'), fn (Builder $b, $v) => $b->where('tarikh_buka', '>=', $v))
            ->when($request->query('hingga'), fn (Builder $b, $v) => $b->where('tarikh_buka', '<=', strlen((string) $v) <= 10 ? $v.' 23:59:59' : $v))
            ->when($q !== '', fn (Builder $b) => $b->where(fn ($w) => $w->where('nombor_rujukan', 'ilike', '%'.addcslashes($q, '%_\\').'%')->orWhere('tajuk', 'ilike', '%'.addcslashes($q, '%_\\').'%')));

        match ((string) $request->query('susun', 'tarikh_buka')) {
            'sla' => $query->orderByRaw('tarikh_sasaran_selesai ASC NULLS LAST'),
            'keterukan' => $query->orderBy('keterukan'),
            default => null,
        };
        $page = $query->orderByDesc('tarikh_buka')->orderBy('id')->paginate((int) $request->query('per_halaman', 25));

        return response()->json([
            'data' => $page->getCollection()->map(fn (Tiket $t) => $this->present->tiket($t, $user))->values(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage()],
        ]);
    }

    /** SRS-TKT-01..04: open a ticket (multipart; context JSON, attachments, consented screenshot). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tajuk' => ['required', 'string', 'min:5', 'max:200'],
            'penerangan' => ['required', 'string', 'min:10', 'max:10000'],
            'kategori' => ['required', Rule::in(array_keys(Lalai::KATEGORI))],
            'keterukan' => ['nullable', 'integer', 'between:1,4'],
            'kesan' => ['nullable', Rule::in(array_keys(Lalai::KESAN))],
            'kesegeraan' => ['nullable', Rule::in(array_keys(Lalai::KESEGERAAN))],
            'modul' => ['nullable', 'string', 'max:20'],
            'isu_keselamatan' => ['nullable', 'boolean'],
            'konteks' => ['nullable'],
            'sesi' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]{8,64}$/'],
            'fail' => ['nullable', 'array', 'max:5'],
            'fail.*' => ['file', 'max:'.self::SAIZ_MAKS_KB],
            'tangkapan_skrin' => ['nullable', 'file', 'max:'.self::SAIZ_MAKS_KB, 'mimes:png,jpg,jpeg,webp'],
            'persetujuan_tangkapan_skrin' => ['nullable', 'boolean'],
        ]);
        if ($request->hasFile('tangkapan_skrin') && ! $request->boolean('persetujuan_tangkapan_skrin')) {
            return response()->json(['message' => __('tkt::tkt.ralat.perlu_persetujuan'), 'errors' => ['persetujuan_tangkapan_skrin' => [__('tkt::tkt.ralat.perlu_persetujuan')]]], 422);
        }
        $konteks = $data['konteks'] ?? [];
        if (is_string($konteks)) {
            $konteks = json_decode($konteks, true) ?: [];
        }
        $user = $request->user();

        $tiket = DB::transaction(function () use ($data, $konteks, $user, $request) {
            $tiket = $this->service->buka($data, 'aplikasi', $user, null, is_array($konteks) ? $konteks : [], $data['sesi'] ?? null);
            foreach ($request->file('fail', []) as $file) {
                $this->service->lampiran($tiket, $file, $user, false, false);
            }
            if ($request->hasFile('tangkapan_skrin')) {
                $this->service->lampiran($tiket, $request->file('tangkapan_skrin'), $user, true, $request->boolean('persetujuan_tangkapan_skrin'));
            }

            return $tiket;
        });

        return response()->json(['data' => $this->present->butiran($tiket, $user), 'message' => __('tkt::tkt.mesej.dibuka', ['no' => $tiket->nombor_rujukan])], 201);
    }

    public function show(Request $request, string $tiket): JsonResponse
    {
        $user = $request->user();
        $t = $this->cari($user, $tiket);
        $this->audit->record('lihat', 'tkt', 'tkt_tiket', $t->id, meta: ['klasifikasi' => $t->klasifikasi]);

        return response()->json(['data' => $this->present->butiran($t, $user)]);
    }

    public function balas(Request $request, string $tiket): JsonResponse
    {
        $user = $request->user();
        $t = $this->pelapor($user, $tiket);
        $data = $request->validate(['kandungan' => ['required', 'string', 'max:5000']]);
        $this->service->balasPelapor($t, $data['kandungan'], $user);

        return response()->json(['data' => $this->present->butiran($t->refresh(), $user), 'message' => __('tkt::tkt.mesej.balasan')]);
    }

    public function sahkan(Request $request, string $tiket): JsonResponse
    {
        $user = $request->user();
        $t = $this->pelapor($user, $tiket);
        $data = $request->validate(['catatan' => ['nullable', 'string', 'max:2000']]);
        $this->service->sahkan($t, $user, $data['catatan'] ?? null);

        return response()->json(['data' => $this->present->butiran($t->refresh(), $user), 'message' => __('tkt::tkt.mesej.disahkan')]);
    }

    public function kepuasan(Request $request, string $tiket): JsonResponse
    {
        $user = $request->user();
        $t = $this->pelapor($user, $tiket);
        $data = $request->validate(['skor' => ['required', 'integer', 'between:1,5'], 'ulasan' => ['nullable', 'string', 'max:1000']]);
        $this->service->kepuasan($t, (int) $data['skor'], $data['ulasan'] ?? null, $user);

        return response()->json(['data' => $this->present->butiran($t->refresh(), $user), 'message' => __('tkt::tkt.mesej.kepuasan')]);
    }

    /** Reporter or support may reopen within the window (SRS-TKT-12). */
    public function bukaSemula(Request $request, string $tiket): JsonResponse
    {
        $user = $request->user();
        $t = $this->cari($user, $tiket);
        abort_unless($this->akses->bolehBalasPelapor($user, $t) || $this->akses->bolehUrus($user, $t), 403);
        $data = $request->validate(['sebab' => ['required', 'string', 'min:5', 'max:2000']]);
        $this->service->bukaSemula($t, $user, $data['sebab']);

        return response()->json(['data' => $this->present->butiran($t->refresh(), $user), 'message' => __('tkt::tkt.mesej.dibuka_semula')]);
    }

    /** Additional attachment / screenshot (SRS-TKT-03, SRS-TKT-11). */
    public function lampiran(Request $request, string $tiket): JsonResponse
    {
        $user = $request->user();
        $t = $this->cari($user, $tiket);
        $support = $this->akses->bolehUrus($user, $t);
        abort_unless($support || $this->akses->bolehBalasPelapor($user, $t), 403);
        $request->validate([
            'fail' => ['required', 'file', 'max:'.self::SAIZ_MAKS_KB],
            'tangkapan_skrin' => ['nullable', 'boolean'],
            'persetujuan' => ['nullable', 'boolean'],
            'dalaman' => ['nullable', 'boolean'],
        ]);
        $this->service->lampiran($t, $request->file('fail'), $user, $request->boolean('tangkapan_skrin'), $request->boolean('persetujuan'), ! ($support && $request->boolean('dalaman')));

        return response()->json(['data' => $this->present->butiran($t->refresh(), $user), 'message' => __('tkt::tkt.mesej.lampiran')], 201);
    }

    public function muatTurun(Request $request, string $tiket, int $lampiran, DocumentStore $documents): JsonResponse
    {
        $user = $request->user();
        $t = $this->cari($user, $tiket);
        $row = Lampiran::query()->where('id_tiket', $t->id)->findOrFail($lampiran);
        abort_if(! $row->kelihatan_pelapor && ! $this->akses->sokongan($user, $t), 404);
        $url = $documents->temporaryUrl($row->id_dokumen, $user);
        abort_if($url === null, 403, (string) __('tkt::tkt.ralat.lampiran_tiada_akses'));

        return response()->json(['data' => ['url' => $url]]);
    }

    private function bolehSenarai(User $user): void
    {
        abort_unless($user->can('tkt.tiket.lihat') || $user->can('tkt.sokongan.lihat') || $user->can('tkt.semua.pantau'), 403);
    }

    /** Visible ticket or 404 (an out-of-scope ticket is indistinguishable from a missing one). */
    private function cari(User $user, string $id): Tiket
    {
        $this->bolehSenarai($user);
        abort_unless(preg_match('/^[0-9a-f-]{36}$/i', $id) === 1, 404);

        return Tiket::query()->bolehDilihat($user)->with(['pelapor', 'juruteknik', 'pasukan'])->findOrFail($id);
    }

    private function pelapor(User $user, string $id): Tiket
    {
        $t = $this->cari($user, $id);
        abort_unless($this->akses->bolehBalasPelapor($user, $t), 403);

        return $t;
    }
}
