<?php

namespace App\Modules\Rbc\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Rbc\Models\RecertKeputusan;
use App\Modules\Rbc\Models\RecertKitaran;
use App\Modules\Rbc\Services\Recertification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** SRS-RBC-27: periodic access recertification by data owners. */
class RecertificationController extends Controller
{
    public function __construct(private Recertification $recert) {}

    public function index(Request $request): JsonResponse
    {
        $cycles = RecertKitaran::query()
            ->withCount([
                'keputusan',
                'keputusan as belum_count' => fn ($q) => $q->where('keputusan', RecertKeputusan::BELUM),
                'keputusan as ditarik_count' => fn ($q) => $q->where('keputusan', RecertKeputusan::DITARIK),
            ])
            ->latest('id')->limit(100)->get();

        return response()->json(['data' => $cycles->map(fn (RecertKitaran $c) => $this->presentCycle($c, $request))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'bahagian' => ['nullable', 'string', 'max:100'],
            'tarikh_akhir' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $cycle = $this->recert->open($data['nama'], $data['bahagian'] ?? null, isset($data['tarikh_akhir']) ? Carbon::parse($data['tarikh_akhir']) : null, $request->user());

        return response()->json(['data' => $this->presentCycle($cycle->loadCount('keputusan'), $request)], 201);
    }

    public function show(Request $request, RecertKitaran $kitaran): JsonResponse
    {
        $decisions = $kitaran->keputusan()
            ->with(['user:id,name,email,bahagian,unit,jawatan', 'peranan:id,kod,nama', 'peranan.kebenaran:id,peranan_id,kebenaran', 'penyemak:id,name'])
            ->when($request->filled('keputusan'), fn ($q) => $q->where('keputusan', $request->string('keputusan')))
            ->orderBy('user_id')->orderBy('peranan_id')
            ->get();

        return response()->json(['data' => [
            ...$this->presentCycle($kitaran->loadCount('keputusan'), $request),
            'keputusan' => $decisions->map(fn (RecertKeputusan $d) => [
                'id' => $d->id,
                'pengguna' => ['id' => $d->user->id, 'name' => $d->user->name, 'email' => $d->user->email, 'bahagian' => $d->user->bahagian, 'jawatan' => $d->user->jawatan],
                'peranan' => ['id' => $d->peranan->id, 'kod' => $d->peranan->kod, 'nama' => $d->peranan->nama],
                'kebenaran' => $d->peranan->kebenaran->pluck('kebenaran')->sort()->values(),
                'keputusan' => $d->keputusan,
                'catatan' => $d->catatan,
                'penyemak' => $d->penyemak?->name,
                'disemak_pada' => $d->disemak_pada?->toIso8601String(),
            ]),
        ]]);
    }

    public function decide(Request $request, RecertKitaran $kitaran, RecertKeputusan $keputusan): JsonResponse
    {
        abort_unless($keputusan->kitaran_id === $kitaran->id, 404);

        $data = $request->validate([
            'keputusan' => ['required', Rule::in([RecertKeputusan::DISAHKAN, RecertKeputusan::DITARIK])],
            'catatan' => ['nullable', 'string', 'max:255', Rule::requiredIf($request->input('keputusan') === RecertKeputusan::DITARIK)],
        ]);

        $this->recert->decide($keputusan, $data['keputusan'], $data['catatan'] ?? null, $request->user());

        return response()->json(['data' => ['id' => $keputusan->id, 'keputusan' => $keputusan->keputusan]]);
    }

    public function close(Request $request, RecertKitaran $kitaran): JsonResponse
    {
        $this->recert->close($kitaran, $request->user());

        return response()->json(['data' => $this->presentCycle($kitaran->loadCount('keputusan'), $request)]);
    }

    /** @return array<string, mixed> */
    private function presentCycle(RecertKitaran $cycle, Request $request): array
    {
        return [
            'id' => $cycle->id,
            'nama' => $cycle->nama,
            'bahagian' => $cycle->bahagian,
            'tarikh_mula' => $cycle->tarikh_mula->toDateString(),
            'tarikh_akhir' => $cycle->tarikh_akhir->toDateString(),
            'status' => $cycle->status,
            'ditutup_pada' => $cycle->ditutup_pada?->toIso8601String(),
            'jumlah' => (int) ($cycle->getAttribute('keputusan_count') ?? 0),
            'belum' => (int) ($cycle->getAttribute('belum_count') ?? $cycle->keputusan()->where('keputusan', RecertKeputusan::BELUM)->count()),
            'ditarik' => (int) ($cycle->getAttribute('ditarik_count') ?? $cycle->keputusan()->where('keputusan', RecertKeputusan::DITARIK)->count()),
            'boleh_semak' => $this->recert->canReview($request->user(), $cycle),
        ];
    }
}
