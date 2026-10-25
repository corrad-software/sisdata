<?php

namespace App\Modules\Pws\Http\Controllers\Admin;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Kes\Contracts\MfaAssurance;
use App\Modules\Pws\Models\KlienApi;
use App\Modules\Pws\Services\KlienService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * /api/v1/pws/klien — API clients of the public gateway (SRS-PWS-23,
 * SRS-INT-05/13). Secrets are shown exactly once (create / rotate). Managing
 * API keys requires MFA (SRS-SEC-08) when an MfaAssurance is available.
 */
class KlienController
{
    public function __construct(private KlienService $service) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => KlienApi::query()->orderBy('nama')->get()->map(fn (KlienApi $k) => self::present($k))->all(),
            'skop' => collect(KlienApi::SKOP)->map(fn ($label, $kod) => ['kod' => $kod, 'nama' => $label])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->mfa($request);
        [$klien, $secret] = $this->service->create($request->all(), $user);

        return response()->json(['data' => self::present($klien) + ['client_secret' => $secret], 'message' => __('pws::pws.mesej.klien_dicipta')], 201);
    }

    public function update(Request $request, KlienApi $klien): JsonResponse
    {
        $this->mfa($request);

        return response()->json(['data' => self::present($this->service->update($klien, $request->all())), 'message' => __('pws::pws.mesej.disimpan')]);
    }

    public function rotate(Request $request, KlienApi $klien): JsonResponse
    {
        $this->mfa($request);
        abort_if($klien->status !== 'aktif', 422, __('pws::pws.ralat.klien_batal'));
        $secret = $this->service->rotate($klien);

        return response()->json(['data' => self::present($klien->refresh()) + ['client_secret' => $secret], 'message' => __('pws::pws.mesej.klien_diputar', ['jam' => (int) config('pws.tempoh_tangguh_putaran_jam', 24)])]);
    }

    public function revoke(Request $request, KlienApi $klien): JsonResponse
    {
        $this->mfa($request);
        $data = $request->validate(['sebab' => ['required', 'string', 'min:5', 'max:500']], [], ['sebab' => 'sebab']);
        $this->service->revoke($klien, $data['sebab']);

        return response()->json(['data' => self::present($klien->refresh()), 'message' => __('pws::pws.mesej.klien_dibatal')]);
    }

    /** @return array<string, mixed> */
    public static function present(KlienApi $k): array
    {
        return [
            'id' => $k->id, 'nama' => $k->nama, 'id_klien' => $k->id_klien, 'skop' => $k->skop, 'ip_dibenarkan' => $k->ip_dibenarkan ?? [],
            'had_kadar_minit' => $k->had_kadar_minit, 'tarikh_luput' => $k->tarikh_luput?->format('Y-m-d'), 'status' => $k->status,
            'aktif' => $k->aktif(), 'tarikh_putaran' => $k->tarikh_putaran?->toIso8601String(),
            'rahsia_lama_sah_hingga' => $k->rahsia_lama_luput?->isFuture() ? $k->rahsia_lama_luput->toIso8601String() : null,
            'terakhir_digunakan' => $k->terakhir_digunakan?->toIso8601String(), 'dicipta' => $k->created_at->toIso8601String(),
        ];
    }

    private function mfa(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();
        $required = (bool) app(Parameters::class)->get('pws.mfa_kunci_api', true);
        if ($required && app()->bound(MfaAssurance::class) && ! app(MfaAssurance::class)->satisfied($user, $request)) {
            abort(403, __('pws::pws.ralat.mfa'));
        }

        return $user;
    }
}
