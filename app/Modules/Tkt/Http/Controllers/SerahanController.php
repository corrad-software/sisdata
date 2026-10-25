<?php

namespace App\Modules\Tkt\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Tkt\Models\Pasukan;
use App\Modules\Tkt\Services\KalendarSokongan;
use App\Modules\Tkt\Services\Serahan;
use App\Modules\Tkt\Support\Eksport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** SRS-TKT-21 / §18.7: support hand-over package and team transfer (vendor → BTM MAIS). */
class SerahanController extends Controller
{
    public function __construct(private Serahan $serahan, private AuditLogger $audit) {}

    public function pakej(): Response
    {
        $this->audit->record('eksport', 'tkt', 'tkt_tiket', null, meta: ['pakej' => 'serahan_sokongan']);

        return Eksport::download('xlsx', 'pakej-serahan-sokongan-'.now()->format('Ymd'), (string) __('tkt::tkt.serahan.tajuk'), $this->serahan->pakej(),
            (string) __('tkt::tkt.laporan.dijana', ['masa' => now()->setTimezone(KalendarSokongan::TZ)->format('d/m/Y H:i')]));
    }

    public function pindah(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dari_pasukan' => ['required', 'integer', 'exists:tkt_pasukan,id'],
            'ke_pasukan' => ['required', 'integer', 'exists:tkt_pasukan,id', 'different:dari_pasukan'],
            'catatan' => ['required', 'string', 'min:5', 'max:1000'],
            'nyahaktif_sumber' => ['nullable', 'boolean'],
        ]);
        $out = $this->serahan->pindah(Pasukan::query()->findOrFail($data['dari_pasukan']), Pasukan::query()->findOrFail($data['ke_pasukan']), $request->user(), $data['catatan'], (bool) ($data['nyahaktif_sumber'] ?? false));

        return response()->json(['data' => $out, 'message' => __('tkt::tkt.mesej.dipindah', $out)]);
    }
}
