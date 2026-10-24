<?php

namespace App\Modules\Dsh\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Dsh\Contracts\InsightGenerator;
use App\Modules\Dsh\Models\LogPromptAi;
use App\Modules\Dsh\Services\AiPrompt;
use App\Modules\Dsh\Services\Aras;
use App\Modules\Dsh\Services\Penapis;
use App\Modules\Dsh\Services\Ringkasan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/** AI prompt + automatic insights (SRS-DSH-15..18) — local engines only. */
class AiController extends Controller
{
    public function __construct(private Aras $aras) {}

    private function aras(Request $request, User $user): string
    {
        $aras = (string) $request->input('aras', $this->aras->default($user) ?? '');
        abort_unless($this->aras->allows($user, $aras), 403, __('dsh::dsh.aras_tiada_kebenaran'));

        return $aras;
    }

    public function prompt(Request $request, AiPrompt $prompt): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate(['prompt' => ['required', 'string', 'min:2', 'max:1000'], 'aras' => ['nullable', 'string'], 'penapis' => ['nullable', 'array']]);
        $aras = $this->aras($request, $user);

        return response()->json(['data' => $prompt->ask($user, $data['prompt'], $aras, Penapis::from((array) ($data['penapis'] ?? [])))]);
    }

    public function insights(Request $request, InsightGenerator $generator, Ringkasan $ringkasan): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $aras = $this->aras($request, $user);
        $kpis = $ringkasan->compute($user, $aras, Penapis::fromRequest($request));
        try {
            $insights = $generator->insights($user, $kpis);
        } catch (Throwable $e) {
            report($e);
            $insights = [];
        }

        return response()->json(['data' => $insights, 'enjin' => $generator->engine()]);
    }

    /** The user's own prompt history; admins with dsh.log_ai.lihat see everyone's. */
    public function log(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $all = $user->can('dsh.log_ai.lihat') && $request->boolean('semua');

        return response()->json(['data' => LogPromptAi::query()
            ->when(! $all, fn ($q) => $q->where('id_pengguna', $user->id))
            ->orderByDesc('cap_masa')->limit(100)->get()]);
    }
}
