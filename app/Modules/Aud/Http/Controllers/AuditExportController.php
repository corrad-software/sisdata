<?php

namespace App\Modules\Aud\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Aud\Http\Requests\AuditSearchRequest;
use App\Modules\Aud\Services\AuditExporter;
use App\Modules\Aud\Services\TrailAuditLogger;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** SRS-AUD-12/13/14: export packages with checksum + metadata; export history. */
class AuditExportController extends Controller
{
    public function download(AuditSearchRequest $request, AuditExporter $exporter, AccessControl $access): BinaryFileResponse
    {
        /** @var User $user */
        $user = $request->user();
        if ($request->wantsArchive() && ! $access->allows($user, 'aud.arkib.lihat')) {
            abort(403, __('aud::aud.tiada_kebenaran_arkib'));
        }

        $result = $exporter->export((string) $request->input('format', 'csv'), $request->criteria(), $user);

        return response()->download($result['path'], $result['filename'], [
            'Content-Type' => 'application/zip',
            'X-Checksum-SHA256' => $result['checksum'],
            'X-Audit-Eksport-Id' => (string) $result['id'],
            'X-Bilangan-Rekod' => (string) $result['count'],
            'Access-Control-Expose-Headers' => 'X-Checksum-SHA256, X-Audit-Eksport-Id, X-Bilangan-Rekod, Content-Disposition',
        ])->deleteFileAfterSend();
    }

    public function history(TrailAuditLogger $logger): JsonResponse
    {
        $rows = DB::table('audit_eksport')->orderByDesc('id')->limit(100)->get()->map(fn ($r) => [
            'id' => (int) $r->id,
            'pengecam_pengguna' => $r->pengecam_pengguna,
            'format' => $r->format,
            'bilangan_rekod' => (int) $r->bilangan_rekod,
            'checksum' => $r->checksum,
            'nama_fail' => $r->nama_fail,
            'saiz_bait' => (int) $r->saiz_bait,
            'kriteria_carian' => json_decode((string) $r->kriteria_carian, true),
            'tarikh_masa_tempatan' => substr((string) $r->tarikh_masa_tempatan, 0, 19),
        ]);

        $logger->record('lihat', 'aud', 'audit_eksport', null, 'berjaya', null, [], ['senarai' => true]);

        return response()->json(['data' => $rows]);
    }
}
