<?php

namespace App\Modules\Lap\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Lap\Services\AiPrompt;
use App\Modules\Lap\Services\Enjin;
use App\Modules\Lap\Services\Katalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI prompt → report (SRS-LAP-13) and smart property search (SRS-LAP-14),
 * both rule-based inside SISDATA (SRS-LAP-21) and executed under the user's
 * own scope.
 */
class AiController extends Controller
{
    public function __construct(private AiPrompt $ai, private AuditLogger $audit) {}

    public function prompt(Request $request): JsonResponse
    {
        $data = $request->validate(['prompt' => ['required', 'string', 'min:3', 'max:500']]);
        /** @var User $user */
        $user = $request->user();
        $r = $this->ai->interpret($user, $data['prompt']);
        $this->audit->record('ai_prompt', 'lap', null, null, $r['difahami'] ? 'berjaya' : 'gagal', meta: ['prompt' => mb_substr($data['prompt'], 0, 500), 'set' => $r['definisi']['set'] ?? null, 'enjin' => 'peraturan']);

        return response()->json(['data' => $r + ['enjin' => 'peraturan', 'label_cadangan' => __('lap::lap.label_cadangan')]]);
    }

    public function search(Request $request, Enjin $enjin, Katalog $katalog): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:200'], 'halaman' => ['nullable', 'integer', 'min:1']]);
        /** @var User $user */
        $user = $request->user();
        abort_unless($katalog->canUseSet($user, 'lot'), 403, __('lap::lap.ralat.tiada_akses'));
        $f = $this->ai->searchFilters($data['q']);
        if ($f['penapis'] === []) {
            return response()->json(['data' => ['tafsiran' => [], 'hasil' => null, 'mesej' => __('lap::lap.carian.tiada_kata_kunci')]]);
        }
        $def = $enjin->normalise(['set' => 'lot', 'medan' => ['no_lot', 'kod_daerah', 'kod_mukim', 'kategori_guna_tanah', 'status_lot', 'bahagian', 'keluasan', 'ada_pajakan_aktif', 'keadaan_tapak_terakhir'], 'penapis' => $f['penapis']], $user);
        $res = $enjin->run($user, $def, ['halaman' => (int) ($data['halaman'] ?? 1), 'per_halaman' => 25]);
        $this->audit->record('carian', 'lap', 'lot_tanah', null, meta: ['kata_kunci' => mb_substr($data['q'], 0, 200), 'bilangan' => $res['bilangan_rekod']]);

        return response()->json(['data' => ['tafsiran' => $f['tafsiran'], 'definisi' => $def, 'hasil' => $res]]);
    }
}
