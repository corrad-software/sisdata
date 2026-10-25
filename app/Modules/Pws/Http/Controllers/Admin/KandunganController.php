<?php

namespace App\Modules\Pws\Http\Controllers\Admin;

use App\Models\User;
use App\Modules\Kba\Contracts\LlmGateway;
use App\Modules\Pws\Models\Kandungan;
use App\Modules\Pws\Services\KandunganService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

/** /api/v1/pws/kandungan — portal content governance (SRS-PWS-04). */
class KandunganController
{
    public function __construct(private KandunganService $service) {}

    public function index(Request $request): JsonResponse
    {
        $q = $request->validate([
            'jenis' => ['nullable', Rule::in(array_keys(Kandungan::JENIS))],
            'status' => ['nullable', Rule::in(array_keys(Kandungan::STATUS))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $page = Kandungan::query()
            ->when($q['jenis'] ?? null, fn ($w, $v) => $w->where('jenis', $v))
            ->when($q['status'] ?? null, fn ($w, $v) => $w->where('status_terbitan', $v))
            ->orderByDesc('updated_at')->paginate((int) ($q['per_page'] ?? 20));

        return response()->json([
            'data' => collect($page->items())->map(fn (Kandungan $k) => self::present($k, false))->all(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
            'jenis' => collect(Kandungan::JENIS)->map(fn ($n, $k) => ['kod' => $k, 'nama' => $n])->values(),
        ]);
    }

    public function show(Kandungan $kandungan): JsonResponse
    {
        return response()->json(['data' => self::present($kandungan, true)]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['data' => self::present($this->service->create($request->all(), $this->user($request)), true), 'message' => __('pws::pws.mesej.disimpan')], 201);
    }

    public function update(Request $request, Kandungan $kandungan): JsonResponse
    {
        return response()->json(['data' => self::present($this->service->update($kandungan, $request->all()), true), 'message' => __('pws::pws.mesej.disimpan')]);
    }

    public function janaAi(Request $request, LlmGateway $llm): JsonResponse
    {
        $user = $this->user($request);
        $key = 'pws:jana_ai:'.$user->id;
        abort_if(RateLimiter::tooManyAttempts($key, 10), 429, __('pws::pws.ralat.had_kadar'));
        RateLimiter::hit($key, 600);

        return response()->json(['data' => self::present($this->service->draftWithAi($request->all(), $user, $llm), true), 'message' => __('pws::pws.mesej.draf_ai')], 201);
    }

    public function statusAi(LlmGateway $llm): JsonResponse
    {
        return response()->json(['data' => ['model' => $llm->model(), 'tersedia' => $llm->model() !== null && $llm->available(), 'lokal' => true]]);
    }

    public function submit(Request $request, Kandungan $kandungan): JsonResponse
    {
        return response()->json(['data' => self::present($this->service->submit($kandungan, $this->user($request)), true), 'message' => __('pws::pws.mesej.dihantar_semakan')]);
    }

    public function withdraw(Request $request, Kandungan $kandungan): JsonResponse
    {
        $data = $request->validate(['sebab' => ['required', 'string', 'min:5', 'max:1000']], [], ['sebab' => 'sebab']);

        return response()->json(['data' => self::present($this->service->withdraw($kandungan, $data['sebab']), true), 'message' => __('pws::pws.mesej.ditarik')]);
    }

    /** @return array<string, mixed> */
    public static function present(Kandungan $k, bool $full): array
    {
        $base = [
            'id' => $k->id, 'jenis' => $k->jenis, 'jenis_nama' => Kandungan::JENIS[$k->jenis] ?? $k->jenis, 'slug' => $k->slug,
            'tajuk_bm' => $k->tajuk_bm, 'tajuk_bi' => $k->tajuk_bi, 'status' => $k->status_terbitan,
            'status_nama' => Kandungan::STATUS[$k->status_terbitan] ?? $k->status_terbitan, 'dijana_ai' => $k->dijana_ai,
            'model_ai' => $k->model_ai, 'id_pengarang' => $k->id_pengarang, 'id_penyemak' => $k->id_penyemak,
            'tarikh_terbit' => $k->tarikh_terbit?->toIso8601String(), 'dikemas_kini' => $k->updated_at->toIso8601String(),
        ];

        return $full ? $base + [
            'kandungan_bm' => $k->kandungan_bm, 'kandungan_bi' => $k->kandungan_bi, 'ringkasan' => $k->ringkasan,
            'kemajuan_peratus' => $k->kemajuan_peratus, 'lokasi' => $k->lokasi, 'prompt_ai' => $k->prompt_ai,
            'id_aliran' => $k->id_aliran, 'catatan_semakan' => $k->catatan_semakan,
        ] : $base;
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
