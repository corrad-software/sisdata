<?php

namespace App\Modules\Tkt\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Tkt\Models\LogIntegrasi;
use App\Modules\Tkt\Models\Tiket;
use App\Modules\Tkt\Services\SegerakPusat;
use App\Modules\Tkt\Support\Lalai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * SRS-TKT-14/15/16. Machine endpoints for the Sistem Aduan berpusat MAIS
 * (behind the API Gateway): Sanctum token with tkt.integrasi.sistem, rate
 * limited, optional IP allowlist (parameter tkt.integrasi_ip_dibenarkan).
 * Admin endpoints: transaction log, retry, reconciliation.
 */
class IntegrasiController extends Controller
{
    public function __construct(private SegerakPusat $segerak, private AuditLogger $audit) {}

    public function terima(Request $request): JsonResponse
    {
        $this->ipDibenarkan($request);
        $data = $request->validate([
            'nombor_aduan' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
            'tajuk' => ['required', 'string', 'max:200'],
            'keterangan' => ['required', 'string', 'max:10000'],
            'kategori' => ['nullable', 'string', 'max:60'],
            'keterukan' => ['nullable', 'string', 'max:60'],
            'modul' => ['nullable', 'string', 'max:20'],
            'emel_pelapor' => ['nullable', 'email', 'max:200'],
        ]);
        [$tiket, $created] = $this->segerak->terima($data, $request->user());

        return response()->json(['data' => [
            'id_tiket' => $tiket->id,
            'nombor_tiket' => $tiket->nombor_rujukan,
            'nombor_aduan' => $data['nombor_aduan'],
            'status' => $this->segerak->petaKeluar('status', $tiket->status),
            'sedia_ada' => ! $created,
        ]], $created ? 201 : 200);
    }

    public function kemasKini(Request $request, string $nombor): JsonResponse
    {
        $this->ipDibenarkan($request);
        $data = $request->validate([
            'kunci_idempotensi' => ['required', 'string', 'max:100'],
            'tindakan' => ['required', Rule::in(['catatan', 'sahkan', 'buka_semula'])],
            'catatan' => ['required_if:tindakan,catatan', 'nullable', 'string', 'max:5000'],
        ]);
        $tiket = $this->segerak->kemasKiniMasuk($nombor, $data);

        return response()->json(['data' => ['nombor_tiket' => $tiket->nombor_rujukan, 'status' => $this->segerak->petaKeluar('status', $tiket->status)]]);
    }

    public function log(Request $request): JsonResponse
    {
        $f = $request->validate(['arah' => ['nullable', Rule::in(['masuk', 'keluar'])], 'status' => ['nullable', 'string', 'max:20']]);
        $page = LogIntegrasi::query()
            ->when($f['arah'] ?? null, fn ($q, $v) => $q->where('arah', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('id')->paginate(50);
        $numbers = Tiket::query()->whereIn('id', $page->getCollection()->pluck('id_tiket')->filter()->unique())->pluck('nombor_rujukan', 'id');

        return response()->json([
            'data' => $page->getCollection()->map(fn (LogIntegrasi $l) => [
                'id' => $l->id, 'id_tiket' => $l->id_tiket, 'nombor_rujukan' => $numbers[$l->id_tiket] ?? null, 'arah' => $l->arah, 'endpoint' => $l->endpoint,
                'kunci_idempotensi' => $l->kunci_idempotensi, 'ringkasan' => $l->ringkasan_payload, 'status' => $l->status, 'kod_status' => $l->kod_status,
                'cubaan' => $l->bilangan_cuba_semula, 'cuba_seterusnya_pada' => $l->cuba_seterusnya_pada?->toIso8601String(), 'ralat' => $l->mesej_ralat,
                'cap_masa' => $l->cap_masa->toIso8601String(),
            ])->values(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function rekonsiliasi(): JsonResponse
    {
        return response()->json(['data' => array_map(fn ($r) => $r + [
            'status_semasa_nama' => Lalai::STATUS[$r['status_semasa']] ?? $r['status_semasa'],
            'status_disegerak_nama' => $r['status_disegerak'] !== null ? (Lalai::STATUS[$r['status_disegerak']] ?? $r['status_disegerak']) : null,
        ], $this->segerak->rekonsiliasi())]);
    }

    public function ulangCuba(): JsonResponse
    {
        $out = $this->segerak->ulangCuba(true);
        $this->audit->record('ulang_cuba', 'tkt', 'tkt_log_integrasi', null, meta: $out);

        return response()->json(['data' => $out, 'message' => __('tkt::tkt.mesej.ulang_cuba', $out)]);
    }

    public function selaras(): JsonResponse
    {
        $n = $this->segerak->selaras();
        $this->audit->record('rekonsiliasi', 'tkt', 'tkt_pautan_aduan', null, meta: ['diselaras' => $n]);

        return response()->json(['data' => ['diselaras' => $n], 'message' => __('tkt::tkt.mesej.selaras', ['n' => $n])]);
    }

    private function ipDibenarkan(Request $request): void
    {
        $list = array_values(array_filter(array_map('trim', explode(',', (string) app(Parameters::class)->get('tkt.integrasi_ip_dibenarkan', '')))));
        if ($list !== [] && ! IpUtils::checkIp((string) $request->ip(), $list)) {
            abort(403, (string) __('tkt::tkt.ralat.ip_tidak_dibenarkan'));
        }
    }
}
