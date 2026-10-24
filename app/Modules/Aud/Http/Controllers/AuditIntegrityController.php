<?php

namespace App\Modules\Aud\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Aud\Services\IntegrityChecker;
use App\Modules\Aud\Services\TrailAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** SRS-AUD-07: integrity status page data and on-demand check. */
class AuditIntegrityController extends Controller
{
    public function __construct(private TrailAuditLogger $logger) {}

    public function index(): JsonResponse
    {
        $checks = DB::table('audit_integriti_semakan')->orderByDesc('id')->limit(30)->get()->map(fn ($r) => [
            'id' => (int) $r->id,
            'dimulakan_pada' => $r->dimulakan_pada,
            'selesai_pada' => $r->selesai_pada,
            'status' => $r->status,
            'bilangan_disemak' => (int) $r->bilangan_disemak,
            'no_urutan_terakhir' => $r->no_urutan_terakhir === null ? null : (int) $r->no_urutan_terakhir,
            'no_urutan_rosak' => $r->no_urutan_rosak === null ? null : (int) $r->no_urutan_rosak,
            'butiran' => $r->butiran,
            'dicetuskan_oleh' => $r->dicetuskan_oleh,
        ]);

        $this->logger->record('lihat', 'aud', 'audit_integriti_semakan');

        return response()->json(['data' => [
            'rantaian' => [
                'dalam_talian' => DB::table('audit_rekod')->count(),
                'arkib' => DB::table('audit_arkib')->count(),
                'no_urutan_terakhir' => DB::table('audit_rekod')->max('no_urutan') ?? DB::table('audit_arkib')->max('no_urutan'),
            ],
            'jadual_cron' => config('aud.jadual.semak_integriti'),
            'semakan' => $checks,
        ]]);
    }

    public function run(Request $request, IntegrityChecker $checker): JsonResponse
    {
        $result = $checker->run('manual', $request->user()?->getKey());

        $this->logger->record('semak_integriti', 'aud', 'audit_integriti_semakan', $result['id'], $result['status'] === 'lulus' ? 'berjaya' : 'gagal');

        return response()->json([
            'data' => $result,
            'message' => $result['status'] === 'lulus' ? __('aud::aud.integriti_lulus') : __('aud::aud.integriti_gagal'),
        ]);
    }
}
