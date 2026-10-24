<?php

namespace App\Modules\Ast\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ast\Http\Present;
use App\Modules\Ast\Models\AsetKontraktor;
use App\Modules\Ast\Models\RekodPenyelenggaraan;
use App\Modules\Ast\Support\SusutNilai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Maintenance contractors (company data only — synthetic in dev; no personal identifiers). */
class KontraktorController extends Controller
{
    public function __construct(private Present $present) {}

    public function index(Request $request): JsonResponse
    {
        $rows = AsetKontraktor::query()->when($request->query('q'), fn ($w, $v) => $w->where('nama_syarikat', 'ilike', '%'.$v.'%'))
            ->when(! $request->boolean('semua'), fn ($w) => $w->where('status_aktif', true))->orderBy('nama_syarikat')->limit(500)->get();
        $stat = RekodPenyelenggaraan::query()->whereIn('id_kontraktor', $rows->pluck('id')->all())->groupBy('id_kontraktor')
            ->selectRaw("id_kontraktor, COUNT(*) AS n, COUNT(*) FILTER (WHERE status = 'selesai') AS siap, COALESCE(SUM(kos) FILTER (WHERE status = 'selesai'), 0)::text AS kos")->get()->keyBy('id_kontraktor');

        return response()->json(['data' => $rows->map(fn (AsetKontraktor $c) => $this->present->kontraktor($c) + [
            'bilangan_kerja' => (int) ($stat[$c->id]?->getAttribute('n') ?? 0), 'kerja_siap' => (int) ($stat[$c->id]?->getAttribute('siap') ?? 0),
            'jumlah_kos' => SusutNilai::bundar((string) ($stat[$c->id]?->getAttribute('kos') ?? '0')),
        ])->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $c = AsetKontraktor::query()->create($this->rules($request, true));

        return response()->json(['data' => $this->present->kontraktor($c), 'message' => __('ast::ast.kontraktor_disimpan')], 201);
    }

    public function update(Request $request, int $kontraktor): JsonResponse
    {
        $c = AsetKontraktor::query()->findOrFail($kontraktor);
        $c->fill($this->rules($request, false))->save();

        return response()->json(['data' => $this->present->kontraktor($c), 'message' => __('ast::ast.kontraktor_disimpan')]);
    }

    /** @return array<string, mixed> */
    private function rules(Request $request, bool $create): array
    {
        return $request->validate([
            'nama_syarikat' => [$create ? 'required' : 'sometimes', 'string', 'max:200'], 'no_pendaftaran' => ['nullable', 'string', 'max:40'],
            'no_telefon' => ['nullable', 'string', 'max:30'], 'emel' => ['nullable', 'email', 'max:150'], 'bidang' => ['nullable', 'array', 'max:20'], 'bidang.*' => ['string', 'max:40'],
            'tarikh_tamat_pendaftaran' => ['nullable', 'date'], 'id_pengguna' => ['nullable', 'integer', 'exists:users,id', Rule::unique('aset_kontraktor', 'id_pengguna')->ignore($request->route('kontraktor'))],
            'status_aktif' => ['sometimes', 'boolean'],
        ]);
    }
}
