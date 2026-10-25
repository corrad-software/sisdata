<?php

namespace App\Modules\Lap\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Lap\Models\DefinisiLaporan;
use App\Modules\Lap\Models\JadualPenjanaan;
use App\Modules\Lap\Models\KatalogLaporan;
use App\Modules\Lap\Services\Eksport;
use App\Modules\Lap\Services\Jadual;
use App\Modules\Lap\Services\Katalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Scheduled report distribution (SRS-LAP-06). Recipients are SISDATA users;
 * each receives the report generated under their OWN permissions and scope,
 * so a recipient who may not open the report is refused at save time.
 */
class JadualController extends Controller
{
    public function __construct(private Katalog $katalog, private Jadual $jadual) {}

    public function index(Request $request): JsonResponse
    {
        $rows = JadualPenjanaan::query()->where('id_pencipta', $request->user()?->id)->orderBy('nama')->get()->map(fn ($j) => $this->present($j))->values();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $j = new JadualPenjanaan(['id_pencipta' => $user->id]);
        $this->fill($request, $user, $j);

        return response()->json(['message' => __('lap::lap.disimpan'), 'data' => $this->present($j)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $j = JadualPenjanaan::query()->where('id_pencipta', $user->id)->findOrFail($id);
        $this->fill($request, $user, $j);

        return response()->json(['message' => __('lap::lap.disimpan'), 'data' => $this->present($j)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        JadualPenjanaan::query()->where('id_pencipta', $request->user()?->id)->findOrFail($id)->delete();

        return response()->json(['message' => __('lap::lap.dipadam')]);
    }

    /** Run now (manual trigger) — same path as the scheduler. */
    public function runNow(Request $request, int $id): JsonResponse
    {
        $j = JadualPenjanaan::query()->where('id_pencipta', $request->user()?->id)->findOrFail($id);
        $result = $this->jadual->run($j, false);

        return response()->json(['message' => __('lap::lap.jadual.dijalankan'), 'data' => $result + ['jadual' => $this->present($j->refresh())]]);
    }

    /** Active users the creator can choose as recipients (search). */
    public function recipients(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $rows = User::query()->where('is_active', true)
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'ilike', '%'.addcslashes($q, '%_\\').'%')->orWhere('email', 'ilike', '%'.addcslashes($q, '%_\\').'%')))
            ->orderBy('name')->limit(20)->get(['id', 'name', 'email', 'bahagian']);

        return response()->json(['data' => $rows]);
    }

    private function fill(Request $request, User $user, JadualPenjanaan $j): void
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:200'],
            'id_definisi' => ['nullable', 'uuid', 'required_without:kod_laporan'],
            'kod_laporan' => ['nullable', 'string', 'max:80', 'required_without:id_definisi'],
            'kekerapan' => ['required', Rule::in(JadualPenjanaan::KEKERAPAN)],
            'masa_jalan' => ['required', 'date_format:H:i'],
            'hari_jalan' => ['nullable', 'integer', 'min:1', 'max:28'],
            'format' => ['required', Rule::in(Eksport::FORMAT)],
            'parameter' => ['nullable', 'array'],
            'senarai_penerima' => ['required', 'array', 'min:1', 'max:100'],
            'senarai_penerima.*' => ['integer'],
            'status_aktif' => ['sometimes', 'boolean'],
        ]);
        if ($data['kekerapan'] === 'mingguan' && isset($data['hari_jalan']) && $data['hari_jalan'] > 7) {
            throw ValidationException::withMessages(['hari_jalan' => __('lap::lap.jadual.hari_mingguan')]);
        }

        // The creator must be able to open the report.
        $this->katalog->sync();
        if (! empty($data['id_definisi'])) {
            $def = DefinisiLaporan::query()->findOrFail($data['id_definisi']);
            abort_unless($this->katalog->canOpenDefinition($user, $def), 403, __('lap::lap.ralat.tiada_akses'));
            $data['kod_laporan'] = null;
        } else {
            $entry = KatalogLaporan::query()->where('kod_laporan', $data['kod_laporan'])->whereIn('jenis', ['lap', 'komposit'])->first();
            abort_unless($entry && $this->katalog->canOpen($user, $entry), 403, __('lap::lap.ralat.tiada_akses'));
            $data['id_definisi'] = null;
        }

        // Recipient scope: every recipient must be an active user who may open it.
        $ids = array_values(array_unique(array_map('intval', $data['senarai_penerima'])));
        $users = User::query()->whereIn('id', $ids)->where('is_active', true)->get();
        $denied = [];
        foreach ($ids as $id) {
            $u = $users->firstWhere('id', $id);
            if (! $u || ! $this->jadual->recipientMayOpen($u, $data['id_definisi'] ?? null, $data['kod_laporan'] ?? null)) {
                $denied[] = $u->name ?? (string) $id;
            }
        }
        if ($denied !== []) {
            throw ValidationException::withMessages(['senarai_penerima' => __('lap::lap.jadual.penerima_tanpa_akses', ['nama' => implode(', ', $denied)])]);
        }

        $j->fill([...$data, 'senarai_penerima' => $ids, 'status_aktif' => $data['status_aktif'] ?? true]);
        $j->jalan_berikutnya = $j->status_aktif ? $j->nextRun() : null;
        $j->save();
    }

    /** @return array<string, mixed> */
    private function present(JadualPenjanaan $j): array
    {
        $names = User::query()->whereIn('id', (array) $j->senarai_penerima)->pluck('name', 'id');

        return [
            'id' => $j->id, 'nama' => $j->nama, 'id_definisi' => $j->id_definisi, 'kod_laporan' => $j->kod_laporan, 'kekerapan' => $j->kekerapan,
            'kekerapan_nama' => __('lap::lap.kekerapan.'.$j->kekerapan), 'masa_jalan' => $j->masa_jalan, 'hari_jalan' => $j->hari_jalan, 'format' => $j->format,
            'parameter' => $j->parameter, 'senarai_penerima' => array_map(fn ($id) => ['id' => $id, 'nama' => $names[$id] ?? (string) $id], (array) $j->senarai_penerima),
            'status_aktif' => $j->status_aktif, 'jalan_berikutnya' => $j->jalan_berikutnya?->toIso8601String(), 'jalan_terakhir' => $j->jalan_terakhir?->toIso8601String(),
            'status_terakhir' => $j->status_terakhir,
        ];
    }
}
