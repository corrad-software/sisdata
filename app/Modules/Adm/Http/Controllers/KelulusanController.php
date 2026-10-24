<?php

namespace App\Modules\Adm\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Adm\Models\KonfigKelulusan;
use App\Modules\Adm\Services\KelulusanKonfigurasi;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** SRS-ADM-18/19: pending critical configuration changes and their decisions. */
class KelulusanController extends Controller
{
    public function __construct(private KelulusanKonfigurasi $approvals, private AccessControl $access) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'in:menunggu,lulus,tolak,dibatalkan'],
            'jenis_konfig' => ['nullable', 'string', 'max:30'],
            'dari' => ['nullable', 'date'],
            'hingga' => ['nullable', 'date'],
        ]);

        $page = KonfigKelulusan::query()
            ->with(['pemohon:id,name', 'pelulus:id,name'])
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('jenis_konfig'), fn ($q, $j) => $q->where('jenis_konfig', $j))
            ->when($request->input('dari'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->input('hingga'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest()
            ->paginate(20);

        $user = $request->user();
        $page->getCollection()->transform(fn (KonfigKelulusan $k) => self::present($k, $user, $this->access, $this->approvals));

        return response()->json([
            ...$page->toArray(),
            'mod' => $this->approvals->usesWorkflow() ? 'wfe' : 'terbina',
        ]);
    }

    public function approve(Request $request, KonfigKelulusan $kelulusan): JsonResponse
    {
        $data = $request->validate(['catatan' => ['nullable', 'string', 'max:1000']]);
        $this->approvals->approve($kelulusan, $request->user(), $data['catatan'] ?? null);

        return response()->json(['message' => __('adm::adm.kelulusan.approved'), 'data' => self::present($kelulusan->fresh() ?? $kelulusan)]);
    }

    public function reject(Request $request, KonfigKelulusan $kelulusan): JsonResponse
    {
        $data = $request->validate(['catatan' => ['required', 'string', 'max:1000']], [], (array) __('adm::adm.attributes'));
        $this->approvals->reject($kelulusan, $request->user(), $data['catatan']);

        return response()->json(['message' => __('adm::adm.kelulusan.rejected'), 'data' => self::present($kelulusan->fresh() ?? $kelulusan)]);
    }

    public function cancel(Request $request, KonfigKelulusan $kelulusan): JsonResponse
    {
        $this->approvals->cancel($kelulusan, $request->user());

        return response()->json(['message' => __('adm::adm.kelulusan.cancelled'), 'data' => self::present($kelulusan->fresh() ?? $kelulusan)]);
    }

    /** @return array<string, mixed> */
    public static function present(KonfigKelulusan $k, ?User $viewer = null, ?AccessControl $access = null, ?KelulusanKonfigurasi $approvals = null): array
    {
        $canDecide = $viewer && $access && $approvals
            && $k->status === KonfigKelulusan::MENUNGGU
            && $k->id_pemohon !== $viewer->id
            && $access->allows($viewer, KelulusanKonfigurasi::PELULUS)
            && ! ($k->id_instans_aliran !== null && $approvals->usesWorkflow());

        return [
            'id' => $k->id,
            'jenis_konfig' => $k->jenis_konfig,
            'kunci' => $k->kunci,
            'nilai_sebelum' => $k->nilai_sebelum,
            'nilai_selepas' => $k->nilai_selepas,
            'justifikasi' => $k->justifikasi,
            'status' => $k->status,
            'pemohon' => $k->relationLoaded('pemohon') ? $k->pemohon?->only(['id', 'name']) : ['id' => $k->id_pemohon],
            'pelulus' => $k->relationLoaded('pelulus') ? $k->pelulus?->only(['id', 'name']) : ($k->id_pelulus ? ['id' => $k->id_pelulus] : null),
            'alamat_ip_pemohon' => $k->alamat_ip_pemohon,
            'catatan_pelulus' => $k->catatan_pelulus,
            'id_instans_aliran' => $k->id_instans_aliran,
            'dimohon_pada' => $k->created_at->toIso8601String(),
            'diputuskan_pada' => $k->diputuskan_pada?->toIso8601String(),
            'boleh_putus' => (bool) $canDecide,
            'boleh_batal' => $viewer && $k->status === KonfigKelulusan::MENUNGGU && $k->id_pemohon === $viewer->id,
        ];
    }
}
