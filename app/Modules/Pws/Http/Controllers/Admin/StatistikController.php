<?php

namespace App\Modules\Pws\Http\Controllers\Admin;

use App\Models\User;
use App\Modules\Pws\Models\StatistikTerbitan;
use App\Modules\Pws\Services\KandunganService;
use App\Modules\Pws\Services\StatistikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** /api/v1/pws/statistik — aggregate statistics prepared for publication on the portal. */
class StatistikController
{
    public function __construct(private StatistikService $stats) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => StatistikTerbitan::query()->orderByDesc('created_at')->limit(50)->get()->map(fn (StatistikTerbitan $s) => self::present($s))->all()]);
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate(['tempoh' => ['required', 'regex:/^\d{4}(-\d{2})?$/']], [], ['tempoh' => 'tempoh']);

        return response()->json(['data' => $this->stats->compute($data['tempoh'])]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => self::present($this->stats->snapshot($request->all(), $user)), 'message' => __('pws::pws.mesej.disimpan')], 201);
    }

    public function submit(Request $request, StatistikTerbitan $statistik, KandunganService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => self::present($service->submitStatistics($statistik, $user)), 'message' => __('pws::pws.mesej.dihantar_semakan')]);
    }

    public function withdraw(StatistikTerbitan $statistik): JsonResponse
    {
        abort_if($statistik->status !== 'diterbitkan', 422, __('pws::pws.ralat.bukan_diterbitkan'));
        $statistik->forceFill(['status' => 'ditarik'])->save();

        return response()->json(['data' => self::present($statistik), 'message' => __('pws::pws.mesej.ditarik')]);
    }

    /** @return array<string, mixed> */
    public static function present(StatistikTerbitan $s): array
    {
        return [
            'id' => $s->id, 'tajuk' => $s->tajuk, 'tempoh' => $s->tempoh, 'data' => $s->data, 'status' => $s->status,
            'dicipta_oleh' => $s->dicipta_oleh, 'diluluskan_oleh' => $s->diluluskan_oleh, 'id_aliran' => $s->id_aliran,
            'tarikh_terbit' => $s->tarikh_terbit?->toIso8601String(), 'dicipta' => $s->created_at->toIso8601String(),
        ];
    }
}
